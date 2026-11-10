<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");
require_once(FUNCTIONS_PATH . DS . "gradeFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
 * recommendationProcess.php
 * -------------------------
 * Handles the buttons of manage_recommendations.php:
 *   generate = "Refresh recommendations"  (works out every recommendation again)
 *   save     = "Save note"
 *   toggle   = "Mark done" / "Undo done"
 * When it is finished it shows manage_recommendations.php again.
 *
 * This file has 4 parts:
 *   1. Settings          - the marks and the tips (change them here)
 *   2. Helper functions  - small functions: status, advice, refresh
 *   3. The action        - what to do for generate / save / toggle
 *   4. Show the page again
 */


// =====================================================================
// 1. SETTINGS
// !!! THESE NUMBERS ARE PLACEHOLDERS. Put your school's real marks here. !!!
// =====================================================================
const REC_PASSING     = 75;   // weighted grade below this = "At risk"
const REC_WATCH_UNTIL = 80;   // from the passing mark up to this = "Needs attention"
const REC_EXCELLENT   = 90;   // this and above = "Excellent"
const REC_TREND_DROP  = 10;   // scores in a group that went down by this much (first to last) = a warning

// What to suggest when a group of scores is weak. The keys must be the group names used in GRADE_GROUPS.
const REC_TIPS = [
    'Performance tasks' => 'Check for missing or low performance tasks and allow a make-up or resubmission.',
    'Recitation'        => 'Give the student more chances to take part in class, for example short oral check-ins.',
    'Summative tests'   => 'Go over the topics of these tests with the student and re-teach before the next one.',
    'Term exams'        => 'Hold a review or remedial session before the next term exam.',
];
const REC_TIP_DEFAULT = 'Review this area with the student.';


// =====================================================================
// 2. HELPER FUNCTIONS
// =====================================================================

// The status for a weighted grade: 'risk', 'watch', 'ok' or 'excellent'.
function RecommendationStatus($final)
{
    if ($final < REC_PASSING) {
        return 'risk';
    }
    if ($final < REC_WATCH_UNTIL) {
        return 'watch';
    }
    if ($final >= REC_EXCELLENT) {
        return 'excellent';
    }
    return 'ok';
}

// The scores that were typed in one group (for example Recitation), in column order.
// Empty boxes are left out.
function EnteredScores($row, $group)
{
    $scores = [];
    foreach ($group['columns'] as $column) {
        if (isset($row[$column]) && $row[$column] !== '') {
            $scores[] = (float)$row[$column];
        }
    }
    return $scores;
}

// One advice line that says what the empty boxes must score for the student to reach the passing mark.
// Returns '' when there is nothing to say.
function ScoreNeededLine($status, $pointsSoFar, $pointsOpen, $totalWeight, $emptyBoxes)
{
    // only for students who are at risk or close to the passing mark
    if ($status !== 'risk' && $status !== 'watch') {
        return '';
    }

    // no empty box is left, so the grade can not change any more
    if ($pointsOpen <= 0 || $totalWeight <= 0) {
        if ($status === 'risk') {
            return 'All scores are in and the grade is below ' . REC_PASSING
                 . '. Plan make-up work or remedial classes.';
        }
        return '';
    }

    // The weighted grade is (pointsSoFar + pointsOpen * x) / totalWeight,
    // where x is the average the empty boxes will get.
    // Solving for the passing mark gives the x below.
    $needed = (REC_PASSING * $totalWeight - $pointsSoFar) / $pointsOpen;
    $left   = $emptyBoxes . ' remaining score' . ($emptyBoxes === 1 ? '' : 's');

    if ($needed > GRADE_MAX) {
        return 'Even perfect scores on the ' . $left . ' would not reach ' . REC_PASSING
             . '. Talk about remedial options now.';
    }
    if ($needed > 0) {
        return 'Needs at least ' . (int)ceil(round($needed, 2)) . ' on each of the '
             . $left . ' to reach ' . REC_PASSING . '.';
    }
    return '';
}

// The advice for one student + subject. One line per advice, joined with "\n"
// (the page splits it into a list again). $row holds the saved scores.
function RecommendationAdvice($row, $status)
{
    // ---- look at every group of scores and collect the facts ----
    $weakGroups  = [];    // group name => average, only groups below the passing mark
    $dropLines   = [];    // advice lines for groups whose scores went down
    $emptyGroups = [];    // names of groups without any score yet
    $pointsSoFar = 0.0;   // weighted points earned so far (an empty box counts as 0)
    $pointsOpen  = 0.0;   // weighted points the empty boxes could still add
    $totalWeight = 0.0;   // all weights together
    $emptyBoxes  = 0;     // how many boxes are still empty

    foreach (GRADE_GROUPS as $name => $group) {
        $scores  = EnteredScores($row, $group);
        $boxes   = count($group['columns']);
        $entered = count($scores);

        if ($entered === 0) {
            $emptyGroups[] = $name;
        } else {
            $average = array_sum($scores) / $entered;
            if ($average < REC_PASSING) {
                $weakGroups[$name] = $average;
            }

            // did the scores go down from the first to the last one?
            $first = $scores[0];
            $last  = $scores[$entered - 1];
            if ($entered >= 2 && $first - $last >= REC_TREND_DROP) {
                $dropLines[] = 'Scores in ' . $name . ' went down from '
                             . FormatScore(number_format($first, 2, '.', '')) . ' to '
                             . FormatScore(number_format($last, 2, '.', '')) . '. Talk to the student early.';
            }
        }

        $totalWeight += $group['weight'];
        $pointsSoFar += $group['weight'] * array_sum($scores) / $boxes;
        $pointsOpen  += $group['weight'] * ($boxes - $entered) / $boxes;
        $emptyBoxes  += $boxes - $entered;
    }

    // ---- write the advice lines, most important first ----
    $advice = [];

    // 1. the two weakest groups (lowest average first)
    asort($weakGroups);
    $twoWeakest = array_slice(array_keys($weakGroups), 0, 2);
    foreach ($twoWeakest as $name) {
        $tip      = REC_TIPS[$name] ?? REC_TIP_DEFAULT;
        $advice[] = $name . ' average is ' . number_format($weakGroups[$name], 1) . '. ' . $tip;
    }

    // 2. scores that are going down
    foreach ($dropLines as $line) {
        $advice[] = $line;
    }

    // 3. what is still needed to pass
    $neededLine = ScoreNeededLine($status, $pointsSoFar, $pointsOpen, $totalWeight, $emptyBoxes);
    if ($neededLine !== '') {
        $advice[] = $neededLine;
    }

    // 4. groups without any score yet
    if ($emptyGroups) {
        $advice[] = 'No scores yet in ' . implode(', ', $emptyGroups) . ', so the grade can still change.';
    }

    // 5. nothing to flag, so give a short default line
    if (!$advice) {
        if ($status === 'excellent') {
            $advice[] = 'Doing very well. Consider enrichment work or peer tutoring.';
        } elseif ($status === 'watch') {
            $advice[] = 'Close to the passing mark. Check the next scores early.';
        } else {
            $advice[] = 'On track. No action needed.';
        }
    }

    return implode("\n", $advice);
}

// REFRESH: work out the recommendation of every student + subject from the saved scores and save it.
// The teacher's note is kept. "Done" is kept too, unless the status changed.
// $subjectIds = the subjects to refresh (integers). Returns the message to show.
function RefreshRecommendations($pdo, $yearId, $subjectIds)
{
    $subjectList  = implode(', ', $subjectIds);                           // integers only
    $scoreColumns = 'g.' . implode(', g.', array_keys(GRADE_COLUMNS));    // fixed list, never user input

    // Step 1: every enrolled student + subject with the saved scores
    // (the score columns are empty when nothing is saved yet)
    $stmt = $pdo->prepare("SELECT e.student_id, e.subject_id, $scoreColumns
                           FROM enrollments e
                           JOIN students s ON s.id = e.student_id
                           LEFT JOIN grades g ON g.student_id = e.student_id AND g.subject_id = e.subject_id
                           WHERE s.school_year_id = :year AND e.subject_id IN ($subjectList)");
    $stmt->execute([':year' => $yearId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Step 2: the statement that saves one recommendation.
    // If this student + subject already has one, it is updated instead (ON CONFLICT ... DO UPDATE).
    // The note is not touched. is_done stays as it was when the status is the same, otherwise it goes back to 0.
    $save = $pdo->prepare("INSERT INTO recommendations (student_id, subject_id, status, final_grade, advice, generated_at)
                           VALUES (:student, :subject, :status, :final, :advice, now())
                           ON CONFLICT (student_id, subject_id)
                           DO UPDATE SET is_done = CASE WHEN recommendations.status = EXCLUDED.status
                                                        THEN recommendations.is_done ELSE 0 END,
                                         status       = EXCLUDED.status,
                                         final_grade  = EXCLUDED.final_grade,
                                         advice       = EXCLUDED.advice,
                                         generated_at = now()");

    // Step 3: work out and save one recommendation for each row
    $stillValid = [];   // "student-subject" => true, for every row that has a recommendation now
    $saved      = 0;
    $atRisk     = 0;
    $noScores   = 0;

    foreach ($rows as $row) {
        // the weighted grade (same as the grade pages). null = no score entered yet
        $final = GradeFinal($row);
        if ($final === null) {
            $noScores++;
            continue;   // nothing to recommend yet
        }

        $status = RecommendationStatus($final);
        $advice = RecommendationAdvice($row, $status);

        $save->execute([
            ':student' => (int)$row['student_id'],
            ':subject' => (int)$row['subject_id'],
            ':status'  => $status,
            ':final'   => number_format($final, 2, '.', ''),
            ':advice'  => $advice,
        ]);

        $stillValid[$row['student_id'] . '-' . $row['subject_id']] = true;
        $saved++;
        if ($status === 'risk') {
            $atRisk++;
        }
    }

    // Step 4: a saved recommendation that has no scores or enrollment any more is removed
    $stmt = $pdo->prepare("SELECT id, student_id, subject_id FROM recommendations WHERE subject_id IN ($subjectList)");
    $stmt->execute();
    $delete = $pdo->prepare("DELETE FROM recommendations WHERE id = :id");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $old) {
        if (!isset($stillValid[$old['student_id'] . '-' . $old['subject_id']])) {
            $delete->execute([':id' => $old['id']]);
        }
    }

    // Step 5: the message for the teacher
    $text = 'Recommendations refreshed: ' . $saved . ' row' . ($saved === 1 ? '' : 's') . ', ' . $atRisk . ' at risk.';
    if ($noScores) {
        $text .= ' ' . $noScores . ' without any score yet ' . ($noScores === 1 ? 'was' : 'were') . ' skipped.';
    }
    return alertBox('success', $text);
}


// =====================================================================
// 3. THE ACTION
// =====================================================================
$action  = $_POST['action'] ?? '';
$message = '';
$myId    = (int)($_SESSION['user_id'] ?? 0);   // decides which subjects this user may work on (admin = all)

try {
    $year = CurrentSchoolYear($pdo);

    if (!$year) {
        $message = alertBox('danger', 'No school year is running.');
    } else {
        // the subjects this user grades: subject id => name
        $gradable = GradableSubjects($pdo, (int)$year['id'], $myId);

        $pdo->beginTransaction();

        if ($action === 'generate') {
            // ---- "Refresh recommendations" ----
            // the chosen subject (only if it is mine), otherwise all of my subjects
            $subjectId = (int)($_POST['subject_id'] ?? 0);
            if (isset($gradable[$subjectId])) {
                $ids = [$subjectId];
            } else {
                $ids = array_map('intval', array_keys($gradable));
            }

            if (!$ids) {
                $message = alertBox('danger', 'You have no subjects to grade in this school year.');
            } else {
                $message = RefreshRecommendations($pdo, (int)$year['id'], $ids);
            }

        } elseif ($action === 'save' || $action === 'toggle') {
            // ---- "Save note" or "Mark done / Undo done" ----
            // (the note typed in the same row is saved by both buttons)
            $note = trim(is_string($_POST['note'] ?? null) ? $_POST['note'] : '');
            $note = mb_substr($note, 0, 500);

            // the recommendation must be of this school year and of a subject this user grades
            $stmt = $pdo->prepare("SELECT r.id, r.subject_id, r.is_done
                                   FROM recommendations r
                                   JOIN students s ON s.id = r.student_id
                                   WHERE r.id = :id AND s.school_year_id = :year");
            $stmt->execute([':id' => (int)($_POST['id'] ?? 0), ':year' => $year['id']]);
            $rec = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$rec || !isset($gradable[(int)$rec['subject_id']])) {
                $message = alertBox('danger', 'Recommendation not found.');
            } else {
                $done = (int)$rec['is_done'];
                if ($action === 'toggle') {
                    $done = $done ? 0 : 1;   // switch the done mark
                }

                $stmt = $pdo->prepare("UPDATE recommendations SET teacher_note = :note, is_done = :done WHERE id = :id");
                $stmt->execute([
                    ':note' => $note === '' ? null : $note,
                    ':done' => $done,
                    ':id'   => $rec['id'],
                ]);

                if ($action === 'save') {
                    $message = alertBox('success', 'Note saved.');
                } elseif ($done) {
                    $message = alertBox('success', 'Marked as done.');
                } else {
                    $message = alertBox('success', 'Marked as not done.');
                }
            }
        }

        $pdo->commit();
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    err_log($e->getMessage());
    $message = alertBox('danger', 'Could not save. Please check the data and try again.');
}


// =====================================================================
// 4. SHOW THE PAGE AGAIN
// Give the page its state back (same keys as $state in manage_recommendations.php).
// The page checks every value, so nothing here needs cleaning.
// =====================================================================
$_GET = [
    'sort' => $_POST['sort'] ?? '',
    'dir' => $_POST['dir'] ?? '',
    'search' => $_POST['search'] ?? '',
    'subject_id' => $_POST['subject_id'] ?? 0,
    'status' => $_POST['status'] ?? '',
    'PerPageLimit' => $_POST['PerPageLimit'] ?? 20,
    'PageNum' => $_POST['PageNum'] ?? 1,
];

require_once(COMPONENTS_PATH . DS . "manage_recommendations.php");
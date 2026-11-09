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

// !!! THESE NUMBERS ARE PLACEHOLDERS. Put your school's real marks here. !!!
$passing    = 75;   // weighted grade below this = "At risk"
$watchUntil = 80;   // from the passing mark up to this = "Needs attention"
$excellent  = 90;   // this and above = "Excellent"
$trendDrop  = 10;   // scores of a group that went down by this much (first to last) = a warning

// What to suggest when a group of scores is weak. The keys must be the group names used in GRADE_GROUPS.
$tips = [
    'Performance tasks' => 'Check for missing or low performance tasks and allow a make-up or resubmission.',
    'Recitation'        => 'Give the student more chances to take part in class, for example short oral check-ins.',
    'Summative tests'   => 'Go over the topics of these tests with the student and re-teach before the next one.',
    'Term exams'        => 'Hold a review or remedial session before the next term exam.',
];
$tipDefault = 'Review this area with the student.';

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

        // ---------------------------------------------------------------------------------
        // REFRESH: work out the recommendation of every student + subject from the saved
        // scores and save it. Notes are kept. "Done" is kept too, unless the status changed.
        // ---------------------------------------------------------------------------------
        if ($action === 'generate') {
            // the chosen subject (only if it is mine), otherwise all of my subjects
            $subjectId = (int)($_POST['subject_id'] ?? 0);
            $ids       = isset($gradable[$subjectId]) ? [$subjectId] : array_map('intval', array_keys($gradable));

            if (!$ids) {
                $message = alertBox('danger', 'You have no subjects to grade in this school year.');
            } else {
                $inIds     = implode(', ', $ids);                                   // integers only
                $scoreCols = 'g.' . implode(', g.', array_keys(GRADE_COLUMNS));     // fixed list, never user input

                // every enrolled student + subject with the saved scores (the columns are empty when nothing is saved)
                $stmt = $pdo->prepare("SELECT e.student_id, e.subject_id, $scoreCols
                                       FROM enrollments e
                                       JOIN students s ON s.id = e.student_id
                                       LEFT JOIN grades g ON g.student_id = e.student_id AND g.subject_id = e.subject_id
                                       WHERE s.school_year_id = :year AND e.subject_id IN ($inIds)");
                $stmt->execute([':year' => $year['id']]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $save = $pdo->prepare("INSERT INTO recommendations (student_id, subject_id, status, final_grade, advice, generated_at)
                                       VALUES (:student, :subject, :status, :final, :advice, now())
                                       ON CONFLICT (student_id, subject_id)
                                       DO UPDATE SET is_done = CASE WHEN recommendations.status = EXCLUDED.status
                                                                    THEN recommendations.is_done ELSE 0 END,
                                                     status       = EXCLUDED.status,
                                                     final_grade  = EXCLUDED.final_grade,
                                                     advice       = EXCLUDED.advice,
                                                     generated_at = now()");

                $kept     = [];   // "student-subject" => true, for the rows that have a recommendation now
                $saved    = 0;
                $atRisk   = 0;
                $noScores = 0;

                foreach ($rows as $row) {
                    // the weighted grade (same as the grade pages), null = no score entered yet
                    $final = GradeFinal($row);
                    if ($final === null) {
                        $noScores++;
                        continue;   // nothing to recommend yet
                    }

                    // status
                    if ($final < $passing) {
                        $status = 'risk';
                    } elseif ($final < $watchUntil) {
                        $status = 'watch';
                    } elseif ($final >= $excellent) {
                        $status = 'excellent';
                    } else {
                        $status = 'ok';
                    }

                    // look at every group of scores (performance tasks, recitation, ...)
                    $weak      = [];     // group => average, only the groups under the passing mark
                    $drops     = [];     // advice lines for groups whose scores went down
                    $missing   = [];     // groups without any score yet
                    $have      = 0.0;    // weighted points from the scores entered (empty boxes count as 0)
                    $open      = 0.0;    // weight that the empty boxes can still add
                    $weights   = 0.0;    // all weights together
                    $remaining = 0;      // number of empty boxes

                    foreach (GRADE_GROUPS as $name => $group) {
                        $scores = [];    // the scores entered in this group, in column order
                        foreach ($group['columns'] as $col) {
                            if (isset($row[$col]) && $row[$col] !== '') {
                                $scores[] = (float)$row[$col];
                            }
                        }
                        $boxes   = count($group['columns']);
                        $entered = count($scores);

                        if ($entered === 0) {
                            $missing[] = $name;
                        } else {
                            $avg = array_sum($scores) / $entered;
                            if ($avg < $passing) {
                                $weak[$name] = $avg;
                            }
                            if ($entered >= 2 && $scores[0] - $scores[$entered - 1] >= $trendDrop) {
                                $drops[] = 'Scores in ' . $name . ' went down from '
                                         . FormatScore(number_format($scores[0], 2, '.', '')) . ' to '
                                         . FormatScore(number_format($scores[$entered - 1], 2, '.', '')) . '. Talk to the student early.';
                            }
                        }

                        $weights   += $group['weight'];
                        $have      += $group['weight'] * array_sum($scores) / $boxes;
                        $open      += $group['weight'] * ($boxes - $entered) / $boxes;
                        $remaining += $boxes - $entered;
                    }

                    // the advice, most important first
                    $advice = [];

                    // 1. the two weakest groups
                    asort($weak);
                    foreach (array_slice($weak, 0, 2, true) as $name => $avg) {
                        $advice[] = $name . ' average is ' . number_format($avg, 1) . '. ' . ($tips[$name] ?? $tipDefault);
                    }

                    // 2. scores that are going down
                    foreach ($drops as $line) {
                        $advice[] = $line;
                    }

                    // 3. what is still needed to pass (only for students under or close to the passing mark)
                    //    weighted grade = (have + open * x) / weights, where x is the average of the empty boxes,
                    //    so the x that reaches the passing mark is (passing * weights - have) / open
                    if ($status === 'risk' || $status === 'watch') {
                        if ($open <= 0 || $weights <= 0) {
                            if ($status === 'risk') {
                                $advice[] = 'All scores are in and the grade is below ' . $passing
                                          . '. Plan make-up work or remedial classes as your school allows.';
                            }
                        } else {
                            $needed = ($passing * $weights - $have) / $open;
                            $left   = $remaining . ' remaining score' . ($remaining === 1 ? '' : 's');

                            if ($needed > GRADE_MAX) {
                                $advice[] = 'Even perfect scores on the ' . $left . ' would not reach ' . $passing
                                          . '. Talk about remedial options now.';
                            } elseif ($needed > 0) {
                                $advice[] = 'Needs at least ' . (int)ceil(round($needed, 2)) . ' on each of the '
                                          . $left . ' to reach ' . $passing . '.';
                            }
                        }
                    }

                    // 4. groups without any score yet
                    if ($missing) {
                        $advice[] = 'No scores yet in ' . implode(', ', $missing) . ', so the grade can still change.';
                    }

                    // nothing to flag
                    if (!$advice) {
                        if ($status === 'excellent') {
                            $advice[] = 'Doing very well. Consider enrichment work or peer tutoring.';
                        } elseif ($status === 'watch') {
                            $advice[] = 'Close to the passing mark. Check the next scores early.';
                        } else {
                            $advice[] = 'On track. No action needed.';
                        }
                    }

                    $save->execute([
                        ':student' => (int)$row['student_id'],
                        ':subject' => (int)$row['subject_id'],
                        ':status'  => $status,
                        ':final'   => number_format($final, 2, '.', ''),
                        ':advice'  => implode("\n", $advice),   // one line per advice, the page splits it again
                    ]);

                    $kept[$row['student_id'] . '-' . $row['subject_id']] = true;
                    $saved++;
                    if ($status === 'risk') {
                        $atRisk++;
                    }
                }

                // a saved recommendation without scores or enrollment any more is removed
                $stmt = $pdo->prepare("SELECT id, student_id, subject_id FROM recommendations WHERE subject_id IN ($inIds)");
                $stmt->execute();
                $remove = $pdo->prepare("DELETE FROM recommendations WHERE id = :id");
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $old) {
                    if (!isset($kept[$old['student_id'] . '-' . $old['subject_id']])) {
                        $remove->execute([':id' => $old['id']]);
                    }
                }

                $text = 'Recommendations refreshed: ' . $saved . ' row' . ($saved === 1 ? '' : 's') . ', ' . $atRisk . ' at risk.';
                if ($noScores) {
                    $text .= ' ' . $noScores . ' without any score yet ' . ($noScores === 1 ? 'was' : 'were') . ' skipped.';
                }
                $message = alertBox('success', $text);
            }

        // ---------------------------------------------------------------------------------
        // SAVE a note, or TOGGLE "follow-up done" (the note typed in the same row is saved too)
        // ---------------------------------------------------------------------------------
        } elseif ($action === 'save' || $action === 'toggle') {
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
                    $done = $done ? 0 : 1;
                }

                $stmt = $pdo->prepare("UPDATE recommendations SET teacher_note = :note, is_done = :done WHERE id = :id");
                $stmt->execute([
                    ':note' => $note === '' ? null : $note,
                    ':done' => $done,
                    ':id'   => $rec['id'],
                ]);

                if ($action === 'toggle') {
                    $message = alertBox('success', $done ? 'Marked as done.' : 'Marked as not done.');
                } else {
                    $message = alertBox('success', 'Note saved.');
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

// Hand the page its state back (same keys as $state in manage_recommendations.php; the page checks every value)
$_GET = [
    'sort'         => $_POST['sort']         ?? '',
    'dir'          => $_POST['dir']          ?? '',
    'search'       => $_POST['search']       ?? '',
    'subject_id'   => $_POST['subject_id']   ?? 0,
    'status'       => $_POST['status']       ?? '',
    'PerPageLimit' => $_POST['PerPageLimit'] ?? 20,
    'PageNum'      => $_POST['PageNum']      ?? 1,
];

require_once(COMPONENTS_PATH . DS . "manage_recommendations.php");
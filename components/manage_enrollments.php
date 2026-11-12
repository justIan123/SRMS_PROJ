<?php
require_once("../config/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");

$self = COMPONENTS_URL . 'manage_enrollments.php';

// ---- state of the page ----
// by = "subject": pick a subject, tick the students (can be narrowed to one grade level)
// by = "student": search for a student, tick the subjects
$by         = ($_GET['by'] ?? '') === 'student' ? 'student' : 'subject';
$subjectId  = (int)($_GET['subject_id'] ?? 0);
$studentId  = (int)($_GET['student_id'] ?? 0);
$gradeLevel = trim($_GET['grade_level'] ?? '');
$search     = trim($_GET['search'] ?? '');   // text typed in the student search box (By student only)

try {
    // Everything belongs to the current school year
    $year = CurrentSchoolYear($pdo);
    if (!$year) {
        echo alertBox('warning', 'No school year is running. Start one in School Years first.');
        return;
    }

    // subject id => name (active subjects only)
    $stmt = $pdo->prepare("SELECT id, subject_name FROM subjects
                           WHERE school_year_id = :year AND is_active = 1
                           ORDER BY subject_name");
    $stmt->execute([':year' => $year['id']]);
    $subjects = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $stmt = $pdo->prepare("SELECT id, fname, lname, grade_level FROM students
                           WHERE school_year_id = :year
                           ORDER BY lname, fname, id");
    $stmt->execute([':year' => $year['id']]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $gradeLevels = array_values(array_unique(array_filter(array_column($students, 'grade_level'), fn($g) => $g !== null && $g !== '')));
    sort($gradeLevels, SORT_NATURAL);

    // what is ticked, and which of those already have grades (these cannot be un-enrolled)
    $enrolled = [];   // id => true
    $graded   = [];   // id => true
    $subject  = null;
    $student  = null;
    $rows     = [];   // what the table lists: [id, name, grade level]

    if ($by === 'subject') {
        $subject = $subjects[$subjectId] ?? null;
        if ($subject !== null) {
            $stmt = $pdo->prepare("SELECT student_id FROM enrollments WHERE subject_id = :sub");
            $stmt->execute([':sub' => $subjectId]);
            $enrolled = array_fill_keys(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);

            $stmt = $pdo->prepare("SELECT DISTINCT student_id FROM grades WHERE subject_id = :sub");
            $stmt->execute([':sub' => $subjectId]);
            $graded = array_fill_keys(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);

            foreach ($students as $s) {
                if ($gradeLevel === '' || $s['grade_level'] === $gradeLevel) {
                    $rows[] = [(int)$s['id'], $s['lname'] . ', ' . $s['fname'], (string)($s['grade_level'] ?? '')];
                }
            }
        }
    } else {
        foreach ($students as $s) {
            if ((int)$s['id'] === $studentId) {
                $student = $s;
                break;
            }
        }
        if ($student) {
            $stmt = $pdo->prepare("SELECT subject_id FROM enrollments WHERE student_id = :sid");
            $stmt->execute([':sid' => $studentId]);
            $enrolled = array_fill_keys(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);

            $stmt = $pdo->prepare("SELECT DISTINCT subject_id FROM grades WHERE student_id = :sid");
            $stmt->execute([':sid' => $studentId]);
            $graded = array_fill_keys(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);

            foreach ($subjects as $id => $name) {
                $rows[] = [(int)$id, $name, ''];
            }
        }
    }
} catch (PDOException $e) {
    err_log($e->getMessage());
    echo alertBox('danger', 'Could not load the enrollments. Please try again.');
    return;
}

$enrolledShown = count(array_filter($rows, fn($r) => isset($enrolled[$r[0]])));

// ---- student search (By student only) ----
// A student matches when every word typed appears in their first or last name.
// Case is ignored and the order of the words doesn't matter, so "juan santos",
// "santos juan" and "Santos, Juan" all find the same student.
$maxResults     = 15;     // never list more than this many matches
$searchWords    = preg_split('/[\s,]+/', mb_strtolower($search), -1, PREG_SPLIT_NO_EMPTY);
$isSearching    = $by === 'student' && $searchWords !== [];
$searchResults  = [];     // the matching students, listed under the search box
$hasMoreResults = false;  // true when there were more matches than $maxResults

if ($isSearching) {
    foreach ($students as $candidate) {
        $fullName = mb_strtolower($candidate['fname'] . ' ' . $candidate['lname']);

        // check every typed word against the name
        $matchesAllWords = true;
        foreach ($searchWords as $word) {
            if (mb_strpos($fullName, $word) === false) {
                $matchesAllWords = false;
                break;
            }
        }
        if (!$matchesAllWords) {
            continue;   // not a match, look at the next student
        }

        // it is a match, but stop once the list is full
        if (count($searchResults) >= $maxResults) {
            $hasMoreResults = true;
            break;
        }
        $searchResults[] = $candidate;
    }
}
?>

<div hx-boost="true" hx-target="#main" hx-push-url="false" hx-indicator="#global-spinner, #main">

    <h3>Enrollment <small class="text-muted fs-6"><?= htmlspecialchars($year['name']) ?></small></h3>

    <ul class="nav nav-pills mb-3">
        <li class="nav-item"><a class="nav-link <?= $by === 'subject' ? 'active' : '' ?>" href="<?= $self ?>?by=subject">By subject</a></li>
        <li class="nav-item"><a class="nav-link <?= $by === 'student' ? 'active' : '' ?>" href="<?= $self ?>?by=student">By student</a></li>
    </ul>

    <div id="message"><?= $message ?? '' ?></div>

    <?php if (!$subjects): ?>

        <?= alertBox('info', 'There are no active subjects in this school year yet. Add them in Subjects first.') ?>

    <?php elseif (!$students): ?>

        <?= alertBox('info', 'There are no students in this school year yet. Add them in Students first.') ?>

    <?php else: ?>

        <?php if ($by === 'subject'): ?>

            <!-- PICK (reloads when a dropdown changes; the button is for when JavaScript is off) -->
            <form method="get" action="<?= $self ?>" hx-get="<?= $self ?>" hx-trigger="change, submit" class="mb-4 d-flex gap-2 flex-wrap">
                <input type="hidden" name="by" value="subject">

                <select name="subject_id" class="form-select w-auto" aria-label="Subject">
                    <option value="">-- Select a subject --</option>
                    <?php foreach ($subjects as $id => $name): ?>
                        <option value="<?= $id ?>" <?= $id === $subjectId ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="grade_level" class="form-select w-auto" aria-label="Grade level">
                    <option value="">All grade levels</option>
                    <?php foreach ($gradeLevels as $gl): ?>
                        <option value="<?= htmlspecialchars($gl) ?>" <?= $gl === $gradeLevel ? 'selected' : '' ?>><?= htmlspecialchars($gl) ?></option>
                    <?php endforeach; ?>
                </select>

                <button class="btn btn-outline-primary">Open</button>
            </form>

        <?php else: ?>

            <!-- SEARCH A STUDENT
                 hx-trigger: search after a 300 ms pause in typing, or when Enter is pressed
                 hx-target / hx-select / hx-swap: replace only the #student-results list below, so the cursor stays in the box
                 hx-sync: if you keep typing, the older request is cancelled
                 Without JavaScript the Search button submits the form as a normal page request. -->
            <form method="get" action="<?= $self ?>" class="mb-3"
                  hx-get="<?= $self ?>" hx-trigger="input delay:300ms, submit"
                  hx-target="#student-results" hx-select="#student-results" hx-swap="outerHTML"
                  hx-sync="this:replace" hx-indicator="#global-spinner">
                <input type="hidden" name="by" value="student">
                <!-- keeps the chosen student highlighted in the list while you type -->
                <input type="hidden" name="student_id" value="<?= $studentId ?>">

                <div class="input-group" style="max-width: 28rem;">
                    <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control"
                           placeholder="Search student by name..." aria-label="Search student"
                           autocomplete="off" <?= $student ? '' : 'autofocus' ?>>
                    <button class="btn btn-outline-primary">Search</button>
                </div>
            </form>

            <!-- the students that match what was typed -->
            <div id="student-results">

                <?php if ($isSearching && !$searchResults): ?>
                    <p class="text-muted">No student matches &ldquo;<?= htmlspecialchars($search) ?>&rdquo;.</p>
                <?php endif; ?>

                <?php if ($searchResults): ?>
                    <div class="list-group mb-2" style="max-width: 28rem;">
                        <?php foreach ($searchResults as $result): ?>
                            <?php
                            $isSelected = (int)$result['id'] === $studentId;
                            $resultUrl  = $self . '?' . http_build_query(['by' => 'student', 'student_id' => $result['id'], 'search' => $search]);
                            ?>
                            <a href="<?= htmlspecialchars($resultUrl) ?>"
                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $isSelected ? 'active' : '' ?>"
                               <?= $isSelected ? 'aria-current="true"' : '' ?>>
                                <span><?= htmlspecialchars($result['lname'] . ', ' . $result['fname']) ?></span>
                                <?php if (($result['grade_level'] ?? '') !== ''): ?>
                                    <small class="<?= $isSelected ? '' : 'text-muted' ?>"><?= htmlspecialchars($result['grade_level']) ?></small>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($hasMoreResults): ?>
                        <p class="text-muted small">Showing the first <?= $maxResults ?> matches. Type more of the name to narrow it down.</p>
                    <?php endif; ?>
                <?php endif; ?>

            </div>

        <?php endif; ?>

        <?php if ($by === 'subject' && $subject === null): ?>

            <p class="text-muted">Select a subject to choose which students take it.</p>

        <?php elseif ($by === 'student' && !$student): ?>

            <p class="text-muted">Search for a student to choose which subjects they take.</p>

        <?php elseif (!$rows): ?>

            <p class="text-muted">No students match this grade level.</p>

        <?php else: ?>

            <h5 class="mb-1">
                <?= $by === 'subject'
                    ? 'Subject: ' . htmlspecialchars($subject)
                    : 'Student: ' . htmlspecialchars($student['fname'] . ' ' . $student['lname']) ?>
            </h5>
            <p class="text-muted small mb-3">
                <?= $enrolledShown ?> of <?= count($rows) ?> <?= $by === 'subject' ? 'students' : 'subjects' ?> enrolled.
                Tick to enroll, untick to remove.
            </p>

            <form hx-post="<?= HANDLERS_URL . 'enrollmentProcess.php' ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="by" value="<?= $by ?>">
                <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
                <input type="hidden" name="student_id" value="<?= $studentId ?>">
                <input type="hidden" name="grade_level" value="<?= htmlspecialchars($gradeLevel) ?>">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">

                <table class="table table-sm w-auto align-middle">
                    <tr>
                        <th>
                            <!-- ticks or unticks every box of this table in the browser, no request is sent -->
                            <input type="checkbox" class="form-check-input" aria-label="Select all"
                                   onclick="this.closest('table').querySelectorAll('.enroll-box').forEach(b => b.checked = this.checked)">
                        </th>
                        <th><?= $by === 'subject' ? 'Student' : 'Subject' ?></th>
                        <?php if ($by === 'subject'): ?><th>Grade level</th><?php endif; ?>
                        <th></th>
                    </tr>

                    <?php foreach ($rows as [$id, $name, $level]): ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input enroll-box" name="enrolled[]" value="<?= $id ?>"
                                       aria-label="<?= htmlspecialchars($name) ?>" <?= isset($enrolled[$id]) ? 'checked' : '' ?>>
                                <input type="hidden" name="shown[]" value="<?= $id ?>">
                            </td>
                            <td><?= htmlspecialchars($name) ?></td>
                            <?php if ($by === 'subject'): ?><td><?= htmlspecialchars($level) ?></td><?php endif; ?>
                            <td>
                                <?php if (isset($graded[$id])): ?>
                                    <span class="badge text-bg-secondary">has grades</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <button class="btn btn-primary">Save enrollment</button>
            </form>

        <?php endif; ?>

    <?php endif; ?>

</div>
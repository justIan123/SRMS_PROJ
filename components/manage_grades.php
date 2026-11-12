<?php
require_once("../config/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");
require_once(FUNCTIONS_PATH . DS . "gradeFunctions.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$self = COMPONENTS_URL . 'manage_grades.php';
$myId = (int)($_SESSION['user_id'] ?? 0);   // decides which subjects are shown (admin = all)

// ---- which student is open, and the search that narrows the dropdown ----
$studentId = (int)($_GET['student_id'] ?? 0);
$search    = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';

try {
    // Everything belongs to the current school year
    $year = CurrentSchoolYear($pdo);
    if (!$year) {
        echo alertBox('warning', 'No school year is running. Start one in School Years first.');
        return;
    }

    // the subjects this user may grade, and the students enrolled in at least one of them
    $gradable = GradableSubjects($pdo, (int)$year['id'], $myId);
    $students = GradableStudents($pdo, (int)$year['id'], array_keys($gradable), $search);

    // the open student must be one of them (an id from the URL is never trusted)
    $student = null;
    foreach ($students as $s) {
        if ((int)$s['id'] === $studentId) {
            $student = $s;
            break;
        }
    }

    // a search with exactly one match and no student open yet: open that student right away
    if (!$student && $search !== '' && count($students) === 1) {
        $student   = $students[0];
        $studentId = (int)$student['id'];
    }

    $subjects = [];   // subject id => name (graded by this user AND the student is enrolled in it)
    $grades   = [];   // subject id => the saved scores of the open student
    if ($student) {
        $stmt = $pdo->prepare("SELECT subject_id FROM enrollments WHERE student_id = :sid");
        $stmt->execute([':sid' => $student['id']]);
        $enrolledIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $subjects    = array_intersect_key($gradable, array_flip($enrolledIds));

        if ($subjects) {
            $stmt = $pdo->prepare("SELECT subject_id, " . implode(', ', array_keys(GRADE_COLUMNS)) . "
                                   FROM grades
                                   WHERE student_id = :sid");
            $stmt->execute([':sid' => $student['id']]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
                $grades[(int)$g['subject_id']] = $g;
            }
        }
    }
} catch (PDOException $e) {
    err_log($e->getMessage());
    echo alertBox('danger', 'Could not load the grades. Please try again.');
    return;
}

// $formValues is set by gradeProcess.php when a save failed, so what the teacher typed is not lost
$formValues = $formValues ?? null;
?>

<div hx-boost="true" hx-target="#main" hx-push-url="false" hx-indicator="#global-spinner, #main">

    <h3>Grades <small class="text-muted fs-6"><?= htmlspecialchars($year['name']) ?></small></h3>
    <?= GradeNav('student') ?>
    <div id="message"><?= $message ?? '' ?></div>

    <?php if (!$gradable): ?>

        <?= alertBox('info', 'You have no subjects to grade in this school year. Ask the admin to assign subjects to you.') ?>

    <?php elseif (!$students && $search === ''): ?>

        <?= alertBox('info', 'No students are enrolled in your subjects yet. Enroll them in Enrollment first.') ?>

    <?php else: ?>

        <div class="d-flex flex-wrap gap-3 mb-4">

            <!-- SEARCH: narrows the dropdown to the students that match (and closes the open student, you pick again) -->
            <form method="get" action="<?= $self ?>" class="d-flex gap-2">
                <input type="text" name="search" class="form-control w-auto" value="<?= htmlspecialchars($search) ?>" placeholder="Search name or grade level...">
                <button class="btn btn-outline-primary">Search</button>
                <?php if ($search !== ''): ?>
                    <a href="<?= $self . ($studentId ? '?student_id=' . $studentId : '') ?>" class="btn btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </form>

            <!-- PICK A STUDENT (reloads when the dropdown changes; the button is for when JavaScript is off) -->
            <?php if ($students): ?>
                <form method="get" action="<?= $self ?>" hx-get="<?= $self ?>" hx-trigger="change, submit" class="d-flex gap-2">
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                    <select name="student_id" class="form-select w-auto" aria-label="Student">
                        <option value="">-- Select a student<?= $search !== '' ? ' (' . count($students) . ' found)' : '' ?> --</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= (int)$s['id'] === $studentId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['lname'] . ', ' . $s['fname']) ?><?= ($s['grade_level'] ?? '') !== '' ? ' (' . htmlspecialchars($s['grade_level']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline-primary">Open</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (!$students): ?>

            <?= alertBox('info', 'No student matches "' . htmlspecialchars($search) . '".') ?>

        <?php elseif (!$student): ?>

            <p class="text-muted">Select a student to enter their grades.</p>

        <?php elseif (!$subjects): ?>

            <?= alertBox('info', 'This student is not enrolled in any subject you grade.') ?>

        <?php else: ?>

            <h5 class="mb-3">
                Student: <?= htmlspecialchars($student['fname'] . ' ' . $student['lname']) ?>
                <?php if (($student['grade_level'] ?? '') !== ''): ?>
                    <small class="text-muted fs-6"><?= htmlspecialchars($student['grade_level']) ?></small>
                <?php endif; ?>
            </h5>

            <!-- One form for the whole table: type every score, then one Save -->
            <form hx-post="<?= HANDLERS_URL . 'gradeProcess.php' ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <tr>
                            <th>Subjects</th>
                            <?php foreach (GRADE_COLUMNS as $label): ?>
                                <th><?= htmlspecialchars($label) ?></th>
                            <?php endforeach; ?>
                            <th>Weighted</th>
                        </tr>

                        <?php foreach ($subjects as $subjectId => $subjectName): ?>
                            <?php
                            $saved = $grades[$subjectId] ?? [];
                            $final = GradeFinal($saved);
                            ?>
                            <tr>
                                <td class="text-nowrap"><?= htmlspecialchars($subjectName) ?></td>
                                <?php foreach (GRADE_COLUMNS as $col => $label): ?>
                                    <?php $value = $formValues[$subjectId][$col] ?? ($saved[$col] ?? null); ?>
                                    <td>
                                        <input type="number"
                                               name="grades[<?= $subjectId ?>][<?= $col ?>]"
                                               value="<?= htmlspecialchars(FormatScore($value)) ?>"
                                               min="0" max="<?= GRADE_MAX ?>" step="0.01"
                                               class="form-control form-control-sm"
                                               style="min-width: 4.5rem;"
                                               aria-label="<?= htmlspecialchars($subjectName . ' - ' . $label) ?>">
                                    </td>
                                <?php endforeach; ?>
                                <td class="text-end fw-semibold"><?= $final === null ? '&mdash;' : number_format($final, 1) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>

                <p class="text-muted small mb-3">
                    Scores are 0&ndash;<?= GRADE_MAX ?>. Leave a box empty if it is not graded yet.
                    Weighted = <?= GradeWeightsText() ?>, based on the scores entered so far.
                </p>

                <button class="btn btn-primary">Save grades</button>
                <?php if (count($students) > 1): ?>
                    <button name="next" value="1" class="btn btn-outline-primary">Save &amp; next student</button>
                <?php endif; ?>
            </form>

        <?php endif; ?>

    <?php endif; ?>

</div>
<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");

$self = COMPONENTS_URL . 'manage_enrollments.php';

// ---- state of the page ----
// by = "subject": pick a subject, tick the students (can be narrowed to one grade level)
// by = "student": pick a student, tick the subjects
$by         = ($_GET['by'] ?? '') === 'student' ? 'student' : 'subject';
$subjectId  = (int)($_GET['subject_id'] ?? 0);
$studentId  = (int)($_GET['student_id'] ?? 0);
$gradeLevel = trim($_GET['grade_level'] ?? '');

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
?>

<!-- replace the old js alert with sweetalert -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- nalimutan kko na kung saan to kinuha ko -->
<script>
document.body.addEventListener('htmx:confirm', function (evt) {
    evt.preventDefault();

    Swal.fire({
        title: 'Confirm',
        text: evt.detail.question,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes',
        cancelButtonText: 'No'
    }).then((result) => {
        if (result.isConfirmed) {
            evt.detail.issueRequest(true);
        }
    });
});
</script>

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

        <!-- PICK (reloads when a dropdown changes; the button is for when JavaScript is off) -->
        <form method="get" action="<?= $self ?>" hx-get="<?= $self ?>" hx-trigger="change, submit" class="mb-4 d-flex gap-2 flex-wrap">
            <input type="hidden" name="by" value="<?= $by ?>">

            <?php if ($by === 'subject'): ?>
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
            <?php else: ?>
                <select name="student_id" class="form-select w-auto" aria-label="Student">
                    <option value="">-- Select a student --</option>
                    <?php foreach ($students as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= (int)$s['id'] === $studentId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['lname'] . ', ' . $s['fname']) ?><?= ($s['grade_level'] ?? '') !== '' ? ' (' . htmlspecialchars($s['grade_level']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <button class="btn btn-outline-primary">Open</button>
        </form>

        <?php if ($by === 'subject' && $subject === null): ?>

            <p class="text-muted">Select a subject to choose which students take it.</p>

        <?php elseif ($by === 'student' && !$student): ?>

            <p class="text-muted">Select a student to choose which subjects they take.</p>

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
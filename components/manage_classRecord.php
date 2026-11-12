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

$self = COMPONENTS_URL . 'manage_classRecord.php';
$myId = (int)($_SESSION['user_id'] ?? 0);   // decides which subjects are shown (admin = all)

// ---- which subject is open ----
$subjectId = (int)($_GET['subject_id'] ?? 0);

try {
    // Everything belongs to the current school year
    $year = CurrentSchoolYear($pdo);
    if (!$year) {
        echo alertBox('warning', 'No school year is running. Start one in School Years first.');
        return;
    }

    // the subjects this user may grade; the open one must be one of them (an id from the URL is never trusted)
    $gradable    = GradableSubjects($pdo, (int)$year['id'], $myId);
    $subjectName = $gradable[$subjectId] ?? null;

    $students = [];   // the students enrolled in the open subject
    $grades   = [];   // student id => the saved scores in the open subject
    if ($subjectName !== null) {
        $stmt = $pdo->prepare("SELECT s.id, s.fname, s.lname, s.grade_level
                               FROM students s
                               JOIN enrollments e ON e.student_id = s.id
                               WHERE e.subject_id = :sub AND s.school_year_id = :year
                               ORDER BY s.lname, s.fname, s.id");
        $stmt->execute([':sub' => $subjectId, ':year' => $year['id']]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($students) {
            $stmt = $pdo->prepare("SELECT student_id, " . implode(', ', array_keys(GRADE_COLUMNS)) . "
                                   FROM grades
                                   WHERE subject_id = :sub");
            $stmt->execute([':sub' => $subjectId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
                $grades[(int)$g['student_id']] = $g;
            }
        }
    }
} catch (PDOException $e) {
    err_log($e->getMessage());
    echo alertBox('danger', 'Could not load the class record. Please try again.');
    return;
}

// $formValues is set by classRecordProcess.php when a save failed, so what the teacher typed is not lost
$formValues = $formValues ?? null;
?>

<div hx-boost="true" hx-target="#main" hx-push-url="false" hx-indicator="#global-spinner, #main">


    <h3>Grades <small class="text-muted fs-6"><?= htmlspecialchars($year['name']) ?></small></h3>
    <?= GradeNav('subject') ?>
    <div id="message"><?= $message ?? '' ?></div>

    <?php if (!$gradable): ?>

        <?= alertBox('info', 'You have no subjects to grade in this school year. Ask the admin to assign subjects to you.') ?>

    <?php else: ?>

        <!-- PICK A SUBJECT (reloads when the dropdown changes; the button is for when JavaScript is off) -->
        <form method="get" action="<?= $self ?>" hx-get="<?= $self ?>" hx-trigger="change, submit" class="mb-4 d-flex gap-2">
            <select name="subject_id" class="form-select w-auto" aria-label="Subject">
                <option value="">-- Select a subject --</option>
                <?php foreach ($gradable as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $id === $subjectId ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-outline-primary">Open</button>
        </form>

        <?php if ($subjectName === null): ?>

            <p class="text-muted">Select a subject to enter the grades of its whole class.</p>

        <?php elseif (!$students): ?>

            <?= alertBox('info', 'No students are enrolled in ' . htmlspecialchars($subjectName) . ' yet. Enroll them in Enrollment first.') ?>

        <?php else: ?>

            <h5 class="mb-3">
                Subject: <?= htmlspecialchars($subjectName) ?>
                <small class="text-muted fs-6"><?= count($students) ?> student<?= count($students) === 1 ? '' : 's' ?></small>
            </h5>

            <!-- SEARCH: hides the rows that do not match while you type. It runs in the browser only,
                 so scores you already typed are not lost, and Save still saves every row. -->
            <div class="d-flex align-items-center gap-3 mb-3">
                <input type="search" id="recordSearch" class="form-control w-auto" autocomplete="off"
                       placeholder="Search student..." aria-label="Search students"
                       oninput="filterRecord(this)">
                <small class="text-muted" id="recordCount"></small>
            </div>

            <!-- One form for the whole class: type every score, then one Save -->
            <form id="recordForm" hx-post="<?= HANDLERS_URL . 'classRecordProcess.php' ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="subject_id" value="<?= $subjectId ?>">

                <style>
                    /* keep the student's name in view while scrolling sideways */
                    .record-table th:first-child, .record-table td:first-child {
                        position: sticky; left: 0; z-index: 1; background: var(--bs-body-bg);
                    }
                </style>
                <div class="table-responsive">
                    <table class="table table-sm align-middle record-table">
                        <tr>
                            <th>Student</th>
                            <?php foreach (GRADE_COLUMNS as $label): ?>
                                <th><?= htmlspecialchars($label) ?></th>
                            <?php endforeach; ?>
                            <th>Weighted</th>
                        </tr>

                        <?php foreach ($students as $s): ?>
                            <?php
                            $sid   = (int)$s['id'];
                            $saved = $grades[$sid] ?? [];
                            $final = GradeFinal($saved);
                            $who   = $s['lname'] . ', ' . $s['fname'];
                            ?>
                            <tr class="record-row" data-name="<?= htmlspecialchars($s['lname'] . ' ' . $s['fname'] . ' ' . ($s['grade_level'] ?? '')) ?>">
                                <td class="text-nowrap">
                                    <?= htmlspecialchars($who) ?>
                                    <?php if (($s['grade_level'] ?? '') !== ''): ?>
                                        <small class="text-muted"><?= htmlspecialchars($s['grade_level']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <?php foreach (GRADE_COLUMNS as $col => $label): ?>
                                    <?php $value = $formValues[$sid][$col] ?? ($saved[$col] ?? null); ?>
                                    <td>
                                        <input type="number"
                                               name="grades[<?= $sid ?>][<?= $col ?>]"
                                               value="<?= htmlspecialchars(FormatScore($value)) ?>"
                                               min="0" max="<?= GRADE_MAX ?>" step="0.01"
                                               class="form-control form-control-sm"
                                               style="min-width: 4.5rem;"
                                               aria-label="<?= htmlspecialchars($who . ' - ' . $label) ?>">
                                    </td>
                                <?php endforeach; ?>
                                <td class="text-end fw-semibold"><?= $final === null ? '&mdash;' : number_format($final, 1) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <tr id="recordEmpty" class="d-none">
                            <td colspan="<?= count(GRADE_COLUMNS) + 2 ?>" class="text-center text-muted">No student matches your search.</td>
                        </tr>
                    </table>
                </div>

                <p class="text-muted small mb-3">
                    Scores are 0&ndash;<?= GRADE_MAX ?>. Leave a box empty if it is not graded yet.
                    Weighted = <?= GradeWeightsText() ?>, based on the scores entered so far.
                </p>

                <button class="btn btn-primary">Save grades</button>
            </form>

            <script>
                // Search box: show only the rows where every typed word is in the student's name or grade level.
                function filterRecord(input) {
                    const words = input.value.toLowerCase().split(/[\s,]+/).filter(Boolean);
                    const rows  = document.querySelectorAll('.record-row');
                    let shown = 0;

                    rows.forEach(row => {
                        const name  = row.dataset.name.toLowerCase();
                        const match = words.every(w => name.includes(w));
                        row.classList.toggle('d-none', !match);
                        if (match) shown++;
                    });

                    document.getElementById('recordEmpty').classList.toggle('d-none', shown > 0);
                    document.getElementById('recordCount').textContent =
                        words.length ? shown + ' of ' + rows.length + ' shown' : '';

                    // A box that is hidden and has a wrong value would make the browser block Save without
                    // saying why. While a filter is on, let the server check the scores instead (it names the student).
                    document.getElementById('recordForm').noValidate = words.length > 0;
                }
            </script>

        <?php endif; ?>

    <?php endif; ?>

</div>
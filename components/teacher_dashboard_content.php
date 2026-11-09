<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");
require_once(FUNCTIONS_PATH . DS . "gradeFunctions.php");
require_once(FUNCTIONS_PATH . DS . "testFunctions.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$myId = (int)CurrentUserId();   // decides which subjects are "mine" (admin = all)

// A weighted grade below this counts as "below passing". Define PASSING_GRADE somewhere central to change it.
$passing = defined('PASSING_GRADE') ? (float)PASSING_GRADE : 75.0;

try {
    // Everything belongs to the current school year
    $year = CurrentSchoolYear($pdo);
    if (!$year) {
        echo alertBox('warning', 'No school year is running. Start one in School Years first.');
        return;
    }

    $gradable  = GradableSubjects($pdo, (int)$year['id'], $myId);   // subject id => name
    $isTeacher = IsTeacher($pdo, $myId);                            // tests belong to teachers only

    // one summary per subject
    $stats = [];
    foreach ($gradable as $id => $name) {
        $stats[$id] = ['name' => $name, 'enrolled' => 0, 'passed' => 0, 'below' => 0, 'ungraded' => 0, 'sum' => 0.0];
    }

    $studentSet  = [];   // student id => true (to count each student once, however many subjects)
    $watch       = [];   // records below passing: the ones to look at first
    $cellsFilled = 0;    // score boxes with a value
    $cellsTotal  = 0;    // score boxes there are (records x score columns)

    if ($gradable) {
        $allIds    = implode(', ', array_map('intval', array_keys($gradable)));   // integers only
        $scoreCols = implode(', ', array_map(fn($c) => "g.$c", array_keys(GRADE_COLUMNS)));
        $colCount  = count(GRADE_COLUMNS);

        // every (student, subject) record of mine; a record without scores yet comes back with empty scores
        $stmt = $pdo->prepare("SELECT e.student_id, e.subject_id, s.fname, s.lname, s.grade_level, $scoreCols
                               FROM enrollments e
                               JOIN students s ON s.id = e.student_id
                               LEFT JOIN grades g ON g.student_id = e.student_id AND g.subject_id = e.subject_id
                               WHERE s.school_year_id = :year AND e.subject_id IN ($allIds)");
        $stmt->execute([':year' => $year['id']]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $subId = (int)$r['subject_id'];
            if (!isset($stats[$subId])) {
                continue;
            }

            $studentSet[(int)$r['student_id']] = true;
            $stats[$subId]['enrolled']++;

            $filled = 0;
            foreach (array_keys(GRADE_COLUMNS) as $col) {
                if ($r[$col] !== null && $r[$col] !== '') {
                    $filled++;
                }
            }
            $cellsFilled += $filled;
            $cellsTotal  += $colCount;

            $final = GradeFinal($r);
            if ($final === null) {
                $stats[$subId]['ungraded']++;
            } else {
                $stats[$subId]['sum'] += $final;
                if ($final < $passing) {
                    $stats[$subId]['below']++;
                    $watch[] = [
                        'student_id'  => (int)$r['student_id'],
                        'name'        => $r['lname'] . ', ' . $r['fname'],
                        'grade_level' => $r['grade_level'] ?? '',
                        'subject'     => $stats[$subId]['name'],
                        'final'       => $final,
                    ];
                } else {
                    $stats[$subId]['passed']++;
                }
            }
        }

        // lowest first, and only the first few are shown
        usort($watch, fn($a, $b) => $a['final'] <=> $b['final']);
    }

    // my tests (teachers only)
    $tests      = [];
    $testCounts = ['draft' => 0, 'published' => 0];
    if ($isTeacher) {
        $stmt = $pdo->prepare("SELECT t.id, t.title, t.is_published, t.created_at, s.subject_name,
                                      (SELECT COUNT(*) FROM questions q WHERE q.test_id = t.id) AS question_count
                               FROM tests t
                               LEFT JOIN subjects s ON s.id = t.subject_id
                               WHERE t.school_year_id = :year AND t.teacher_id = :teacher
                               ORDER BY t.created_at DESC");
        $stmt->execute([':year' => $year['id'], ':teacher' => $myId]);
        $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tests as $t) {
            $t['is_published'] ? $testCounts['published']++ : $testCounts['draft']++;
        }
    }
} catch (PDOException $e) {
    err_log($e->getMessage());
    echo alertBox('danger', 'Could not load the dashboard. Please try again.');
    return;
}

// ---- totals for the cards ----
$totalStudents = count($studentSet);
$totalBelow    = array_sum(array_column($stats, 'below'));
$totalUngraded = array_sum(array_column($stats, 'ungraded'));
$completeness  = $cellsTotal ? (int)round($cellsFilled / $cellsTotal * 100) : 0;

$myStudentsUrl = COMPONENTS_URL . 'manage_myStudents.php';
$testsUrl      = COMPONENTS_URL . 'manage_tests.php';
?>

<div hx-boost="true" hx-target="#main" hx-push-url="false" hx-indicator="#global-spinner, #main">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h3 class="mb-0">Dashboard <small class="text-muted fs-6"><?= htmlspecialchars($year['name']) ?></small></h3>
        <div class="d-flex gap-2">
            <a href="<?= $myStudentsUrl ?>" class="btn btn-primary">Enter grades</a>
            <?php if ($isTeacher): ?>
                <a href="<?= $testsUrl ?>" class="btn btn-outline-primary">My tests</a>
            <?php endif; ?>
        </div>
    </div>

    <div id="message"><?= $message ?? '' ?></div>

    <?php if (!$gradable): ?>

        <?= alertBox('info', 'You have no subjects in this school year yet. Ask the admin to assign subjects to you.') ?>

    <?php else: ?>

        <!-- CARDS -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted small">My subjects</div>
                    <div class="fs-2 fw-semibold"><?= count($gradable) ?></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted small">My students</div>
                    <div class="fs-2 fw-semibold"><?= $totalStudents ?></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted small">Scores entered</div>
                    <div class="fs-2 fw-semibold"><?= $completeness ?>%</div>
                    <div class="progress" role="progressbar" aria-label="Scores entered" aria-valuenow="<?= $completeness ?>" aria-valuemin="0" aria-valuemax="100" style="height: 6px;">
                        <div class="progress-bar" style="width: <?= $completeness ?>%"></div>
                    </div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted small">Below passing (<?= rtrim(rtrim(number_format($passing, 1), '0'), '.') ?>)</div>
                    <div class="fs-2 fw-semibold <?= $totalBelow ? 'text-danger' : '' ?>"><?= $totalBelow ?></div>
                </div></div>
            </div>
        </div>

        <div class="row g-3 mb-4">

            <!-- SUBJECTS -->
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header fw-semibold">My subjects</div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <tr>
                                <th>Subject</th>
                                <th class="text-end">Students</th>
                                <th class="text-end">Average</th>
                                <th style="min-width: 10rem;">Passing / below / no scores</th>
                                <th></th>
                            </tr>
                            <?php foreach ($stats as $id => $s): ?>
                                <?php
                                $n       = $s['enrolled'];
                                $graded  = $s['passed'] + $s['below'];
                                $average = $graded ? $s['sum'] / $graded : null;
                                $pPass   = $n ? $s['passed']   / $n * 100 : 0;
                                $pBelow  = $n ? $s['below']    / $n * 100 : 0;
                                $pNone   = $n ? $s['ungraded'] / $n * 100 : 0;
                                ?>
                                <tr>
                                    <td class="text-nowrap"><?= htmlspecialchars($s['name']) ?></td>
                                    <td class="text-end"><?= $n ?></td>
                                    <td class="text-end"><?= $average === null ? '&mdash;' : number_format($average, 1) ?></td>
                                    <td>
                                        <?php if ($n): ?>
                                            <div class="progress-stacked" style="height: 8px;">
                                                <div class="progress" role="progressbar" aria-label="Passing" style="width: <?= $pPass ?>%"><div class="progress-bar bg-success"></div></div>
                                                <div class="progress" role="progressbar" aria-label="Below passing" style="width: <?= $pBelow ?>%"><div class="progress-bar bg-danger"></div></div>
                                                <div class="progress" role="progressbar" aria-label="No scores" style="width: <?= $pNone ?>%"><div class="progress-bar bg-secondary"></div></div>
                                            </div>
                                            <div class="small text-muted"><?= $s['passed'] ?> / <?= $s['below'] ?> / <?= $s['ungraded'] ?></div>
                                        <?php else: ?>
                                            <span class="text-muted small">No students enrolled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="<?= COMPONENTS_URL ?>manage_classRecord.php?subject_id=<?= (int)$id ?>" class="btn btn-sm btn-outline-primary">Class record</a>
                                        <a href="<?= $myStudentsUrl ?>?subject_id=<?= (int)$id ?>" class="btn btn-sm btn-primary">Grade</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>

            <!-- NEEDS ATTENTION -->
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Needs attention</div>
                    <ul class="list-group list-group-flush">
                        <?php $anything = false; ?>

                        <?php if ($totalUngraded): ?>
                            <?php $anything = true; ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?= $totalUngraded ?> record<?= $totalUngraded === 1 ? '' : 's' ?> with no scores yet</span>
                                <a href="<?= $myStudentsUrl ?>" class="btn btn-sm btn-outline-primary">Open</a>
                            </li>
                        <?php endif; ?>

                        <?php if ($totalBelow): ?>
                            <?php $anything = true; ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?= $totalBelow ?> record<?= $totalBelow === 1 ? '' : 's' ?> below passing</span>
                                <a href="#lowestGrades" class="btn btn-sm btn-outline-primary" hx-boost="false">See list</a>
                            </li>
                        <?php endif; ?>

                        <?php if ($isTeacher && $testCounts['draft']): ?>
                            <?php $anything = true; ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?= $testCounts['draft'] ?> draft test<?= $testCounts['draft'] === 1 ? '' : 's' ?> not published</span>
                                <a href="<?= $testsUrl ?>" class="btn btn-sm btn-outline-primary">Open</a>
                            </li>
                        <?php endif; ?>

                        <?php if (!$anything): ?>
                            <li class="list-group-item text-muted">You are all caught up.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="row g-3">

            <!-- LOWEST GRADES -->
            <div class="col-lg-7" id="lowestGrades">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Lowest weighted grades</div>
                    <?php if (!$watch): ?>
                        <div class="card-body text-muted">
                            <?= $totalUngraded === array_sum(array_column($stats, 'enrolled'))
                                ? 'No scores entered yet, so there is nothing to compare.'
                                : 'Nobody is below ' . htmlspecialchars(rtrim(rtrim(number_format($passing, 1), '0'), '.')) . '.' ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <tr>
                                    <th>Student</th>
                                    <th>Subject</th>
                                    <th class="text-end">Weighted</th>
                                    <th></th>
                                </tr>
                                <?php foreach (array_slice($watch, 0, 8) as $w): ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($w['name']) ?>
                                            <?php if ($w['grade_level'] !== ''): ?>
                                                <small class="text-muted"><?= htmlspecialchars($w['grade_level']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($w['subject']) ?></td>
                                        <td class="text-end fw-semibold text-danger"><?= number_format($w['final'], 1) ?></td>
                                        <td class="text-end">
                                            <a href="<?= COMPONENTS_URL ?>manage_grades.php?student_id=<?= $w['student_id'] ?>" class="btn btn-sm btn-primary">Grade</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                        <?php if (count($watch) > 8): ?>
                            <div class="card-footer text-muted small">Showing 8 of <?= count($watch) ?>.</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RECENT TESTS -->
            <?php if ($isTeacher): ?>
                <div class="col-lg-5">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="fw-semibold">Recent tests</span>
                            <span class="small text-muted"><?= $testCounts['published'] ?> published &middot; <?= $testCounts['draft'] ?> draft</span>
                        </div>
                        <?php if (!$tests): ?>
                            <div class="card-body text-muted">
                                No tests yet. <a href="<?= $testsUrl ?>">Create one</a>.
                            </div>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach (array_slice($tests, 0, 5) as $t): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                                        <div>
                                            <a href="<?= COMPONENTS_URL ?>manage_questions.php?test=<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['title']) ?></a>
                                            <div class="small text-muted">
                                                <?= htmlspecialchars($t['subject_name'] ?? '') ?> &middot;
                                                <?= (int)$t['question_count'] ?> question<?= (int)$t['question_count'] === 1 ? '' : 's' ?>
                                            </div>
                                        </div>
                                        <?php if ($t['is_published']): ?>
                                            <span class="badge text-bg-success">Published</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-secondary">Draft</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>

        <p class="text-muted small mt-3">
            Weighted = <?= GradeWeightsText() ?>, based on the scores entered so far.
            Below passing means a weighted grade under <?= htmlspecialchars(rtrim(rtrim(number_format($passing, 1), '0'), '.')) ?>.
        </p>

    <?php endif; ?>

</div>
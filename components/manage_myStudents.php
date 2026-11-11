<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");
require_once(FUNCTIONS_PATH . DS . "gradeFunctions.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$self = COMPONENTS_URL . 'manage_myStudents.php';
$myId = (int)($_SESSION['user_id'] ?? 0);   // decides which subjects are "mine" (admin = all)

// ---- search ----
$search = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';

// ---- sort (only a key of $headers can ever reach the SQL) ----
$headers = ['lname' => 'Last name', 'fname' => 'First name', 'grade_level' => 'Grade level'];
$sortIn  = $_GET['sort'] ?? '';
$sort    = is_string($sortIn) && array_key_exists($sortIn, $headers) ? $sortIn : 'lname';
$dir     = ($_GET['dir'] ?? '') === 'DESC' ? 'DESC' : 'ASC';
$flip    = $dir === 'ASC' ? 'DESC' : 'ASC';

$perPage   = min(100, max(1, (int)($_GET['PerPageLimit'] ?? 20)));
$subjectId = (int)($_GET['subject_id'] ?? 0);   // 0 = all of my subjects

try {
    // Everything belongs to the current school year
    $year = CurrentSchoolYear($pdo);
    if (!$year) {
        echo alertBox('warning', 'No school year is running. Start one in School Years first.');
        return;
    }

    // my subjects: the ones assigned to this teacher (an admin gets all of them)
    $gradable  = GradableSubjects($pdo, (int)$year['id'], $myId);   // subject id => name
    $subjectId = isset($gradable[$subjectId]) ? $subjectId : 0;     // an id from the URL is only used if it is one of mine

    $counts = [];   // subject id => how many students are enrolled
    $total  = 0;
    $rows   = [];   // the students shown on this page
    $mine   = [];   // student id => subject id => the saved scores (to work out the weighted grade)
    $page   = 1;
    $totalPages = 1;

    if ($gradable) {
        $allIds = implode(', ', array_map('intval', array_keys($gradable)));   // integers only

        $stmt = $pdo->prepare("SELECT e.subject_id, COUNT(*)
                               FROM enrollments e
                               JOIN students s ON s.id = e.student_id
                               WHERE s.school_year_id = :year AND e.subject_id IN ($allIds)
                               GROUP BY e.subject_id");
        $stmt->execute([':year' => $year['id']]);
        $counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // WHERE shared by the count query and the list query:
        // students enrolled in one of my subjects (or in the chosen one), narrowed by the search
        $inIds = $subjectId ? (string)$subjectId : $allIds;
        [$searchSql, $params] = StudentSearchSql($search);
        $params[':year'] = $year['id'];
        $sqlWhere = "WHERE s.school_year_id = :year
                       AND EXISTS (SELECT 1 FROM enrollments e
                                   WHERE e.student_id = s.id AND e.subject_id IN ($inIds))
                       $searchSql";

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students s $sqlWhere");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $totalPages = max(1, (int)ceil($total / $perPage));
        $page       = min(max(1, (int)($_GET['PageNum'] ?? 1)), $totalPages);
        $offset     = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("SELECT s.id, s.fname, s.lname, s.grade_level
                               FROM students s
                               $sqlWhere
                               ORDER BY s.$sort $dir, s.lname, s.fname, s.id
                               LIMIT $perPage OFFSET $offset");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // the scores of these students in my subjects (a subject without scores yet comes back empty)
        if ($rows) {
            $studentIds = implode(', ', array_map(fn($r) => (int)$r['id'], $rows));
            $scoreCols  = implode(', ', array_map(fn($c) => "g.$c", array_keys(GRADE_COLUMNS)));

            $stmt = $pdo->query("SELECT e.student_id, e.subject_id, $scoreCols
                                 FROM enrollments e
                                 LEFT JOIN grades g ON g.student_id = e.student_id AND g.subject_id = e.subject_id
                                 WHERE e.student_id IN ($studentIds) AND e.subject_id IN ($allIds)");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
                $mine[(int)$g['student_id']][(int)$g['subject_id']] = $g;
            }
        }
    }
} catch (PDOException $e) {
    err_log($e->getMessage());
    echo alertBox('danger', 'Could not load your students. Please try again.');
    return;
}

// the subjects shown as badges on each row
$shownSubjects = $subjectId ? [$subjectId => $gradable[$subjectId]] : $gradable;

// Current state of the page. makeUrl() builds every link from this.
$state = [
    'sort'         => $sort,
    'dir'          => $dir,
    'search'       => $search,
    'subject_id'   => $subjectId,
    'PerPageLimit' => $perPage,
    'PageNum'      => $page,
];
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

    <h3>Grades <small class="text-muted fs-6"><?= htmlspecialchars($year['name']) ?></small></h3>
    <?= GradeNav('mine') ?>
    <div id="message"><?= $message ?? '' ?></div>

    <?php if (!$gradable): ?>

        <?= alertBox('info', 'You have no subjects to grade in this school year. Ask the admin to assign subjects to you.') ?>

    <?php else: ?>

        <!-- MY SUBJECTS: pick one to see only its students -->
        <div class="mb-3 d-flex flex-wrap align-items-center gap-2">
            <span class="text-muted">My subjects:</span>
            <a class="btn btn-sm <?= $subjectId ? 'btn-outline-primary' : 'btn-primary' ?>"
               href="<?= makeUrl($self, $state, ['subject_id' => null, 'PageNum' => null]) ?>">All</a>
            <?php foreach ($gradable as $id => $name): ?>
                <a class="btn btn-sm <?= $id === $subjectId ? 'btn-primary' : 'btn-outline-primary' ?>"
                   href="<?= makeUrl($self, $state, ['subject_id' => $id, 'PageNum' => null]) ?>">
                    <?= htmlspecialchars($name) ?> <span class="badge text-bg-light"><?= (int)($counts[$id] ?? 0) ?></span>
                </a>
            <?php endforeach; ?>

            <?php if ($subjectId): ?>
                <a class="btn btn-sm btn-outline-secondary ms-auto"
                   href="<?= COMPONENTS_URL ?>manage_classRecord.php?subject_id=<?= $subjectId ?>">Open class record</a>
            <?php endif; ?>
        </div>

        <div class="row">
            <div class="col-6">
                <!-- SEARCH -->
                <form method="get" action="<?= $self ?>" class="mb-3 d-flex gap-2">
                    <input type="hidden" name="sort" value="<?= $sort ?>">
                    <input type="hidden" name="dir" value="<?= $dir ?>">
                    <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
                    <input type="hidden" name="PerPageLimit" value="<?= $perPage ?>">
                    <input type="text" name="search" class="form-control w-auto" value="<?= htmlspecialchars($search) ?>" placeholder="Search name or grade level...">
                    <button class="btn btn-outline-primary">Search</button>
                    <?php if ($search !== ''): ?>
                        <a href="<?= makeUrl($self, $state, ['search' => '', 'PageNum' => null]) ?>" class="btn btn-outline-secondary">
                            Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="col-6 d-flex justify-content-end">
                <form method="get" action="<?= $self ?>" class="mb-3 d-flex gap-2 align-items-start">
                    <input type="hidden" name="sort" value="<?= $sort ?>">
                    <input type="hidden" name="dir" value="<?= $dir ?>">
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                    <input type="hidden" name="subject_id" value="<?= $subjectId ?>">

                    <div class="form-floating">
                        <input type="number" name="PerPageLimit" class="form-control" value="<?= $perPage ?>"
                               id="limitRows" placeholder="Rows" min="1" max="100">
                        <label for="limitRows">No. of rows</label>
                    </div>

                    <button class="btn btn-outline-primary">Set</button>
                </form>
            </div>
        </div>

        <!-- TABLE -->
        <table class="table align-middle">
            <tr>
                <?php foreach ($headers as $col => $label): ?>
                    <th>
                        <a href="<?= makeUrl($self, $state, ['sort' => $col, 'dir' => $flip, 'PageNum' => null]) ?>">
                            <?= $label ?>
                        </a>
                    </th>
                <?php endforeach; ?>
                <th><?= $subjectId ? htmlspecialchars($gradable[$subjectId]) . ' (weighted grade)' : 'My subjects (weighted grade)' ?></th>
                <th>Action</th>
            </tr>

            <?php if (!$rows): ?>
                <tr><td colspan="5" class="text-center text-muted">
                    <?= $search !== '' ? 'No student matches your search.' : 'No students are enrolled in ' . ($subjectId ? 'this subject' : 'your subjects') . ' yet. Enroll them in Enrollment first.' ?>
                </td></tr>
            <?php endif; ?>

            <?php foreach ($rows as $r): ?>
                <?php $sid = (int)$r['id']; ?>
                <tr>
                    <td><?= htmlspecialchars($r['lname']) ?></td>
                    <td><?= htmlspecialchars($r['fname']) ?></td>
                    <td><?= htmlspecialchars($r['grade_level'] ?? '') ?></td>
                    <td>
                        <?php foreach ($shownSubjects as $subId => $subName): ?>
                            <?php
                            if (!isset($mine[$sid][$subId])) {
                                continue;   // the student is not enrolled in this one of my subjects
                            }
                            $final = GradeFinal($mine[$sid][$subId]);
                            ?>
                            <span class="badge text-bg-light border text-dark fw-normal me-1">
                                <?= htmlspecialchars($subName) ?>
                                <strong><?= $final === null ? '&mdash;' : number_format($final, 1) ?></strong>
                            </span>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <a href="<?= COMPONENTS_URL ?>manage_grades.php?student_id=<?= $sid ?>" class="btn btn-sm btn-primary">Grade</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <p class="text-muted small">
            <?= $total ?> student<?= $total === 1 ? '' : 's' ?>.
            &mdash; means no scores entered yet. Weighted = <?= GradeWeightsText() ?>.
        </p>

        <?php renderPagination(makeUrl($self, $state, ['PageNum' => null]), $page, "PageNum", $totalPages); ?>

    <?php endif; ?>

</div>
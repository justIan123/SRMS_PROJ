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

// This page only shows the recommendations saved in the recommendations table.
// recommendationProcess.php works them out (Refresh) and saves the notes.
$self    = COMPONENTS_URL . 'manage_recommendations.php';
$handler = HANDLERS_URL . 'recommendationProcess.php';
$myId    = (int)($_SESSION['user_id'] ?? 0);   // decides which subjects are shown (admin = all)

// status saved in the table => [label, Bootstrap badge class]. This is also the order of the buttons.
$statuses = [
    'risk'      => ['At risk',         'text-bg-danger'],
    'watch'     => ['Needs attention', 'text-bg-warning'],
    'ok'        => ['On track',        'text-bg-primary'],
    'excellent' => ['Excellent',       'text-bg-success'],
];

// ---- search + filters ----
$search    = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
$subjectId = (int)($_GET['subject_id'] ?? 0);   // 0 = all of my subjects

$statusIn = $_GET['status'] ?? '';
$status   = is_string($statusIn) && array_key_exists($statusIn, $statuses) ? $statusIn : '';   // '' = every status

// ---- sort (only a key of $headers can ever reach the SQL, and only through $sortColumns) ----
$headers     = ['name' => 'Student', 'subject' => 'Subject', 'final_grade' => 'Weighted grade'];
$sortColumns = ['name' => 's.lname', 'subject' => 'sub.subject_name', 'final_grade' => 'r.final_grade'];
$sortIn      = $_GET['sort'] ?? '';
$sort        = is_string($sortIn) && array_key_exists($sortIn, $headers) ? $sortIn : 'final_grade';   // lowest grade first
$dir         = ($_GET['dir'] ?? '') === 'DESC' ? 'DESC' : 'ASC';
$flip        = $dir === 'ASC' ? 'DESC' : 'ASC';

$perPage = min(100, max(1, (int)($_GET['PerPageLimit'] ?? 20)));

try {
    // Everything belongs to the current school year
    $year = CurrentSchoolYear($pdo);
    if (!$year) {
        echo alertBox('warning', 'No school year is running. Start one in School Years first.');
        return;
    }

    // the subjects this user may grade (an admin gets all of them)
    $gradable  = GradableSubjects($pdo, (int)$year['id'], $myId);   // subject id => name
    $subjectId = isset($gradable[$subjectId]) ? $subjectId : 0;     // an id from the URL is only used if it is one of mine

    $counts     = array_fill_keys(array_keys($statuses), 0);   // status => how many rows
    $total      = 0;
    $rows       = [];   // the rows shown on this page
    $page       = 1;
    $totalPages = 1;

    if ($gradable) {
        $inIds = $subjectId ? (string)$subjectId : implode(', ', array_map('intval', array_keys($gradable)));   // integers only

        [$searchSql, $params] = StudentSearchSql($search);
        $params[':year'] = $year['id'];
        $sqlWhere = "WHERE s.school_year_id = :year AND r.subject_id IN ($inIds) $searchSql";

        // how many rows of each status (before the status filter, so the buttons always show the real numbers)
        $stmt = $pdo->prepare("SELECT r.status, COUNT(*)
                               FROM recommendations r
                               JOIN students s ON s.id = r.student_id
                               $sqlWhere
                               GROUP BY r.status");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $key => $n) {
            if (isset($counts[$key])) {
                $counts[$key] = (int)$n;
            }
        }

        // the status filter only applies to the list
        if ($status !== '') {
            $sqlWhere .= " AND r.status = :status";
            $params[':status'] = $status;
        }
        $total = $status === '' ? array_sum($counts) : $counts[$status];

        $totalPages = max(1, (int)ceil($total / $perPage));
        $page       = min(max(1, (int)($_GET['PageNum'] ?? 1)), $totalPages);
        $offset     = ($page - 1) * $perPage;

        // outdated = the scores were saved after the recommendation was made (or were removed since)
        $stmt = $pdo->prepare("SELECT r.id, r.status, r.final_grade, r.advice, r.is_done, r.teacher_note, r.generated_at,
                                      s.fname, s.lname, s.grade_level, sub.subject_name,
                                      CASE WHEN g.student_id IS NULL OR g.updated_at > r.generated_at THEN 1 ELSE 0 END AS outdated
                               FROM recommendations r
                               JOIN students s ON s.id = r.student_id
                               JOIN subjects sub ON sub.id = r.subject_id
                               LEFT JOIN grades g ON g.student_id = r.student_id AND g.subject_id = r.subject_id
                               $sqlWhere
                               ORDER BY {$sortColumns[$sort]} $dir, s.lname, s.fname, r.id
                               LIMIT $perPage OFFSET $offset");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    err_log($e->getMessage());
    echo alertBox('danger', 'Could not load the recommendations. Please try again.');
    return;
}

$anyOutdated = array_sum(array_column($rows, 'outdated')) > 0;

// Current state of the page. makeUrl() builds every link from this,
// and every form sends it back so the page looks the same afterwards.
$state = [
    'sort'         => $sort,
    'dir'          => $dir,
    'search'       => $search,
    'subject_id'   => $subjectId,
    'status'       => $status,
    'PerPageLimit' => $perPage,
    'PageNum'      => $page,
];
?>

<div hx-boost="true" hx-target="#main" hx-push-url="false" hx-indicator="#global-spinner, #main">

    <h3>Student Recommendations <small class="text-muted fs-6"><?= htmlspecialchars($year['name']) ?></small></h3>
    <div id="message"><?= $message ?? '' ?></div>

    <?php if (!$gradable): ?>

        <?= alertBox('info', 'You have no subjects to grade in this school year. Ask the admin to assign subjects to you.') ?>

    <?php else: ?>

        <!-- MY SUBJECTS + REFRESH -->
        <div class="mb-2 d-flex flex-wrap align-items-center gap-2">
            <span class="text-muted">Subject:</span>
            <a class="btn btn-sm <?= $subjectId ? 'btn-outline-primary' : 'btn-primary' ?>"
               href="<?= makeUrl($self, $state, ['subject_id' => null, 'PageNum' => null]) ?>">All</a>
            <?php foreach ($gradable as $id => $name): ?>
                <a class="btn btn-sm <?= $id === $subjectId ? 'btn-primary' : 'btn-outline-primary' ?>"
                   href="<?= makeUrl($self, $state, ['subject_id' => $id, 'PageNum' => null]) ?>"><?= htmlspecialchars($name) ?></a>
            <?php endforeach; ?>

            <!-- REFRESH: works the recommendations out again from the saved scores (the chosen subject, or all of mine) -->
            <form hx-post="<?= $handler ?>" class="ms-auto">
                <input type="hidden" name="action" value="generate">
                <?php foreach ($state as $k => $v): ?>
                    <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars((string)$v) ?>">
                <?php endforeach; ?>
                <button class="btn btn-sm btn-success">Refresh recommendations</button>
            </form>
        </div>

        <!-- STATUS -->
        <div class="mb-3 d-flex flex-wrap align-items-center gap-2">
            <span class="text-muted">Status:</span>
            <a class="btn btn-sm <?= $status === '' ? 'btn-primary' : 'btn-outline-primary' ?>"
               href="<?= makeUrl($self, $state, ['status' => null, 'PageNum' => null]) ?>">
                All <span class="badge text-bg-light"><?= array_sum($counts) ?></span>
            </a>
            <?php foreach ($statuses as $key => [$label, $badge]): ?>
                <a class="btn btn-sm <?= $key === $status ? 'btn-primary' : 'btn-outline-primary' ?>"
                   href="<?= makeUrl($self, $state, ['status' => $key, 'PageNum' => null]) ?>">
                    <?= htmlspecialchars($label) ?> <span class="badge text-bg-light"><?= $counts[$key] ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($anyOutdated): ?>
            <?= alertBox('warning', 'Some scores changed after their recommendation was made. Press <strong>Refresh recommendations</strong> to update them.') ?>
        <?php endif; ?>

        <!-- SEARCH -->
        <form method="get" action="<?= $self ?>" class="mb-3 d-flex gap-2">
            <input type="hidden" name="sort" value="<?= $sort ?>">
            <input type="hidden" name="dir" value="<?= $dir ?>">
            <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
            <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
            <input type="hidden" name="PerPageLimit" value="<?= $perPage ?>">
            <input type="text" name="search" class="form-control w-auto" value="<?= htmlspecialchars($search) ?>" placeholder="Search name or grade level...">
            <button class="btn btn-outline-primary">Search</button>
            <?php if ($search !== ''): ?>
                <a href="<?= makeUrl($self, $state, ['search' => '', 'PageNum' => null]) ?>" class="btn btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </form>

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
                <th>Recommendation</th>
                <th>Follow-up</th>
            </tr>

            <?php if (!$rows): ?>
                <tr><td colspan="5" class="text-center text-muted">
                    <?php if (array_sum($counts) === 0 && $search === ''): ?>
                        No recommendations yet. Press <strong>Refresh recommendations</strong> to create them from the saved scores.
                    <?php else: ?>
                        No recommendation matches your search or filter.
                    <?php endif; ?>
                </td></tr>
            <?php endif; ?>

            <?php foreach ($rows as $r): ?>
                <?php [$label, $badge] = $statuses[$r['status']]; ?>
                <tr>
                    <td class="text-nowrap">
                        <?= htmlspecialchars($r['lname'] . ', ' . $r['fname']) ?>
                        <?php if (($r['grade_level'] ?? '') !== ''): ?>
                            <small class="text-muted"><?= htmlspecialchars($r['grade_level']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap"><?= htmlspecialchars($r['subject_name']) ?></td>
                    <td class="text-nowrap">
                        <strong><?= number_format((float)$r['final_grade'], 1) ?></strong>
                        <span class="badge <?= $badge ?> ms-1"><?= htmlspecialchars($label) ?></span>
                        <?php if ($r['is_done']): ?>
                            <span class="badge text-bg-success ms-1">Done</span>
                        <?php endif; ?>
                        <?php if ($r['outdated']): ?>
                            <br><small class="text-warning">Scores changed, refresh</small>
                        <?php endif; ?>
                        <br><small class="text-muted">Made <?= htmlspecialchars(substr((string)$r['generated_at'], 0, 10)) ?></small>
                    </td>
                    <td>
                        <ul class="small mb-0 ps-3">
                            <?php foreach (explode("\n", $r['advice']) as $line): ?>
                                <li><?= htmlspecialchars($line) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                    <td style="min-width: 14rem;">
                        <!-- One form per row: the note is saved by both buttons, "Done" also switches the done mark -->
                        <form hx-post="<?= $handler ?>" class="d-flex flex-column gap-1">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <?php foreach ($state as $k => $v): ?>
                                <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars((string)$v) ?>">
                            <?php endforeach; ?>
                            <input type="text" name="note" maxlength="500" class="form-control form-control-sm"
                                   value="<?= htmlspecialchars($r['teacher_note'] ?? '') ?>" placeholder="Note (what was done)"
                                   aria-label="Note for <?= htmlspecialchars($r['lname'] . ', ' . $r['fname'] . ' - ' . $r['subject_name']) ?>">
                            <div class="d-flex gap-1">
                                <button name="action" value="save" class="btn btn-sm btn-outline-primary">Save note</button>
                                <button name="action" value="toggle" class="btn btn-sm <?= $r['is_done'] ? 'btn-outline-secondary' : 'btn-outline-success' ?>">
                                    <?= $r['is_done'] ? 'Undo done' : 'Mark done' ?>
                                </button>
                            </div>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <p class="text-muted small">
            <?= $total ?> row<?= $total === 1 ? '' : 's' ?> (one per student and subject).
            Students without any score yet are not listed.
            At risk = below the passing mark, Needs attention = just above it.
            Weighted = <?= GradeWeightsText() ?>, based on the scores entered when the recommendation was made.
        </p>

        <?php renderPagination(makeUrl($self, $state, ['PageNum' => null]), $page, "PageNum", $totalPages); ?>

    <?php endif; ?>

</div>
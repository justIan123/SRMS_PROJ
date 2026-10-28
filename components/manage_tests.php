<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "testFunctions.php");

$self = COMPONENTS_URL . 'manage_tests.php';

// ---- search + subject filter ----
$search        = trim($_GET['search'] ?? '');
$subjectFilter = (int)($_GET['subject'] ?? 0);

// ---- sort, newest first unless told otherwise (only a key of $headers can ever reach the SQL) ----
$headers = ['title' => 'Title', 'subject_name' => 'Subject', 'question_count' => 'Questions',
            'time_limit' => 'Time', 'is_published' => 'Status', 'created_at' => 'Created'];
$sort = array_key_exists($_GET['sort'] ?? '', $headers) ? $_GET['sort'] : 'created_at';
$dir  = ($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$flip = $dir === 'ASC' ? 'DESC' : 'ASC';

$perPage = 10;
$editId  = (int)($_GET['edit'] ?? 0);

try {
    // Everything belongs to the current school year
    $year = CurrentSchoolYear($pdo);
    if (!$year) {
        echo alertBox('warning', 'No school year is running. Start one in School Years first.');
        return;
    }

    // Only teachers make tests, and only for their own subjects
    $teacherId = CurrentUserId();
    if (!IsTeacher($pdo, $teacherId)) {
        echo alertBox('warning', 'Only teachers can create tests.');
        return;
    }
    $subjects   = TeacherSubjects($pdo, $teacherId, $year['id']);
    $subjectIds = array_map('intval', array_column($subjects, 'id'));

    // a filter on a subject that is not theirs is ignored
    if (!in_array($subjectFilter, $subjectIds, true)) {
        $subjectFilter = 0;
    }

    // WHERE shared by the count query and the list query
    $sqlWhere = "WHERE t.school_year_id = :year AND t.teacher_id = :teacher";
    $params   = [':year' => $year['id'], ':teacher' => $teacherId];
    if ($subjectFilter) {
        $sqlWhere .= " AND t.subject_id = :subject";
        $params[':subject'] = $subjectFilter;
    }
    if ($search !== '') {
        $sqlWhere .= " AND t.title ILIKE :s1";
        $params[':s1'] = '%' . $search . '%';
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tests t $sqlWhere");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $perPage));
    $page       = min(max(1, (int)($_GET['page'] ?? 1)), $totalPages);
    $offset     = ($page - 1) * $perPage;

    // subject name and number of questions are worked out per test
    $stmt = $pdo->prepare("SELECT t.*,
                                  s.subject_name,
                                  (SELECT COUNT(*) FROM questions q WHERE q.test_id = t.id) AS question_count
                           FROM tests t
                           LEFT JOIN subjects s ON s.id = t.subject_id
                           $sqlWhere
                           ORDER BY $sort $dir
                           LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    err_log($e->getMessage());
    echo alertBox('danger', 'Could not load your tests. Please try again.');
    return;
}

// Current state of the page. makeUrl() builds every link from this,
// and the edit/delete requests send it back so the page looks the same afterwards.
$state = [
    'sort'    => $sort,
    'dir'     => $dir,
    'search'  => $search,
    'subject' => $subjectFilter,
    'page'    => $page,
];
?>

<div hx-boost="true" hx-target="#main" hx-push-url="false" hx-indicator="#global-spinner">

    <h3>My Tests <small class="text-muted fs-6"><?= htmlspecialchars($year['name']) ?></small></h3>
    <div id="message"><?= $message ?? '' ?></div>

    <!-- CREATE -->
    <?php if (!$subjects): ?>
        <div class="alert alert-info">
            You have no subjects for <?= htmlspecialchars($year['name']) ?> yet. Ask an admin to assign subjects to you.
        </div>
    <?php else: ?>
        <form hx-post="<?= HANDLERS_URL ?>testProcess.php" class="mb-4 d-flex gap-2">
            <input type="hidden" name="action" value="create">
            <input name="title" placeholder="Test title" class="form-control" required>
            <select name="subject_id" class="form-select w-auto" required>
                <option value="">Subject...</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['subject_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input name="time_limit" type="number" min="1" max="600" placeholder="Minutes (optional)" class="form-control w-auto">
            <button class="btn btn-primary">Create</button>
        </form>
    <?php endif; ?>

    <!-- SEARCH -->
    <form method="get" action="<?= $self ?>" class="mb-3 d-flex gap-2">
        <input type="hidden" name="sort" value="<?= $sort ?>">
        <input type="hidden" name="dir" value="<?= $dir ?>">
        <input type="text" name="search" class="form-control w-auto" value="<?= htmlspecialchars($search) ?>" placeholder="Search title...">
        <select name="subject" class="form-select w-auto">
            <option value="0">All subjects</option>
            <?php foreach ($subjects as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $s['id'] == $subjectFilter ? 'selected' : '' ?>><?= htmlspecialchars($s['subject_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-outline-primary">Search</button>
        <?php if ($search !== '' || $subjectFilter): ?>
            <a href="<?= makeUrl($self, $state, ['search' => '', 'subject' => null, 'page' => null]) ?>" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <!-- UPDATE form (only exists while editing) -->
    <?php if ($editId): ?>
        <form id="editForm" hx-post="<?= HANDLERS_URL ?>testProcess.php">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= $editId ?>">
            <?php foreach ($state as $k => $v): ?>
                <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars($v) ?>">
            <?php endforeach; ?>
        </form>
    <?php endif; ?>

    <!-- TABLE -->
    <table class="table align-middle">
        <tr>
            <?php foreach ($headers as $col => $label): ?>
                <th><a href="<?= makeUrl($self, $state, ['sort' => $col, 'dir' => $flip, 'page' => null]) ?>"><?= $label ?></a></th>
            <?php endforeach; ?>
            <th>Actions</th>
        </tr>

        <?php if (!$rows): ?>
            <tr><td colspan="7" class="text-center text-muted">No tests found.</td></tr>
        <?php endif; ?>

        <?php foreach ($rows as $r): ?>
            <?php if ($r['id'] == $editId): ?>

                <tr>
                    <td><input class="form-control form-control-sm" form="editForm" name="title" value="<?= htmlspecialchars($r['title']) ?>" required></td>
                    <td>
                        <select class="form-select form-select-sm" form="editForm" name="subject_id" required>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $s['id'] == $r['subject_id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['subject_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><?= $r['question_count'] ?></td>
                    <td><input class="form-control form-control-sm" form="editForm" name="time_limit" type="number" min="1" max="600" value="<?= htmlspecialchars($r['time_limit'] ?? '') ?>" placeholder="Minutes"></td>
                    <td><?= $r['is_published'] ? 'Published' : 'Draft' ?></td>
                    <td><?= htmlspecialchars(substr($r['created_at'], 0, 10)) ?></td>
                    <td>
                        <button form="editForm" class="btn btn-sm btn-success">Save</button>
                        <a href="<?= makeUrl($self, $state) ?>" class="btn btn-sm btn-secondary">Cancel</a>
                    </td>
                </tr>

            <?php else: ?>

                <tr>
                    <td><?= htmlspecialchars($r['title']) ?></td>
                    <td><?= htmlspecialchars($r['subject_name'] ?? '') ?></td>
                    <td><?= $r['question_count'] ?></td>
                    <td><?= $r['time_limit'] ? (int)$r['time_limit'] . ' min' : 'No limit' ?></td>
                    <td>
                        <?php if ($r['is_published']): ?>
                            <span class="badge text-bg-success">Published</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary">Draft</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars(substr($r['created_at'], 0, 10)) ?></td>
                    <td>
                        <a href="<?= makeUrl(COMPONENTS_URL . 'manage_questions.php', [], ['test' => $r['id']]) ?>" class="btn btn-sm btn-primary">Questions</a>
                        <a href="<?= makeUrl($self, $state, ['edit' => $r['id']]) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <button class="btn btn-sm btn-outline-secondary"
                                hx-post="<?= HANDLERS_URL ?>testProcess.php"
                                hx-vals='<?= htmlspecialchars(json_encode($state + ['action' => 'toggle', 'id' => $r['id']]), ENT_QUOTES) ?>'>
                            <?= $r['is_published'] ? 'Unpublish' : 'Publish' ?>
                        </button>
                        <button class="btn btn-sm btn-outline-danger"
                                hx-post="<?= HANDLERS_URL ?>testProcess.php"
                                hx-vals='<?= htmlspecialchars(json_encode($state + ['action' => 'delete', 'id' => $r['id']]), ENT_QUOTES) ?>'
                                hx-confirm="Delete this test and all its questions?">Delete</button>
                    </td>
                </tr>

            <?php endif; ?>
        <?php endforeach; ?>
    </table>

    <?php renderPagination(makeUrl($self, $state, ['page' => null]), $page, "page", $totalPages); ?>

</div>
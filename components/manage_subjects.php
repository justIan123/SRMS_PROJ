<?php
require_once("../public/initialize.php");
require_once(CONFIG_PATH . DS . "db_sqlite.php");
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");

$self    = COMPONENTS_URL . 'manage_subjects.php';
$handler = HANDLERS_URL . 'subjectsProcess.php';
$table   = 'subjects';

// Everything belongs to the current school year
$year = CurrentSchoolYear($pdo);
if (!$year) {
    echo alertBox('warning', 'No school year is running. Start one in School Years first.');
    return;
}
$where = ['school_year_id' => $year['id']];

// ---- search ----
$search = trim($_GET['search'] ?? '');
$cols   = ['subject_name', 'subject_description'];

// ---- sort ----
$headers = [
    'id'                  => 'ID',
    'subject_name'        => 'Subject',
    'subject_description' => 'Description',
    'teachers'            => 'Teachers',
    'is_active'           => 'Status',
];
$sort = array_key_exists($_GET['sort'] ?? '', $headers) ? $_GET['sort'] : 'id';
$dir  = ($_GET['dir'] ?? '') === 'DESC' ? 'DESC' : 'ASC';
$flip = $dir === 'ASC' ? 'DESC' : 'ASC';

// ---- pagination ----
$perPage    = max(1, (int)($_GET['PerPageLimit'] ?? 10));
$total      = CountList($pdo, $table, $where, $cols, $search);
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = min(max(1, (int)($_GET['PageNum'] ?? 1)), $totalPages);

// Teachers of each subject, shown as one text value ("Ann Cruz, Ben Lee")
$extra = [
    'teachers' => "SELECT GROUP_CONCAT(u.fname || ' ' || u.lname, ', ')
                   FROM teacher_subjects ts
                   JOIN users u ON u.id = ts.teacher_id
                   WHERE ts.subject_id = subjects.id"
];

$rows = ListRecords($pdo, $table, $where, $cols, $search, $perPage, ($page - 1) * $perPage, $sort, $dir, $extra);

// ---- which subject is being edited / getting teachers ----
$editId   = (int)($_GET['edit'] ?? 0);
$assignId = (int)($_GET['assign'] ?? 0);

// ---- teacher list (only loaded while assigning) ----
$assignSubject = null;
$teachers      = [];
$assignedIds   = [];

if ($assignId) {
    $assignSubject = FindById($pdo, $table, $assignId);

    // forget it if the subject does not exist or is from another school year
    if (!$assignSubject || $assignSubject['school_year_id'] != $year['id']) {
        $assignSubject = null;
        $assignId      = 0;
    }
}

if ($assignId) {
    // all active users that have the "teacher" role
    $stmt = $pdo->query("SELECT u.id, u.fname, u.lname
                         FROM users u
                         JOIN user_roles ur ON ur.user_id = u.id
                         JOIN roles r ON r.role_id = ur.role
                         WHERE r.role = 'teacher' AND u.is_active = 1
                         ORDER BY u.lname, u.fname");
    $teachers = $stmt->fetchAll();

    // teachers already assigned to this subject
    foreach (FindWhere($pdo, 'teacher_subjects', ['subject_id' => $assignId]) as $ts) {
        $assignedIds[] = $ts['teacher_id'];
    }
}

// ---- remember where we are (every link and form carries this) ----
$state = [
    'sort'         => $sort,
    'dir'          => $dir,
    'PageNum'      => $page,
    'search'       => $search,
    'PerPageLimit' => $perPage,
    'edit'         => $editId,
    'assign'       => $assignId,
];
?>

<div hx-boost="true" hx-target="#main" hx-push-url="false" hx-indicator="#global-spinner">

    <h3>Subjects <small class="text-muted fs-6"><?= htmlspecialchars($year['name']) ?></small></h3>
    <div id="message"><?= $message ?? '' ?></div>

    <!-- CREATE -->
    <form hx-post="<?= $handler ?>" class="mb-4">
        <input type="hidden" name="action" value="create">
        <?php hiddenFields($state); ?>
        <div class="row mb-3">
            <div class="col">
                <input name="subject_name" placeholder="Subject name" class="form-control" required>
            </div>
            <div class="col">
                <input name="subject_description" placeholder="Description" class="form-control">
            </div>
            <div class="col d-flex justify-content-end">
                <button class="btn btn-primary">Create</button>
            </div>
        </div>
    </form>

    <div class="row">
        <div class="col-6">
            <!-- SEARCH -->
            <form method="get" action="<?= $self ?>" class="mb-3 d-flex gap-2">
                <?php hiddenFields($state, ['search', 'PageNum']); ?>
                <input type="text" name="search" class="form-control w-auto"
                       value="<?= htmlspecialchars($search) ?>" placeholder="Search...">
                <button class="btn btn-outline-primary">Search</button>
                <?php if ($search !== ''): ?>
                    <a href="<?= makeUrl($self, $state, ['search' => '', 'PageNum' => null]) ?>"
                       class="btn btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="col-6 d-flex justify-content-end">
            <!-- limit rows -->
            <form method="get" action="<?= $self ?>" class="mb-3 d-flex gap-2">
                <p class="mt-3">Rows per page</p>
                <?php hiddenFields($state, ['PerPageLimit', 'PageNum']); ?>
                <input type="number" name="PerPageLimit" min="1" class="form-control w-auto"
                       value="<?= htmlspecialchars($perPage) ?>">
                <button class="btn btn-outline-primary">Set</button>
            </form>
        </div>
    </div>

    <!-- UPDATE form (only exists while editing) -->
    <?php if ($editId): ?>
        <form id="editForm" hx-post="<?= $handler ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= $editId ?>">
            <?php hiddenFields($state); ?>
        </form>
    <?php endif; ?>

    <!-- SUBJECT TABLE -->
    <table class="table align-middle">
        <tr>
            <?php foreach ($headers as $col => $label): ?>
                <th>
                    <a href="<?= makeUrl($self, $state, ['sort' => $col, 'dir' => $flip, 'PageNum' => null]) ?>">
                        <?= $label ?>
                    </a>
                </th>
            <?php endforeach; ?>
            <th>Actions</th>
        </tr>

        <?php if (!$rows): ?>
            <tr><td colspan="6" class="text-center text-muted">No subjects found.</td></tr>
        <?php endif; ?>

        <?php foreach ($rows as $r): ?>
            <?php if ($r['id'] == $editId): ?>

                <!-- Row type 1: this subject is being edited -->
                <tr>
                    <td><?= $r['id'] ?></td>
                    <td><input form="editForm" name="subject_name" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($r['subject_name']) ?>" required></td>
                    <td><input form="editForm" name="subject_description" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($r['subject_description'] ?? '') ?>"></td>
                    <td class="text-muted"><?= htmlspecialchars($r['teachers'] ?? '-') ?></td>
                    <td>
                        <select form="editForm" name="is_active" class="form-select form-select-sm">
                            <option value="1" <?= $r['is_active'] ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= $r['is_active'] ? '' : 'selected' ?>>Inactive</option>
                        </select>
                    </td>
                    <td>
                        <button form="editForm" class="btn btn-sm btn-success">Save</button>
                        <a href="<?= makeUrl($self, $state, ['edit' => null]) ?>"
                           class="btn btn-sm btn-secondary">Cancel</a>
                    </td>
                </tr>

            <?php else: ?>

                <!-- Row type 2: a normal row -->
                <tr class="<?= $r['id'] == $assignId ? 'table-warning' : '' ?>">
                    <td><?= $r['id'] ?></td>
                    <td><?= htmlspecialchars($r['subject_name']) ?></td>
                    <td><?= htmlspecialchars($r['subject_description'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['teachers'] ?? '-') ?></td>
                    <td>
                        <?php if ($r['is_active']): ?>
                            <span class="badge text-bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                        <a href="<?= makeUrl($self, $state, ['edit' => $r['id'], 'assign' => null]) ?>"
                           class="btn btn-sm btn-outline-primary">Edit</a>

                        <?php if ($r['id'] == $assignId): ?>
                            <a href="<?= makeUrl($self, $state, ['assign' => null]) ?>"
                               class="btn btn-sm btn-secondary">Done</a>
                        <?php else: ?>
                            <a href="<?= makeUrl($self, $state, ['assign' => $r['id'], 'edit' => null]) ?>"
                               class="btn btn-sm btn-outline-success">Teachers</a>
                        <?php endif; ?>

                        <button class="btn btn-sm btn-outline-danger"
                                hx-post="<?= $handler ?>"
                                hx-vals='<?= postValues($state, [
                                    'action'    => 'toggle',
                                    'id'        => $r['id'],
                                    'is_active' => $r['is_active'] ? 0 : 1,
                                ]) ?>'
                                hx-confirm="<?= $r['is_active'] ? 'Deactivate' : 'Activate' ?> this subject?">
                            <?= $r['is_active'] ? 'Deactivate' : 'Activate' ?>
                        </button>
                    </td>
                </tr>

            <?php endif; ?>
        <?php endforeach; ?>
    </table>

    <?php renderPagination(makeUrl($self, $state, []), $page, "PageNum", $totalPages); ?>


    <!-- TEACHERS (only shown while assigning teachers to a subject) -->
    <?php if ($assignSubject): ?>
        <div id="teachersDiv" class="mt-4">
            <h5>Teachers for <?= htmlspecialchars($assignSubject['subject_name']) ?></h5>

            <table class="table align-middle w-auto">
                <tr>
                    <th>Teacher</th>
                    <th></th>
                </tr>

                <?php if (!$teachers): ?>
                    <tr><td colspan="2" class="text-center text-muted">No teachers found.</td></tr>
                <?php endif; ?>

                <?php foreach ($teachers as $t): ?>
                    <?php $isAssigned = in_array($t['id'], $assignedIds); ?>
                    <tr class="<?= $isAssigned ? 'table-success' : '' ?>">
                        <td><?= htmlspecialchars($t['fname'] . ' ' . $t['lname']) ?></td>
                        <td class="text-end">
                            <?php if ($isAssigned): ?>
                                <button class="btn btn-sm btn-outline-danger"
                                        hx-post="<?= $handler ?>"
                                        hx-vals='<?= postValues($state, [
                                            'action'     => 'unassign',
                                            'id'         => $assignId,
                                            'teacher_id' => $t['id'],
                                        ]) ?>'>Remove</button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-success"
                                        hx-post="<?= $handler ?>"
                                        hx-vals='<?= postValues($state, [
                                            'action'     => 'assign',
                                            'id'         => $assignId,
                                            'teacher_id' => $t['id'],
                                        ]) ?>'>Assign</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>

</div>
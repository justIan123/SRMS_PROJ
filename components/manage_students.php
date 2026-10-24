<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");

$self    = COMPONENTS_URL . 'manage_students.php';
$handler = HANDLERS_URL . 'studentProcess.php';
$table   = 'students';

// Everything belongs to the current school year
$year = CurrentSchoolYear($pdo);
if (!$year) {
    echo alertBox('warning', 'No school year is running. Start one in School Years first.');
    return;
}
$where = ['school_year_id' => $year['id']];

// ---- search ----
$search = trim($_GET['search'] ?? '');
$cols   = ['fname', 'lname', 'grade_level'];

// ---- sort ----
$headers = [
    'id'            => 'ID',
    'fname'         => 'First name',
    'lname'         => 'Last name',
    'grade_level'   => 'Grade',
    'guardian_name' => 'Parent / Guardian',
];
$sort = array_key_exists($_GET['sort'] ?? '', $headers) ? $_GET['sort'] : 'id';
$dir  = ($_GET['dir'] ?? '') === 'DESC' ? 'DESC' : 'ASC';
$flip = $dir === 'ASC' ? 'DESC' : 'ASC';

// ---- pagination (students) ----
$perPage    = max(1, (int)($_GET['PerPageLimit'] ?? 10));
$total      = CountList($pdo, $table, $where, $cols, $search);
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = min(max(1, (int)($_GET['PageNum'] ?? 1)), $totalPages);

// The guardian's name of each student, shown as one text value
$extra = [
    'guardian_name' => "SELECT g.fname || ' ' || g.lname
                        FROM guardians g
                        WHERE g.id = students.guardian_id"
];

$rows = ListRecords($pdo, $table, $where, $cols, $search, $perPage, ($page - 1) * $perPage, $sort, $dir, $extra);

// ---- which student is being edited / getting a parent ----
$editId   = (int)($_GET['edit'] ?? 0);
$assignId = (int)($_GET['assign'] ?? 0);

// ---- guardian list (only loaded while choosing a parent) ----
$assignStudent     = null;
$currentGuardianId = 0;
$gsearch           = trim($_GET['gsearch'] ?? '');
$gRows             = [];
$gPage             = 1;
$gTotalPages       = 1;

if ($assignId) {
    $assignStudent = FindById($pdo, $table, $assignId);

    // forget it if the student does not exist or is from another school year
    if (!$assignStudent || $assignStudent['school_year_id'] != $year['id']) {
        $assignStudent = null;
        $assignId      = 0;
    }
}

if ($assignId) {
    $currentGuardianId = (int)($assignStudent['guardian_id'] ?? 0);

    $gCols = ['fname', 'lname', 'phone'];

    // the children of each guardian, shown as one text value
    $gExtra = [
        'children' => "SELECT GROUP_CONCAT(s.fname || ' ' || s.lname, ', ')
                       FROM students s
                       WHERE s.guardian_id = guardians.id"
    ];

    $gTotal      = CountList($pdo, 'guardians', $where, $gCols, $gsearch);
    $gTotalPages = max(1, (int)ceil($gTotal / $perPage));
    $gPage       = min(max(1, (int)($_GET['gPageNum'] ?? 1)), $gTotalPages);

    $gRows = ListRecords($pdo, 'guardians', $where, $gCols, $gsearch, $perPage, ($gPage - 1) * $perPage, 'id', 'ASC', $gExtra);
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
    'gsearch'      => $gsearch,
    'gPageNum'     => $gPage,
];
?>

<div hx-boost="true" hx-target="#main" hx-push-url="false" hx-indicator="#global-spinner">

    <h3>Students <small class="text-muted fs-6"><?= htmlspecialchars($year['name']) ?></small></h3>
    <div id="message"><?= $message ?? '' ?></div>

    <!-- CREATE (the parent is assigned later from the table) -->
    <form hx-post="<?= $handler ?>" class="mb-4">
        <input type="hidden" name="action" value="create">
        <?php hiddenFields($state); ?>
        <div class="row mb-3">
            <div class="col">
                <input name="fname" placeholder="First name" class="form-control" required>
            </div>
            <div class="col">
                <input name="lname" placeholder="Last name" class="form-control" required>
            </div>
            <div class="col">
                <input name="grade_level" placeholder="Grade" class="form-control">
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
                       value="<?= htmlspecialchars($search) ?>" placeholder="Search students...">
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
                <?php hiddenFields($state, ['PerPageLimit', 'PageNum', 'gPageNum']); ?>
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

    <!-- STUDENT TABLE -->
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
            <tr><td colspan="6" class="text-center text-muted">No students found.</td></tr>
        <?php endif; ?>

        <?php foreach ($rows as $r): ?>
            <?php
            $parentName  = $r['guardian_name'] ?? 'No parent yet';
            $parentClass = $r['guardian_id'] ? '' : 'text-muted';
            $assignLabel = $r['guardian_id'] ? 'Change parent' : 'Assign parent';
            ?>

            <?php if ($r['id'] == $editId): ?>

                <!-- Row type 1: this student is being edited -->
                <tr>
                    <td><?= $r['id'] ?></td>
                    <td><input form="editForm" name="fname" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($r['fname']) ?>" required></td>
                    <td><input form="editForm" name="lname" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($r['lname']) ?>" required></td>
                    <td><input form="editForm" name="grade_level" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($r['grade_level'] ?? '') ?>"></td>
                    <td class="<?= $parentClass ?>"><?= htmlspecialchars($parentName) ?></td>
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
                    <td><?= htmlspecialchars($r['fname']) ?></td>
                    <td><?= htmlspecialchars($r['lname']) ?></td>
                    <td><?= htmlspecialchars($r['grade_level'] ?? '') ?></td>
                    <td class="<?= $parentClass ?>"><?= htmlspecialchars($parentName) ?></td>
                    <td class="text-nowrap">
                        <a href="<?= makeUrl($self, $state, ['edit' => $r['id'], 'assign' => null]) ?>"
                           class="btn btn-sm btn-outline-primary">Edit</a>

                        <?php if ($r['id'] == $assignId): ?>
                            <a href="<?= makeUrl($self, $state, ['assign' => null, 'gsearch' => null, 'gPageNum' => null]) ?>"
                               class="btn btn-sm btn-secondary">Done</a>
                        <?php else: ?>
                            <a href="<?= makeUrl($self, $state, ['assign' => $r['id'], 'edit' => null, 'gsearch' => null, 'gPageNum' => null]) ?>"
                               class="btn btn-sm btn-outline-success"><?= $assignLabel ?></a>
                        <?php endif; ?>

                        <button class="btn btn-sm btn-outline-danger"
                                hx-post="<?= $handler ?>"
                                hx-vals='<?= postValues($state, ['action' => 'delete', 'id' => $r['id']]) ?>'
                                hx-confirm="Delete this student?">Delete</button>
                    </td>
                </tr>

            <?php endif; ?>
        <?php endforeach; ?>
    </table>

    <?php renderPagination(makeUrl($self, $state, []), $page, "PageNum", $totalPages); ?>


    <!-- GUARDIANS (only shown while choosing a parent for a student) -->
    <?php if ($assignStudent): ?>
        <div id="guardiansDiv" class="mt-4">
            <h5>Parent for <?= htmlspecialchars($assignStudent['fname'] . ' ' . $assignStudent['lname']) ?></h5>

            <!-- Remove the parent the student has now -->
            <?php if ($currentGuardianId): ?>
                <form hx-post="<?= $handler ?>" class="mb-3">
                    <input type="hidden" name="action" value="unassign">
                    <input type="hidden" name="id" value="<?= $assignId ?>">
                    <?php hiddenFields($state); ?>
                    <button class="btn btn-sm btn-outline-danger">Remove current parent</button>
                </form>
            <?php endif; ?>

            <!-- SEARCH guardians -->
            <form method="get" action="<?= $self ?>" class="mb-3 d-flex gap-2">
                <?php hiddenFields($state, ['gsearch', 'gPageNum']); ?>
                <input type="text" name="gsearch" class="form-control w-auto"
                       value="<?= htmlspecialchars($gsearch) ?>" placeholder="Search guardians...">
                <button class="btn btn-outline-primary">Find</button>
                <?php if ($gsearch !== ''): ?>
                    <a href="<?= makeUrl($self, $state, ['gsearch' => '', 'gPageNum' => null]) ?>"
                       class="btn btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </form>

            <!-- GUARDIAN TABLE -->
            <table class="table align-middle">
                <tr>
                    <th>Guardian</th>
                    <th>Phone</th>
                    <th>Children</th>
                    <th></th>
                </tr>

                <?php if (!$gRows): ?>
                    <tr><td colspan="4" class="text-center text-muted">No guardians found.</td></tr>
                <?php endif; ?>

                <?php foreach ($gRows as $g): ?>
                    <?php $isCurrent = ($g['id'] == $currentGuardianId); ?>
                    <tr class="<?= $isCurrent ? 'table-success' : '' ?>">
                        <td><?= htmlspecialchars($g['fname'] . ' ' . $g['lname']) ?></td>
                        <td><?= htmlspecialchars($g['phone'] ?? '') ?></td>
                        <td><?= htmlspecialchars($g['children'] ?? '-') ?></td>
                        <td class="text-end">
                            <?php if ($isCurrent): ?>
                                <span class="badge text-bg-success">Current</span>
                            <?php else: ?>
                                <button class="btn btn-sm btn-success"
                                        hx-post="<?= $handler ?>"
                                        hx-vals='<?= postValues($state, [
                                            'action'      => 'assign',
                                            'id'          => $assignId,
                                            'guardian_id' => $g['id'],
                                        ]) ?>'>Assign</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <?php renderPagination(makeUrl($self, $state, []), $gPage, "gPageNum", $gTotalPages); ?>
        </div>
    <?php endif; ?>

</div>
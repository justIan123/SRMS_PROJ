<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");

$self  = COMPONENTS_URL . 'manage_users.php';
$table = 'users';
// $admins = FindWhere($pdo, 'user_roles', ['role' => 1]);
$isAdminExist = RecordExists($pdo, 'user_roles', 'role', 1);

// Everything belongs to the current school year
$year = CurrentSchoolYear($pdo);
if (!$year) {
    echo alertBox('warning', 'No school year is running. Start one in School Years first.');
    return;
}
$where = [];

// ---- search ----
$search = trim($_GET['search'] ?? '');
$cols   = ['fname', 'lname', 'phone', 'email'];

// ---- sort ----
$headers = ['id' => 'ID', 'fname' => 'First name', 'lname' => 'Last name', 'phone' => 'Phone', 'username' => 'Username', 'email' => 'Email', 'roles' => 'Role'];
$sort = array_key_exists($_GET['sort'] ?? '', $headers) ? $_GET['sort'] : 'id';
$dir  = ($_GET['dir'] ?? '') === 'DESC' ? 'DESC' : 'ASC';
$flip = $dir === 'ASC' ? 'DESC' : 'ASC';

// ---- pagination ----
$perPage    = (int)($_GET['PerPageLimit'] ?? 10);
$total      = CountList($pdo, $table, $where, $cols, $search);
$totalPages = max(1, ceil($total / $perPage));
$page       = min(max(1, (int)($_GET['PageNum'] ?? 1)), $totalPages);

$extra = [
    'roles' => "SELECT string_agg(r.role, ', ')
                FROM user_roles ur
                JOIN roles r ON r.role_id = ur.role
                WHERE ur.user_id = users.id"
];

$rows   = ListRecords($pdo, $table, $where, $cols, $search, $perPage, ($page - 1) * $perPage, $sort, $dir, $extra);

$editId = (int)($_GET['editId'] ?? 0);

// Current state of the page. makeUrl() builds every link from this,
// and the edit/delete requests send it back so the page looks the same afterwards.
$state = [
    'sort'         => $sort,
    'dir'          => $dir,
    'search'       => $search,
    'PerPageLimit' => $perPage,
    'PageNum'      => $page,
];
?>

<div hx-boost="true" hx-target="#main" hx-push-url="false" hx-indicator="#global-spinner">

    <h3>Teachers <small class="text-muted fs-6"><?= htmlspecialchars($year['name']) ?></small></h3>
    <div id="message"><?= $message ?? '' ?></div>

    <!-- CREATE -->
    <form hx-post="<?= HANDLERS_URL . 'userProcess.php' ?>" class="mb-4 gap-2">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="school_year_id" value="<?= $year = CurrentSchoolYear($pdo); ?>">
        <div class="row mb-3">
            <div class="col">
                <input name="fname" placeholder="First name" class="form-control" required>
            </div>
            <div class="col">
                <input name="lname" placeholder="Last name" class="form-control" required>
            </div>
            <div class="col">
                <input name="phone" placeholder="Phone" class="form-control">
            </div>
        </div>
        <div class="row mb-3">
            <div class="col">
                <input name="username" type="text" placeholder="Username" class="form-control">
            </div>
            <div class="col">
                <input name="email" type="email" placeholder="Email" class="form-control" required>
            </div>
            <div class="col d-flex justify-content-end">
                <select class="form-select">
                    <option value="2">Teacher</option>
                    <option value="3">Employee</option>
                </select>
                <button class="btn btn-primary">Create</button>
            </div>
        </div>
    </form>


    <div class="row">
        <div class="col-6">
            <!-- SEARCH -->
            <form method="get" action="<?= $self ?>" class="mb-3 d-flex gap-2">
                <input type="hidden" name="sort" value="<?= $sort ?>">
                <input type="hidden" name="dir" value="<?= $dir ?>">
                <input type="text" name="search" class="form-control w-auto" value="<?= htmlspecialchars($search) ?>" placeholder="Search...">
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

        <div class="form-floating">
            <input
                type="number"
                name="PerPageLimit"
                class="form-control"
                value="<?= htmlspecialchars($perPage) ?>"
                id="limitRows"
                placeholder="Rows">
            <label for="limitRows">No. of rows</label>
        </div>

        <button class="btn btn-outline-primary">
            Set
        </button>

    </form>
</div>
    </div>

    <!-- UPDATE form (only exists while editing) -->
    <?php if ($editId): ?>
        <form id="editForm" hx-post="<?= HANDLERS_URL ?>userProcess.php">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= $editId ?>">
            <?php foreach ($state as $k => $v): ?>
                <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars($v) ?>">
            <?php endforeach; ?>
        </form>
    <?php endif; ?>

    <!-- TABLE -->
    <table class="table">
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
            <tr><td colspan="6" class="text-center text-muted">No Users found.</td></tr>
        <?php endif; ?>

        <?php foreach ($rows as $r): ?>
            <?php if ($r['id'] == $editId): ?>

                <tr>
                    <td><?= $r['id'] ?></td>
                    <td><input class="form-control form-control-sm" form="editForm" name="fname" value="<?= htmlspecialchars($r['fname']) ?>" required></td>
                    <td><input class="form-control form-control-sm" form="editForm" name="lname" value="<?= htmlspecialchars($r['lname']) ?>" required></td>
                    <td><input class="form-control form-control-sm" form="editForm" name="phone" value="<?= htmlspecialchars($r['phone'] ?? '') ?>"></td>
                    <td><input class="form-control form-control-sm" form="editForm" name="username" value="<?= htmlspecialchars($r['username'] ?? '') ?>"></td>
                    <td><?= htmlspecialchars($r['email'] ?? '') ?></td>
                    <!-- <td><input class="form-control form-control-sm" form="editForm" name="email" value="<?= htmlspecialchars($r['roles'] ?? '') ?>"></td> -->
                    <td>
                        <select class="form-select" form="editForm" name="assignRole">
                            <?php if($r['roles'] == "admin"): ?>
                                <option value="3">Teacher</option>
                                <option value="2">Employee</option>
                            <?php elseif($r['roles'] == "teacher"): ?>
                                <option value="3">Teacher</option>
                                <option value="2">Employee</option>
                            <?php else: ?>
                                <option value="2">Employee</option>
                                <option value="3">Teacher</option>
                            <?php endif; ?>
                        </select>
                    </td>
                    <td>
                        <button form="editForm" class="btn btn-sm btn-success">Save</button>
                        <a href="<?= makeUrl($self, $state) ?>" class="btn btn-sm btn-secondary">
                            Cancel
                        </a>
                    </td>
                </tr>

            <?php else: ?>

                <tr>
                    <td><?= $r['id'] ?></td>
                    <td><?= htmlspecialchars($r['fname'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['lname'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['phone'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['username'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['email'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['roles'] ?? '') ?></td>
                    <td>
                        <?php if($r['roles'] == 'admin'): ?>

                        <?php else:?>

                        <a href="<?= makeUrl($self, $state, ['editId' => $r['id']]) ?>" class="btn btn-sm btn-outline-primary">
                            Edit
                        </a>
                        <button class="btn btn-sm btn-outline-danger"
                                hx-post="<?= HANDLERS_URL ?>teacherProcess.php"
                                hx-vals='<?= htmlspecialchars(json_encode($state + ['action' => 'delete', 'id' => $r['id']]), ENT_QUOTES) ?>'
                                hx-confirm="Delete this teacher?">Delete</button>
                        <?php endif?>

                    </td>
                </tr>

            <?php endif; ?>
        <?php endforeach; ?>
    </table>

    <?php renderPagination(makeUrl($self, $state, ['PageNum' => null]), $page, "PageNum", $totalPages); ?>
    <!-- <pre>
        <?php print_r($rows); ?>
    </pre> -->
</div>
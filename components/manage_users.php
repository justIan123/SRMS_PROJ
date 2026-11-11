<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");

$self = COMPONENTS_URL . 'manage_users.php';
$handler = HANDLERS_URL . 'userProcess.php';

// ---- search ----
$search = trim($_GET['search'] ?? '');

// ---- sort (only a key of $headers can ever reach the SQL) ----
$headers = [
        'id' => 'ID',
        'fname' => 'First name',
        'lname' => 'Last name',
        'mname' => 'Middle name',
        'phone' => 'Phone',
        'username' => 'Username',
        'email' => 'Email',
        'roles' => 'Role',
        'is_active' => 'Active'
    ];
$sort = array_key_exists($_GET['sort'] ?? '', $headers) ? $_GET['sort'] : 'id';
$dir  = ($_GET['dir'] ?? '') === 'DESC' ? 'DESC' : 'ASC';
$flip = $dir === 'ASC' ? 'DESC' : 'ASC';

$perPage = max(1, (int)($_GET['PerPageLimit'] ?? 10));

try {
    // is there already an admin?
    $stmt = $pdo->query("SELECT 1 FROM user_roles WHERE role = 1 LIMIT 1");
    $isAdminExist = (bool)$stmt->fetchColumn();

    // the roles that can be given (admin is not offered), for the Role dropdowns
    $roleOptions = $pdo->query("SELECT role_id, role FROM roles WHERE LOWER(role) <> 'admin' ORDER BY role_id")->fetchAll();

    // Everything belongs to the current school year
    $year = CurrentSchoolYear($pdo);
    if (!$year) {
        echo alertBox('warning', 'No school year is running. Start one in School Years first.');
        return;
    }

    // WHERE shared by the count query and the list query (no search = no WHERE)
    $sqlWhere = '';
    $params   = [];
    if ($search !== '') {
        $like     = '%' . $search . '%';
        $sqlWhere = "WHERE (u.fname ILIKE :s1 OR u.lname ILIKE :s2 OR u.phone ILIKE :s3 OR u.email ILIKE :s4)";
        $params   = [':s1' => $like, ':s2' => $like, ':s3' => $like, ':s4' => $like];
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users u $sqlWhere");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $perPage));
    $page       = min(max(1, (int)($_GET['PageNum'] ?? 1)), $totalPages);
    $offset     = ($page - 1) * $perPage;

    // only the columns the page shows (the password column is never loaded),
    // plus each user's roles as one text value ("admin, teacher")
    $stmt = $pdo->prepare("SELECT u.id, u.fname, u.lname, u.mname, u.phone, u.username, u.email, u.is_active,
                                  (SELECT string_agg(r.role, ', ')
                                   FROM user_roles ur
                                   JOIN roles r ON r.role_id = ur.role
                                   WHERE ur.user_id = u.id) AS roles
                           FROM users u
                           $sqlWhere
                           ORDER BY $sort $dir
                           LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    err_log($e->getMessage());
    echo alertBox('danger', 'Could not load the users. Please try again.');
    return;
}

// Current state of the page. makeUrl() builds every link from this,
// and the update/delete requests send it back so the page looks the same afterwards.
$state = [
    'sort'         => $sort,
    'dir'          => $dir,
    'search'       => $search,
    'PerPageLimit' => $perPage,
    'PageNum'      => $page,
];
?>


<div hx-boost="true" hx-target="#main" hx-push-url="false" hx-indicator="#global-spinner, #main">

    <h3>Users <small class="text-muted fs-6"><?= htmlspecialchars($year['name']) ?></small></h3>
    <div id="message"><?= $message ?? '' ?></div>

    <!-- CREATE -->
    <form hx-post="<?= HANDLERS_URL . 'userProcess.php' ?>" class="mb-4">
        <input type="hidden" name="action" value="create">

        <!-- Personal information -->
        <div class="row g-2 mb-2">
            <div class="col-12 col-md">
                <input name="fname" placeholder="First name"
                    class="form-control" required>
            </div>

            <div class="col-12 col-md">
                <input name="lname" placeholder="Last name"
                    class="form-control" required>
            </div>

            <div class="col-12 col-md">
                <input name="phone" placeholder="Phone"
                    class="form-control">
            </div>
        </div>

        <!-- Account information -->
        <div class="row g-2 mb-2">
            <div class="col-12 col-md">
                <input name="username" type="text"
                    placeholder="Username" class="form-control">
            </div>

            <div class="col-12 col-md">
                <input name="email" type="email"
                    placeholder="Email" class="form-control" required>
            </div>

            <div class="col-12 col-md">
                <input name="password" type="password"
                    placeholder="Password" class="form-control" required>
            </div>
        </div>

        <!-- Role and button -->
        <div class="row g-2">
            <div class="col-12 col-md-4">
                <select name="assignRole" class="form-select" required>
                    <?php foreach ($roleOptions as $opt): ?>
                        <option value="<?= $opt['role_id'] ?>">
                            <?= htmlspecialchars(ucfirst($opt['role'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

        <div class="col-12 col-md-auto ms-auto d-grid">
            <button class="btn btn-primary">Create User</button>
        </div>
    </form>


    <div class="row g-2">
        <!-- SEARCH -->
    <div class="col-12 col-md-6">
        <form method="get" action="<?= $self ?>"
            class="mb-3 d-flex gap-2">

            <input type="hidden" name="sort" value="<?= $sort ?>">
            <input type="hidden" name="dir" value="<?= $dir ?>">

            <input type="text" name="search"
                class="form-control"
                value="<?= htmlspecialchars($search) ?>"
                placeholder="Search...">

            <button class="btn btn-outline-primary">Search</button>

            <?php if ($search !== ''): ?>
                <a href="<?= makeUrl($self, $state, ['search' => '', 'PageNum' => null]) ?>"
                class="btn btn-outline-secondary">
                    Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

        <div class="col-12 col-md-6 d-flex justify-content-md-end">
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

    <!-- TABLE -->
    <div class="table-responsive">
        <table class="table table-sm align-middle text-nowrap">
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
                <tr><td colspan="10" class="text-center text-muted">No Users found.</td></tr>
            <?php endif; ?>

            <?php foreach ($rows as $r): ?>
                <?php
                $formId  = 'editForm' . $r['id'];
                $isAdmin = ($r['roles'] == 'admin');
                ?>

                <!-- Row type 1: a normal row -->
                <tr>
                    <td><?= $r['id'] ?></td>
                    <td><?= htmlspecialchars($r['fname'] ?? 'NONE') ?></td>
                    <td><?= htmlspecialchars($r['lname'] ?? 'NONE') ?></td>
                    <td><?= htmlspecialchars($r['mname'] ?? 'NONE') ?></td>
                    <td><?= htmlspecialchars($r['phone'] ?? 'NONE') ?></td>
                    <td><?= htmlspecialchars($r['username'] ?? 'NONE') ?></td>
                    <td><?= htmlspecialchars($r['email'] ?? 'NONE') ?></td>
                    <td><?= htmlspecialchars($r['roles'] ?? 'NONE') ?></td>
                    <td>
                        <?= renderStatusBadge(htmlspecialchars($r['is_active'] ?? '')) ?>
                    </td>
                    <?php if (!$isAdmin): ?>
                        <td class="d-flex justify-content-center gap-1 flex-wrap">
                            <!-- Edit only swaps the rows in the browser, no request is sent -->
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleEdit(this)">Edit</button>
                            <!-- <button class="btn btn-sm btn-outline-danger"
                                    hx-post="<?= HANDLERS_URL ?>userProcess.php"
                                    hx-vals='<?= htmlspecialchars(json_encode($state + ['action' => 'delete', 'id' => $r['id']]), ENT_QUOTES) ?>'
                                    hx-confirm="Delete this user?">Delete</button> -->
                            <button class="btn btn-sm btn-outline-danger dropdown-toggle"
                                    type="button"
                                    data-bs-toggle="dropdown"
                                    aria-expanded="false">
                                Status
                            </button>
        
                            <ul class="dropdown-menu">
                                <li>
                                    <button class="dropdown-item"
                                            hx-post="<?= $handler ?>"
                                            hx-vals='<?= postValues($state, ["action" => "status", "id" => $r["id"], "status" => 1]) ?>'
                                            hx-confirm="Set this user to Active?">
                                        Active
                                    </button>
                                </li>
        
                                <li>
                                    <button class="dropdown-item"
                                            hx-post="<?= $handler ?>"
                                            hx-vals='<?= postValues($state, ["action" => "status", "id" => $r["id"], "status" => 0]) ?>'
                                            hx-confirm="Set this user to Suspended?">
                                        Suspend
                                    </button>
                                </li>
                            </ul>
                        </td>
                    <?php endif; ?>
                </tr>

                <!-- Row type 2: the edit row, hidden until Edit is pressed (the admin can't be edited, so no edit row) -->
                <?php if (!$isAdmin): ?>
                    <tr class="edit-row d-none">
                        <td>
                            <?= $r['id'] ?>
                            <!-- UPDATE form of this row (the inputs below belong to it through form="...") -->
                            <form id="<?= $formId ?>" hx-post="<?= HANDLERS_URL ?>userProcess.php">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <?php foreach ($state as $k => $v): ?>
                                    <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars($v) ?>">
                                <?php endforeach; ?>
                            </form>
                        </td>
                        <td><input class="form-control form-control-sm" form="<?= $formId ?>" name="fname" value="<?= htmlspecialchars($r['fname']) ?>" required></td>
                        <td><input class="form-control form-control-sm" form="<?= $formId ?>" name="lname" value="<?= htmlspecialchars($r['lname']) ?>" required></td>
                        <td><input class="form-control form-control-sm" form="<?= $formId ?>" name="mname" value="<?= htmlspecialchars($r['mname']) ?>" required></td>
                        <td><input class="form-control form-control-sm" form="<?= $formId ?>" name="phone" value="<?= htmlspecialchars($r['phone'] ?? '') ?>"></td>
                        <td><input class="form-control form-control-sm" form="<?= $formId ?>" name="username" value="<?= htmlspecialchars($r['username'] ?? '') ?>"></td>
                        <td><?= htmlspecialchars($r['email'] ?? '') ?></td>
                        <td>
                            <select class="form-select" form="<?= $formId ?>" name="assignRole">
                                <option value="">--</option>
                                <?php foreach ($roleOptions as $opt): ?>
                                    <option value="<?= $opt['role_id'] ?>" <?= $opt['role'] === $r['roles'] ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($opt['role'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <button form="<?= $formId ?>" class="btn btn-sm btn-success">Save</button>
                            <!-- Cancel swaps back and clears what was typed, no request is sent -->
                            <button type="button" class="btn btn-sm btn-secondary" onclick="toggleEdit(this)">Cancel</button>
                        </td>
                    </tr>
                <?php endif; ?>

            <?php endforeach; ?>
        </table>
    </div>

    <?php renderPagination(makeUrl($self, $state, ['PageNum' => null]), $page, "PageNum", $totalPages); ?>

    <script>
        // Edit and Cancel buttons: show one row, hide the other. No request is sent.
        function toggleEdit(button) {
            const row     = button.closest('tr');
            const isEdit  = row.classList.contains('edit-row');
            const otherRow = isEdit ? row.previousElementSibling : row.nextElementSibling;

            // Cancel: put the inputs back to what the database has
            if (isEdit) {
                const input = row.querySelector('[form]');
                if (input) input.form.reset();
            }

            row.classList.add('d-none');
            otherRow.classList.remove('d-none');
        }
    </script>
</div>
<?php
// =====================================================
// readUsers.php
// Shows the users table: search, sort, pages.
// Click "Edit" and that row switches to input boxes.
// Edit and Cancel happen in the browser (NO server request).
// Only "Save" talks to the server.
// =====================================================
require_once("../public/initialize.php");
require_once(CONFIG_PATH . DS . "db_sqlite.php");
require_once(FUNCTIONS_PATH . DS . "securityFunctions.php");
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php");

// ---------- 1. Read what the page asked for ----------
$search = trim($_GET['search'] ?? '');
$page   = max((int)($_GET['page'] ?? 1), 1);
$limit  = 10; // users per page

// Which user's edit row should start OPEN? (0 = none)
// Only used when a save failed, so the person can fix their mistake.
$editId = (int)($_GET['edit'] ?? 0);

$users      = [];
$totalPages = 1;

// ---------- 2. Sorting (only these columns are allowed) ----------
$sortable = [
    'id'       => 'id',
    'username' => 'username',
    'email'    => 'email',
];

$sort = $_GET['sort'] ?? 'id';
if (!isset($sortable[$sort])) {
    $sort = 'id';
}
$dir     = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
$orderBy = $sortable[$sort];

$thisFile = HANDLERS_URL . 'readUsers.php';

// ---------- 3. A helper that makes a clickable column title ----------
if (!function_exists('sortHeader')) {
    function sortHeader(string $label, string $key, string $curSort, string $curDir, string $search, string $file): string
    {
        $nextDir = ($curSort === $key && $curDir === 'ASC') ? 'DESC' : 'ASC';
        $arrow   = ($curSort === $key) ? ($curDir === 'ASC' ? ' ▲' : ' ▼') : '';
        $url     = $file . '?' . http_build_query([
            'search' => $search,
            'sort'   => $key,
            'dir'    => $nextDir,
            'page'   => 1,
        ]);

        return '<th style="cursor:pointer" hx-get="' . htmlspecialchars($url) . '"'
             . ' hx-target="#user-table-container" hx-swap="innerHTML">'
             . htmlspecialchars($label . $arrow) . '</th>';
    }
}

// ---------- 4. Get the users from the database ----------
try {
    $searchColumns = ['username', 'email'];

    $total = ($search === '')
        ? CountRecords($pdo, 'users')
        : CountSearchRecords($pdo, 'users', $searchColumns, $search);

    $totalPages = max((int) ceil($total / $limit), 1);
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $limit;

    $users = ($search === '')
        ? FindAll($pdo, 'users', $limit, $offset, $orderBy, $dir)
        : SearchRecords($pdo, 'users', $searchColumns, $search, $limit, $offset, $orderBy, $dir);

    // Give each user their role (1 = Admin, 2 = Teacher)
    foreach ($users as $i => $user) {
        $roleRows = FindWhere($pdo, 'user_roles', ['user_id' => $user['id']], 1);
        $users[$i]['role'] = $roleRows[0]['role'] ?? null;
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    $users      = [];
    $totalPages = 1;
}
?>

<?php if (!empty($error)): ?>
    <!-- A message from updateUsersProcess.php, like "Username is already taken." -->
    <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- ================= THE TABLE ================= -->
<div class="table-responsive">
    <table class="table table-bordered table-striped align-middle">
        <thead class="table-dark">
            <tr>
                <?= sortHeader('ID',       'id',       $sort, $dir, $search, $thisFile); ?>
                <?= sortHeader('Username', 'username', $sort, $dir, $search, $thisFile); ?>
                <?= sortHeader('Email',    'email',    $sort, $dir, $search, $thisFile); ?>
                <th>Role</th>
                <th class="text-center">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($users)): ?>
                <?php foreach ($users as $user): ?>
                    <?php
                    $uid       = (int)$user['id'];
                    $isEditing = ($uid === $editId);   // true only after a failed save

                    // If a save failed, show what the person typed. Otherwise show the saved values.
                    $nameNow  = $isEditing ? ($_POST['username'] ?? $user['username']) : $user['username'];
                    $emailNow = $isEditing ? ($_POST['email']    ?? $user['email'])    : $user['email'];
                    $roleNow  = $isEditing ? (int)($_POST['role'] ?? $user['role'] ?? 0) : (int)($user['role'] ?? 0);
                    ?>

                    <!-- ===== ROW 1: the normal row (d-none = hidden) ===== -->
                    <tr id="view-<?= $uid; ?>" class="<?= $isEditing ? 'd-none' : ''; ?>">
                        <td><?= htmlspecialchars($user['id']); ?></td>
                        <td><?= htmlspecialchars($user['username']); ?></td>
                        <td><?= htmlspecialchars($user['email']); ?></td>
                        <td>
                            <?php if ($user['role'] === null): ?>
                                <span class="badge bg-light text-dark">No role</span>
                            <?php elseif ((int)$user['role'] === 1): ?>
                                <span class="badge bg-danger">Admin</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Teacher</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <!-- DELETE -->
                            <button class="btn btn-sm btn-outline-danger"
                                    hx-delete="<?= HANDLERS_URL . 'deleteUserProcess.php?id=' . $uid; ?>"
                                    hx-target="#user-table-container"
                                    hx-confirm="Are you sure you want to delete this user?">
                                Delete
                            </button>
                            <!-- EDIT: hides this row and shows the edit row. No server request. -->
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    onclick="toggleEdit(<?= $uid; ?>)">
                                Edit
                            </button>
                        </td>
                    </tr>

                    <!-- the edit row (hidden until you click Edit) ===== -->
                    <tr id="edit-<?= $uid; ?>" class="table-warning <?= $isEditing ? '' : 'd-none'; ?>">
                        <td colspan="5">

                            <!-- Save sends this form to updateUsersProcess.php -->
                            <form hx-post="<?= HANDLERS_URL . 'updateUserProcess.php'; ?>"
                                  hx-target="#user-table-container"
                                  hx-swap="innerHTML">

                                <!-- hidden: which user, and which page/sort/search we are on -->
                                <input type="hidden" name="id"     value="<?= $uid; ?>">
                                <input type="hidden" name="search" value="<?= htmlspecialchars($search); ?>">
                                <input type="hidden" name="sort"   value="<?= htmlspecialchars($sort); ?>">
                                <input type="hidden" name="dir"    value="<?= htmlspecialchars($dir); ?>">
                                <input type="hidden" name="page"   value="<?= $page; ?>">

                                <div class="row g-2 align-items-center">
                                    <div class="col-12 col-md-3">
                                        <input type="text" name="username" class="form-control form-control-sm"
                                               placeholder="Username"
                                               value="<?= htmlspecialchars($nameNow); ?>" required>
                                    </div>
                                    <div class="col-12 col-md-3">
                                        <input type="email" name="email" class="form-control form-control-sm"
                                               placeholder="Email"
                                               value="<?= htmlspecialchars($emailNow); ?>" required>
                                    </div>
                                    <div class="col-12 col-md-2">
                                        <select name="role" class="form-select form-select-sm" required>
                                            <option value="">Select role</option>
                                            <option value="1" <?= $roleNow === 1 ? 'selected' : ''; ?>>Admin</option>
                                            <option value="2" <?= $roleNow === 2 ? 'selected' : ''; ?>>Teacher</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-4 text-md-end">
                                        <button type="submit" class="btn btn-sm btn-success">Save</button>
                                        <!-- CANCEL: puts the old text back, then switches rows. No server request. -->
                                        <button type="button" class="btn btn-sm btn-secondary"
                                                onclick="this.form.reset(); toggleEdit(<?= $uid; ?>)">
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            </form>

                        </td>
                    </tr>

                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center text-muted">No users found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>


<nav>
    <ul class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php
            $pageUrl = $thisFile . '?' . http_build_query([
                'search' => $search,
                'sort'   => $sort,
                'dir'    => $dir,
                'page'   => $i,
            ]);
            ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                <button class="page-link"
                        hx-get="<?= htmlspecialchars($pageUrl); ?>"
                        hx-target="#user-table-container"
                        hx-swap="innerHTML">
                    <?= $i ?>
                </button>
            </li>
        <?php endfor; ?>
    </ul>
</nav>

<script>
// Switch between the normal row and the edit row of one user.
// "d-none" is a Bootstrap class that hides something.
// toggle = if it is hidden, show it. If it is showing, hide it.
function toggleEdit(id) {
    document.getElementById('view-' + id).classList.toggle('d-none');
    document.getElementById('edit-' + id).classList.toggle('d-none');
}
</script>
<?php
require_once("../public/initialize.php");
require_once(CONFIG_PATH . DS . "db_sqlite.php");
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php");

$self = COMPONENTS_URL . 'manage_users.php';
// http://localhost/1_SRMS/components/manage_users.php

// ---- search ----
$search = trim($_GET['search'] ?? '');
$cols   = ['username', 'email'];

// ---- sort ----
$allowed = ['id', 'username', 'email'];
$sort = in_array($_GET['sort'] ?? '', $allowed) ? $_GET['sort'] : 'id';
$dir  = ($_GET['dir'] ?? '') === 'DESC' ? 'DESC' : 'ASC';
$flip = $dir === 'ASC' ? 'DESC' : 'ASC';

// ---- pagination ----
$perPage = 10;
$total   = $search !== ''
    ? CountSearchRecords($pdo, 'users', $cols, $search)
    : CountRecords($pdo, 'users');
$totalPages = max(1, ceil($total / $perPage));
$page       = min(max(1, (int)($_GET['page'] ?? 1)), $totalPages);
$offset     = ($page - 1) * $perPage;

$users = $search !== ''
    ? SearchRecords($pdo, 'users', $cols, $search, $perPage, $offset, $sort, $dir)
    : FindAll($pdo, 'users', $perPage, $offset, $sort, $dir);

$editId = (int)($_GET['edit'] ?? 0);

$s    = '&search=' . urlencode($search);               // keeps the search in every link
$base = $self . "?sort=$sort&dir=$dir" . $s;           // keeps sort + search when changing page

// page buttons: show 2 pages before and after the current one
$start = max(1, $page - 2);
$end   = min($totalPages, $page + 2);

// values the update/delete handlers need to come back to the same view
$keep = ['sort' => $sort, 'dir' => $dir, 'page' => $page, 'search' => $search];
?>

<div hx-boost="true" hx-target="#main" hx-push-url="false" hx-indicator="#global-spinner">

    <h3>Manage Users</h3>
    <div id="message"><?=  isset($msg)? $msg : "" ?></div>

    <!-- CREATE -->
    <form hx-post="<?= HANDLERS_URL ?>createUserProcess.php" class="mb-4">
        <input name="username" placeholder="Username" required>
        <input name="email" type="email" placeholder="Email">
        <input name="password" type="password" placeholder="Password">
        <button class="btn btn-primary">Create</button>
    </form>

    <!-- SEARCH -->
    <form method="get" action="<?= $self ?>" class="mb-3 d-flex gap-2">
        <input type="hidden" name="sort" value="<?= $sort ?>">
        <input type="hidden" name="dir" value="<?= $dir ?>">
        <input type="text" name="search" class="form-control w-auto"
               value="<?= htmlspecialchars($search) ?>" placeholder="Search username or email...">
        <button class="btn btn-outline-primary">Search</button>
        <?php if ($search !== ''): ?>
            <a href="<?= $self ?>?sort=<?= $sort ?>&dir=<?= $dir ?>" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <!-- update form (only exists while editing; the row inputs point to it) -->
    <?php if ($editId): ?>
        <form id="editForm" hx-post="<?= HANDLERS_URL ?>updateUserProcess.php">
            <input type="hidden" name="id" value="<?= $editId ?>">
            <?php foreach ($keep as $k => $v): ?>
                <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars($v) ?>">
            <?php endforeach; ?>
        </form>
    <?php endif; ?>

    <!-- TABLE -->
    <table class="table">
        <tr>
            <th><a href="<?= $self ?>?sort=id&dir=<?= $flip . $s ?>">ID</a></th>
            <th><a href="<?= $self ?>?sort=username&dir=<?= $flip . $s ?>">Username</a></th>
            <th><a href="<?= $self ?>?sort=email&dir=<?= $flip . $s ?>">Email</a></th>
            <th>Actions</th>
        </tr>

        <?php if (!$users): ?>
            <tr><td colspan="4" class="text-center text-muted">No users found.</td></tr>
        <?php endif; ?>

        <?php foreach ($users as $u): ?>
            <?php if ($u['id'] == $editId): ?>
                <!-- edit -->
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><input form="editForm" name="username" value="<?= htmlspecialchars($u['username']) ?>" required></td>
                    <td><input form="editForm" name="email" value="<?= htmlspecialchars($u['email'] ?? '') ?>"></td>
                    <td>
                        <button form="editForm" class="btn btn-sm btn-success">Save</button>
                        <a href="<?= $base ?>&page=<?= $page ?>" class="btn btn-sm btn-secondary">Cancel</a>
                    </td>
                </tr>

            <?php else: ?>

                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
                    <td>
                        <a href="<?= $base ?>&page=<?= $page ?>&edit=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>

                        <!-- DELETE -->
                        <button class="btn btn-sm btn-outline-danger"
                                hx-post="<?= HANDLERS_URL ?>deleteUserProcess.php"
                                hx-vals='<?= htmlspecialchars(json_encode($keep + ['id' => $u['id']]), ENT_QUOTES) ?>'
                                hx-confirm="Delete this user?">
                                Delete
                                <!-- <?= htmlspecialchars(json_encode($keep + ['id' => $u['id']]), ENT_QUOTES) ?>' -->
                                <!-- {"sort":"id","dir":"ASC","page":1,"search":"","id":14}' {"sort":"id","dir":"ASC","page":1,"search":"","id":18}' -->
                        </button>
                    </td>
                </tr>

            <?php endif; ?>
        <?php endforeach; ?>
    </table>

    <!-- PAGINATION -->
    <nav>
        <ul class="pagination">

            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <!-- get to prev page -->
                <!-- http://localhost/1_SRMS/components/manage_users.php?sort=id&dir=ASC&search=&page=0 -->
                <a class="page-link" href="<?= $base ?>&page=<?= $page - 1 ?>">&laquo;</a>
            </li>

            <?php for ($i = $start; $i <= $end; $i++): ?>
                <!-- select specific page -->
                <!-- http://localhost/1_SRMS/components/manage_users.php?sort=id&dir=ASC&search=&page=1 -->
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= $base ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>

            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <!-- get to next page -->
                <!-- http://localhost/1_SRMS/components/manage_users.php?sort=id&dir=ASC&search=&page=1 -->
                <a class="page-link" href="<?= $base ?>&page=<?= $page + 1 ?>">&raquo;</a>
            </li>

        </ul>
    </nav>

</div>
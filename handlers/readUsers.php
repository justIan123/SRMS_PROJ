<?php
require_once("../public/initialize.php");
require_once(CONFIG_PATH . DS . "db_sqlite.php");
require_once(FUNCTIONS_PATH . DS . "securityFunctions.php");
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php"); // must contain the CRUD helper functions (crud.php)

$search  = trim($_GET['search'] ?? '');
$page    = max((int)($_GET['page'] ?? 1), 1);
$limit   = 10;

$users      = [];
$totalPages = 1;

try {
    $searchColumns = ['username', 'email'];

    // 1. Count the total rows so we know how many pages there are
    $total = ($search === '')
        ? CountRecords($pdo, 'users')
        : CountSearchRecords($pdo, 'users', $searchColumns, $search);

    $totalPages = max((int) ceil($total / $limit), 1);
    $page       = min($page, $totalPages);        // don't go past the last page
    $offset     = ($page - 1) * $limit;

    // 2. Get only the rows for the current page
    $users = ($search === '')
        ? FindAll($pdo, 'users', $limit, $offset, 'id', 'ASC')
        : SearchRecords($pdo, 'users', $searchColumns, $search, $limit, $offset, 'id', 'ASC');

    // 3. Attach each user's role from the user_roles table
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

<!-- HTML Response Returned to #user-table-container -->
<div class="table-responsive">
    <table class="table table-bordered table-striped align-middle">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th class="text-center">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($users)): ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['id']); ?></td>
                        <td><?= htmlspecialchars($user['username']); ?></td>
                        <td><?= htmlspecialchars($user['email']); ?></td>
                        <td>
                            <?php if ($user['role'] === null): ?>
                                <span class="badge bg-light text-dark">No role</span>
                            <?php else: ?>
                                <span class="badge <?= ($user['role'] == 1) ? 'bg-danger' : 'bg-secondary'; ?>">
                                    <?= ($user['role'] == 1) ? 'Admin' : 'Teacher'; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-danger"
                                    hx-delete="<?= HANDLERS_URL . 'deleteUserProcess.php?id=' . $user['id']; ?>"
                                    hx-target="#user-table-container"
                                    hx-confirm="Are you sure you want to delete this user?">
                                Delete
                            </button>
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
      <li class="page-item <?= $i == $page ? 'active' : '' ?>">
        <button class="page-link"
                hx-get="<?= HANDLERS_URL . 'readUsers.php?page=' . $i ?>"
                hx-target="#user-table-container"
                hx-include="[name='search']">
          <?= $i ?>
        </button>
      </li>
    <?php endfor; ?>
  </ul>
</nav>
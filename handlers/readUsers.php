<?php
require_once("../public/index.php");
require_once(CONFIG_PATH . DS . "db_sqlite.php");
require_once(FUNCTIONS_PATH . DS . "securityFunctions.php");
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php");

$page   = max((int)($_GET['page'] ?? 1), 1);
$search = trim($_GET['search'] ?? '');
$limit  = 10;
$offset = ($page - 1) * $limit;


try {
$result = TableSearchPaginate1(
    'users',

    [
        'INNER JOIN user_roles
            ON users.id = user_roles.user_id',

        'INNER JOIN roles
            ON user_roles.role = roles.role_id'
    ],

    [
        'users.username',
        'users.email',
        'roles.role'
    ],

    $_GET['search'] ?? '',
    $_GET['page'] ?? 1,
    10,
    $pdo
);

} catch (PDOException $e) {
    $result = [];
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
            <?php if (!empty($result)): ?>
                <?php foreach ($result['data'] as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['id']); ?></td>
                        <td><?= htmlspecialchars($user['username']); ?></td>
                        <td><?= htmlspecialchars($user['email']); ?></td>
                        <td>
                            <span class="badge <?= ($user['role'] == 1) ? 'bg-danger' : 'bg-secondary'; ?>">
                                <?= ($user['role'] == 1) ? 'Admin' : 'Teacher'; ?>
                            </span>
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

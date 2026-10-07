<?php
require_once("../public/index.php");
require_once(CONFIG_PATH . DS . "db_sqlite.php");
require_once(FUNCTIONS_PATH . DS . "securityFunctions.php");

$page   = max((int)($_GET['page'] ?? 1), 1);
$search = trim($_GET['search'] ?? '');
$role   = trim($_GET['role'] ?? '');
$limit  = 10;
$offset = ($page - 1) * $limit;

// Dynamic filters
$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(u.username LIKE :search OR u.email LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($role !== '') {
    $whereClauses[] = "ur.role = :role";
    $params[':role'] = $role;
}

$whereSql = !empty($whereClauses) ? " WHERE " . implode(" AND ", $whereClauses) : "";

try {
    // 1. Total records count
    $countSql = "SELECT COUNT(DISTINCT u.id) 
                 FROM users u 
                 LEFT JOIN user_roles ur ON u.id = ur.user_id " . $whereSql;
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalUsers = (int) $countStmt->fetchColumn();
    $totalPages = max(ceil($totalUsers / $limit), 1);

    // 2. Query page data
    $dataSql = "SELECT u.id, u.username, u.email, COALESCE(ur.role, 2) as role 
                FROM users u 
                LEFT JOIN user_roles ur ON u.id = ur.user_id " . $whereSql . " 
                ORDER BY u.id DESC 
                LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($dataSql);
    
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $users = [];
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
                            <span class="badge <?= ($user['role'] == 1) ? 'bg-danger' : 'bg-secondary'; ?>">
                                <?= ($user['role'] == 1) ? 'Admin' : 'User'; ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-danger"
                                    hx-delete="<?= HANDLERS_URL . 'deleteUser.php?id=' . $user['id']; ?>"
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

<!-- Pagination Links -->
<?php if ($totalPages > 1): ?>
    <nav aria-label="Page navigation" class="mt-3">
        <ul class="pagination justify-content-center">
            
            <!-- Previous -->
            <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">
                <button class="page-link"
                        hx-get="<?= HANDLERS_URL . 'readUsers.php?page=' . ($page - 1); ?>"
                        hx-target="#user-table-container"
                        hx-include="[name='search'], [name='role']"
                        hx-indicator="#global-spinner">
                    Previous
                </button>
            </li>

            <!-- Page Numbers -->
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= ($page == $i) ? 'active' : ''; ?>">
                    <button class="page-link"
                            hx-get="<?= HANDLERS_URL . 'readUsers.php?page=' . $i; ?>"
                            hx-target="#user-table-container"
                            hx-include="[name='search'], [name='role']"
                            hx-indicator="#global-spinner">
                        <?= $i; ?>
                    </button>
                </li>
            <?php endfor; ?>

            <!-- Next -->
            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : ''; ?>">
                <button class="page-link"
                        hx-get="<?= HANDLERS_URL . 'readUsers.php?page=' . ($page + 1); ?>"
                        hx-target="#user-table-container"
                        hx-include="[name='search'], [name='role']"
                        hx-indicator="#global-spinner">
                    Next
                </button>
            </li>

        </ul>
    </nav>
<?php endif; ?>
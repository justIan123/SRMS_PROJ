<?php
require_once("../public/initialize.php");
require_once(CONFIG_PATH . DS . "db_sqlite.php");
require_once(FUNCTIONS_PATH . DS . "securityFunctions.php");
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php");

$page   = max((int)($_GET['page'] ?? 1), 1);
$search = trim($_GET['search'] ?? '');
$role   = trim($_GET['role'] ?? '');
$limit  = 10;
$offset = ($page - 1) * $limit;

try {
    $result = TableSearchPaginate(
        'guardians',
        ['fname','mname','lname','phone','email'],
        $search,
        $page,
        $limit,
        $pdo
    );


} catch (PDOException $e) {
    $result = [];
    $totalPages = 1;
}
?>

<!-- HTML Response Returned to #user-table-container -->
<div class="table-responsive">
    <table class="table table-light table-hover table-bordered table-striped align-middle">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th class="text-center">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($result)): ?>
                <?php foreach ($result['data'] as $guardian): ?>
                    <tr>
                        <td><?= htmlspecialchars($guardian['id']); ?></td>
                        <td><?= htmlspecialchars($guardian['lname'].", ".$guardian['fname']). " " . $guardian['mname']; ?></td>
                        <td class="formatPhone"><?= htmlspecialchars($guardian['phone']); ?></td>
                        <td><?= htmlspecialchars($guardian['email']); ?></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-danger"
                                    hx-delete="<?= HANDLERS_URL . 'deleteGuardianProcess.php?id=' . $guardian['id']; ?>"
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

<script>
    document.querySelectorAll('.formatPhone').forEach(phone => {
  phone.textContent = phone.textContent.replace(
    /(\d{4})(\d{3})(\d{4})/,
    '$1-$2-$3'
  );
});
</script>
<?php
require_once("../public/initialize.php");
require_once(CONFIG_PATH . DS . "db_sqlite.php");
require_once(FUNCTIONS_PATH . DS . "securityFunctions.php");
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php");
require_once(DEBUG_PATH . DS . "debugFunctions.php");

$search = trim($_GET['search'] ?? '');
$page   = max((int)($_GET['page'] ?? 1), 1);
$limit  = 10;

$guardians  = [];
$totalPages = 1;

//TABLE SORT
// Only these keys can be sorted. The value is the real DB column.
$sortable = [
    'id'    => 'id',
    'name'  => 'lname',   // the Name column sorts by last name
    'phone' => 'phone',
    'email' => 'email',
];

$sort = $_GET['sort'] ?? 'id';
if (!isset($sortable[$sort])) {
    $sort = 'id';
}
$dir     = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
$orderBy = $sortable[$sort];

if (!function_exists('sortHeader')) {
    function sortHeader(string $label, string $key, string $curSort, string $curDir, string $search): string
    {
        $nextDir = ($curSort === $key && $curDir === 'ASC') ? 'DESC' : 'ASC';
        $arrow   = ($curSort === $key) ? ($curDir === 'ASC' ? ' ▲' : ' ▼') : '';
        $url     = strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query([
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
//START PROCESS
try {
    $searchColumns = ['fname', 'mname', 'lname', 'phone', 'email'];

    $total = ($search === '')
        ? CountRecords($pdo, 'guardians')
        : CountSearchRecords($pdo, 'guardians', $searchColumns, $search);

    $totalPages = max((int) ceil($total / $limit), 1);
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $limit;

    $guardians = ($search === '')
        ? FindAll($pdo, 'guardians', $limit, $offset, $orderBy, $dir)
        : SearchRecords($pdo, 'guardians', $searchColumns, $search, $limit, $offset, $orderBy, $dir);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $guardians  = [];
    $totalPages = 1;
}
?>

<div class="table-responsive">
    <table class="table table-light table-hover table-bordered table-striped align-middle">
        <thead class="table-dark">
            <tr>
                <?= sortHeader('ID',    'id',    $sort, $dir, $search); ?>
                <?= sortHeader('Name',  'name',  $sort, $dir, $search); ?>
                <?= sortHeader('Phone', 'phone', $sort, $dir, $search); ?>
                <?= sortHeader('Email', 'email', $sort, $dir, $search); ?>
                <th class="text-center">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($guardians)): ?>
                <?php foreach ($guardians as $guardian): ?>
                    <tr>
                        <td><?= htmlspecialchars($guardian['id']); ?></td>
                        <td><?= htmlspecialchars($guardian['lname'] . ', ' . $guardian['fname'] . ' ' . $guardian['mname']); ?></td>
                        <td class="formatPhone"><?= htmlspecialchars($guardian['phone']); ?></td>
                        <td><?= htmlspecialchars($guardian['email']); ?></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-danger"
                                    hx-delete="<?= HANDLERS_URL . 'deleteGuardianProcess.php?id=' . $guardian['id']; ?>"
                                    hx-target="#user-table-container"
                                    hx-confirm="Are you sure you want to delete this guardian?">
                                Delete
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>    
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center text-muted">No guardians found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
document.querySelectorAll('.formatPhone').forEach(phone => {
    phone.textContent = phone.textContent.replace(/(\d{4})(\d{3})(\d{4})/, '$1-$2-$3');
});
</script>
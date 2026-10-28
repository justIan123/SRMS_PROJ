<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");

$self = COMPONENTS_URL . 'manage_guardians.php';

// ---- search ----
$search = trim($_GET['search'] ?? '');

// ---- sort (only a key of $headers can ever reach the SQL) ----
$headers = ['id' => 'ID', 'fname' => 'First name', 'lname' => 'Last name', 'phone' => 'Phone', 'email' => 'Email'];
$sort = array_key_exists($_GET['sort'] ?? '', $headers) ? $_GET['sort'] : 'id';
$dir   = ($_GET['dir'] ?? '') === 'DESC' ? 'DESC' : 'ASC';
$flip = $dir === 'ASC' ? 'DESC' : 'ASC';

$perPage = 10;

try {
    // Everything belongs to the current school year
    $year = CurrentSchoolYear($pdo);
    if (!$year) {
        echo alertBox('warning', 'No school year is running. Start one in School Years first.');
        return;
    }

    // WHERE shared by the count query and the list query
    $sqlWhere = "WHERE school_year_id = :year";
    $params   = [':year' => $year['id']];
    if ($search !== '') {
        $like      = '%' . $search . '%';
        $sqlWhere .= " AND (fname ILIKE :s1 OR lname ILIKE :s2 OR phone ILIKE :s3 OR email ILIKE :s4)";
        $params   += [':s1' => $like, ':s2' => $like, ':s3' => $like, ':s4' => $like];
    }

    // how many rows match (for the pagination)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM guardians $sqlWhere");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $perPage));
    $page       = min(max(1, (int)($_GET['gpage'] ?? 1)), $totalPages);
    $offset     = ($page - 1) * $perPage;

    // the rows of this page
    $stmt = $pdo->prepare("SELECT * FROM guardians $sqlWhere ORDER BY $sort $dir LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    err_log($e->getMessage());
    echo alertBox('danger', 'Could not load the guardians. Please try again.');
    return;
}

// Current state of the page. makeUrl() builds every link from this,
// and the delete/update requests send it back so the page looks the same afterwards.
$state = [
    'sort'   => $sort,
    'dir'    => $dir,
    'search' => $search,
    'gpage'  => $page,
];
?>

<div hx-boost="true" hx-target="#main" hx-push-url="false" hx-indicator="#global-spinner, #main">

    <h3>Guardians <small class="text-muted fs-6"><?= htmlspecialchars($year['name']) ?></small></h3>
    <div id="message"><?= $message ?? '' ?></div>

    <!-- CREATE -->
    <form hx-post="<?= HANDLERS_URL ?>guardianProcess.php" class="mb-4 d-flex gap-2" hx-target="#main" hx-indicator="#global-spinner, #main">
        <input type="hidden" name="action" value="create">
        <input name="fname" placeholder="First name" class="form-control" required>
        <input name="lname" placeholder="Last name" class="form-control" required>
        <input name="phone" placeholder="Phone" class="form-control">
        <input name="email" type="email" placeholder="Email" class="form-control">
        <button class="btn btn-primary">Create</button>
    </form>

    <!-- SEARCH -->
    <form method="get" action="<?= $self ?>" class="mb-3 d-flex gap-2">
        <input type="hidden" name="sort" value="<?= $sort ?>">
        <input type="hidden" name="dir" value="<?= $dir ?>">
        <input type="text" name="search" class="form-control w-auto" value="<?= htmlspecialchars($search) ?>" placeholder="Search...">
        <button class="btn btn-outline-primary">Search</button>
        <?php if ($search !== ''): ?>
            <a href="<?= makeUrl($self, $state, ['search' => '', 'gpage' => null]) ?>" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <!-- TABLE -->
    <table class="table align-middle">
        <tr>
            <?php foreach ($headers as $col => $label): ?>
                <th><a href="<?= makeUrl($self, $state, ['sort' => $col, 'dir' => $flip, 'gpage' => null]) ?>"><?= $label ?></a></th>
            <?php endforeach; ?>
            <th>Actions</th>
        </tr>

        <?php if (!$rows): ?>
            <tr><td colspan="6" class="text-center text-muted">No guardians found.</td></tr>
        <?php endif; ?>

        <?php foreach ($rows as $r): ?>
            <tr id="row-<?= $r['id'] ?>">
                <td><?= $r['id'] ?></td>
                
                <!-- First Name -->
                <td>
                    <span class="view-mode"><?= htmlspecialchars($r['fname']) ?></span>
                    <input type="text" name="fname" class="form-control form-control-sm edit-mode d-none" value="<?= htmlspecialchars($r['fname']) ?>" required>
                </td>
                
                <!-- Last Name -->
                <td>
                    <span class="view-mode"><?= htmlspecialchars($r['lname']) ?></span>
                    <input type="text" name="lname" class="form-control form-control-sm edit-mode d-none" value="<?= htmlspecialchars($r['lname']) ?>" required>
                </td>
                
                <!-- Phone -->
                <td>
                    <span class="view-mode"><?= htmlspecialchars($r['phone'] ?? '') ?></span>
                    <input type="text" name="phone" class="form-control form-control-sm edit-mode d-none" value="<?= htmlspecialchars($r['phone'] ?? '') ?>">
                </td>
                
                <!-- Email -->
                <td>
                    <span class="view-mode"><?= htmlspecialchars($r['email'] ?? '') ?></span>
                    <input type="email" name="email" class="form-control form-control-sm edit-mode d-none" value="<?= htmlspecialchars($r['email'] ?? '') ?>">
                </td>
                
                <!-- Actions -->
                <td>
                    <!-- View Mode Buttons -->
                    <div class="view-mode">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleRowEdit(<?= $r['id'] ?>, true)">Edit</button>
                        <button class="btn btn-sm btn-outline-danger"
                                hx-post="<?= HANDLERS_URL ?>guardianProcess.php"
                                hx-vals='<?= htmlspecialchars(json_encode($state + ['action' => 'delete', 'id' => $r['id']]), ENT_QUOTES) ?>'
                                hx-confirm="Delete this guardian?"
                                hx-target="#main"
                                hx-indicator="#global-spinner, #main">Delete</button>
                    </div>
                    
                    <!-- Edit Mode Buttons -->
                    <div class="edit-mode d-none">
                        <button type="button" class="btn btn-sm btn-success"
                                hx-post="<?= HANDLERS_URL ?>guardianProcess.php"
                                hx-vals='<?= htmlspecialchars(json_encode($state + ['action' => 'update', 'id' => $r['id']]), ENT_QUOTES) ?>'
                                hx-include="#row-<?= $r['id'] ?> input"
                                hx-target="#main"
                                hx-indicator="#global-spinner, #main">Save</button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="toggleRowEdit(<?= $r['id'] ?>, false)">Cancel</button>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <?php renderPagination(makeUrl($self, $state, ['gpage' => null]), $page, "gpage", $totalPages); ?>

</div>

<script>
function toggleRowEdit(id, showEdit) {
    const row = document.getElementById('row-' + id);
    const viewElements = row.querySelectorAll('.view-mode');
    const editElements = row.querySelectorAll('.edit-mode');
    
    viewElements.forEach(el => el.classList.toggle('d-none', showEdit));
    editElements.forEach(el => el.classList.toggle('d-none', !showEdit));
}
</script>
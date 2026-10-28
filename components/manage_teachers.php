<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");

$self = COMPONENTS_URL . 'manage_teachers.php';
// this is suppose to be teacher but it seems the logic is that the users is already exist
// and the roles of the teacher in the user table is alreadyexist

// ---- search ----
$search = trim($_GET['search'] ?? '');

// ---- sort (only a key of $headers can ever reach the SQL) ----
$headers = ['id' => 'ID', 'fname' => 'First name', 'lname' => 'Last name', 'phone' => 'Phone', 'email' => 'Email'];
$sort = array_key_exists($_GET['sort'] ?? '', $headers) ? $_GET['sort'] : 'id';
$dir  = ($_GET['dir'] ?? '') === 'DESC' ? 'DESC' : 'ASC';
$flip = $dir === 'ASC' ? 'DESC' : 'ASC';

$perPage = max(1, (int)($_GET['PerPageLimit'] ?? 10));
$editId  = (int)($_GET['edit'] ?? 0);

try {
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
        $sqlWhere = "WHERE (fname ILIKE :s1 OR lname ILIKE :s2 OR phone ILIKE :s3 OR email ILIKE :s4)";
        $params   = [':s1' => $like, ':s2' => $like, ':s3' => $like, ':s4' => $like];
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users $sqlWhere");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $perPage));
    $page       = min(max(1, (int)($_GET['tpage'] ?? 1)), $totalPages);
    $offset     = ($page - 1) * $perPage;

    // only the columns the page shows, so the password column is never loaded
    $stmt = $pdo->prepare("SELECT id, fname, lname, phone, email
                           FROM users
                           $sqlWhere
                           ORDER BY $sort $dir
                           LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    err_log($e->getMessage());
    echo alertBox('danger', 'Could not load the teachers. Please try again.');
    return;
}

// Current state of the page. makeUrl() builds every link from this,
// and the edit/delete requests send it back so the page looks the same afterwards.
$state = [
    'sort'         => $sort,
    'dir'          => $dir,
    'search'       => $search,
    'PerPageLimit' => $perPage,
    'tpage'        => $page,
];
?>

<div hx-boost="true" hx-target="#main" hx-push-url="false" hx-indicator="#global-spinner">

    <h3>Teachers <small class="text-muted fs-6"><?= htmlspecialchars($year['name']) ?></small></h3>
    <div id="message"><?= $message ?? '' ?></div>

    <!-- CREATE -->
    <form hx-post="<?= HANDLERS_URL ?>teacherProcess.php" class="mb-4 d-flex gap-2">
        <input type="hidden" name="action" value="create">
        <input name="fname" placeholder="First name" class="form-control" required>
        <input name="lname" placeholder="Last name" class="form-control" required>
        <input name="phone" placeholder="Phone" class="form-control">
        <input name="email" type="email" placeholder="Email" class="form-control">
        <button class="btn btn-primary">Create</button>
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
                    <a href="<?= makeUrl($self, $state, ['search' => '', 'tpage' => null]) ?>" class="btn btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="col-6 justify-content-end">
            <!-- limit rows -->
            <form method="get" action="<?= $self ?>" class="mb-3 d-flex gap-2">
                <p> Set Limit the No of rows</p>
                <?php hiddenFields($state, ['PerPageLimit', 'tpage']); ?>
                <input type="number" name="PerPageLimit" class="form-control w-auto"
                    value="<?= htmlspecialchars($perPage) ?>" placeholder="Show limit of Rows...">
                <button class="btn btn-outline-primary">Search</button>
            </form>
        </div>
    </div>

    <!-- UPDATE form (only exists while editing) -->
    <?php if ($editId): ?>
        <form id="editForm" hx-post="<?= HANDLERS_URL ?>teacherProcess.php">
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
                <th><a href="<?= makeUrl($self, $state, ['sort' => $col, 'dir' => $flip, 'tpage' => null]) ?>"><?= $label ?></a></th>
            <?php endforeach; ?>
            <th>Actions</th>
        </tr>

        <?php if (!$rows): ?>
            <tr><td colspan="6" class="text-center text-muted">No teachers found.</td></tr>
        <?php endif; ?>

        <?php foreach ($rows as $r): ?>
            <?php if ($r['id'] == $editId): ?>

                <tr>
                    <td><?= $r['id'] ?></td>
                    <td><input class="form-control form-control-sm" form="editForm" name="fname" value="<?= htmlspecialchars($r['fname']) ?>" required></td>
                    <td><input class="form-control form-control-sm" form="editForm" name="lname" value="<?= htmlspecialchars($r['lname']) ?>" required></td>
                    <td><input class="form-control form-control-sm" form="editForm" name="phone" value="<?= htmlspecialchars($r['phone'] ?? '') ?>"></td>
                    <td><input class="form-control form-control-sm" form="editForm" name="email" value="<?= htmlspecialchars($r['email'] ?? '') ?>"></td>
                    <td>
                        <button form="editForm" class="btn btn-sm btn-success">Save</button>
                        <a href="<?= makeUrl($self, $state) ?>" class="btn btn-sm btn-secondary">Cancel</a>
                    </td>
                </tr>

            <?php else: ?>

                <tr>
                    <td><?= $r['id'] ?></td>
                    <td><?= htmlspecialchars($r['fname']) ?></td>
                    <td><?= htmlspecialchars($r['lname']) ?></td>
                    <td><?= htmlspecialchars($r['phone'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['email'] ?? '') ?></td>
                    <td>
                        <a href="<?= makeUrl($self, $state, ['edit' => $r['id']]) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <button class="btn btn-sm btn-outline-danger"
                                hx-post="<?= HANDLERS_URL ?>teacherProcess.php"
                                hx-vals='<?= htmlspecialchars(json_encode($state + ['action' => 'delete', 'id' => $r['id']]), ENT_QUOTES) ?>'
                                hx-confirm="Delete this teacher?">Delete</button>
                    </td>
                </tr>

            <?php endif; ?>
        <?php endforeach; ?>
    </table>

    <?php renderPagination(makeUrl($self, $state, ['tpage' => null]), $page, "tpage", $totalPages); ?>

</div>
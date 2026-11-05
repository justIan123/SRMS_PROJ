<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");

$self = COMPONENTS_URL . 'manage_guardians.php';
$handler = HANDLERS_URL . 'guardianProcess.php';


// ---- search ----
$search = trim($_GET['search'] ?? '');

// ---- sort (only a key of $headers can ever reach the SQL) ----
$headers = [
        'id' => 'ID',
        'fname' => 'First name',
        'lname' => 'Last name',
        'mname' => 'Middle name',
        'phone' => 'Phone',
        'email' => 'Email',
        'is_active' => 'Active'
    ];
$sort = array_key_exists($_GET['sort'] ?? '', $headers) ? $_GET['sort'] : 'id';
$dir  = ($_GET['dir'] ?? '') === 'DESC' ? 'DESC' : 'ASC';
$flip = $dir === 'ASC' ? 'DESC' : 'ASC';

$perPage = 10;$perPage = max(1, (int)($_GET['PerPageLimit'] ?? 10));

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
// and the update/delete requests send it back so the page looks the same afterwards.
$state = [
    'sort'         => $sort,
    'dir'          => $dir,
    'search'       => $search,
    'PerPageLimit' => $perPage,
    'gpage'        => $page,
];
?>

<div hx-boost="true" hx-target="#main" hx-push-url="false" hx-indicator="#global-spinner">

    <h3>Guardians <small class="text-muted fs-6"><?= htmlspecialchars($year['name']) ?></small></h3>
    <div id="message"><?= $message ?? '' ?></div>

    <!-- CREATE -->
    <form hx-post="<?= HANDLERS_URL ?>guardianProcess.php" class="mb-4 d-flex gap-2">
        <input type="hidden" name="action" value="create">
        <input name="fname" placeholder="First name" class="form-control" required>
        <input name="lname" placeholder="Last name" class="form-control" required>
        <input name="mname" placeholder="Middle name" class="form-control">
        <input name="phone" placeholder="Phone" class="form-control">
        <input name="email" type="email" placeholder="Email" class="form-control">
        <button class="btn btn-primary">Create</button>
    </form>

    <!-- SEARCH -->
    <div class="row">
        <div class="col-6">
            <!-- SEARCH -->
            <form method="get" action="<?= $self ?>" class="mb-3 d-flex gap-2">
                <?php hiddenFields($state, ['search', 'gpage']); ?>
                <input type="text" name="search" class="form-control w-auto"
                       value="<?= htmlspecialchars($search) ?>" placeholder="Search...">
                <button class="btn btn-outline-primary">Search</button>
                <?php if ($search !== ''): ?>
                    <a href="<?= makeUrl($self, $state, ['search' => '', 'gpage' => null]) ?>"
                        class="btn btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="col-6 d-flex justify-content-end">
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
    <table class="table">
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
            <?php $formId = 'editForm' . $r['id']; ?>

            <!-- Row type 1: a normal row -->
            <tr>
                <td><?= $r['id'] ?></td>
                <td><?= htmlspecialchars($r['fname']) ?></td>
                <td><?= htmlspecialchars($r['lname']) ?></td>
                <td><?= htmlspecialchars($r['mname']) ?></td>
                <td><?= htmlspecialchars($r['phone'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['email'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['is_active'] ? 'ACTIVE' : 'INACTIVE') ?></td>
                <td>
                    <!-- Edit only swaps the rows in the browser, no request is sent -->
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleEdit(this)">Edit</button>
                    <!-- <button class="btn btn-sm btn-outline-danger"
                            hx-post="<?= HANDLERS_URL ?>guardianProcess.php"
                            hx-vals='<?= htmlspecialchars(json_encode($state + ['action' => 'delete', 'id' => $r['id']]), ENT_QUOTES) ?>'
                            hx-confirm="Delete this guardian?">Delete</button> -->

                    <!-- DROP DOWN STATUS -->

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
                                    hx-confirm="Set Guardian to Active?">
                                Active
                            </button>
                        </li>

                        <li>
                            <button class="dropdown-item"
                                    hx-post="<?= $handler ?>"
                                    hx-vals='<?= postValues($state, ["action" => "status", "id" => $r["id"], "status" => 0]) ?>'
                                    hx-confirm="Set Guardian to Inactive?">
                                Inactive
                            </button>
                        </li>
                    </ul>
                </td>
            </tr>

            <!-- Row type 2: the edit row, hidden until Edit is pressed -->
            <tr class="edit-row d-none">
                <td>
                    <?= $r['id'] ?>
                    <!-- UPDATE form of this row (the inputs below belong to it through form="...") -->
                    <form id="<?= $formId ?>" hx-post="<?= HANDLERS_URL ?>guardianProcess.php">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <?php foreach ($state as $k => $v): ?>
                            <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars($v) ?>">
                        <?php endforeach; ?>
                    </form>
                </td>
                <td><input form="<?= $formId ?>" name="fname" value="<?= htmlspecialchars($r['fname']) ?>" required></td>
                <td><input form="<?= $formId ?>" name="lname" value="<?= htmlspecialchars($r['lname']) ?>" required></td>
                <td><input form="<?= $formId ?>" name="mname" value="<?= htmlspecialchars($r['mname']) ?>" required></td>
                <td><input form="<?= $formId ?>" name="phone" value="<?= htmlspecialchars($r['phone'] ?? '') ?>"></td>
                <td><input form="<?= $formId ?>" name="email" value="<?= htmlspecialchars($r['email'] ?? '') ?>"></td>
                <td>
                    <button form="<?= $formId ?>" class="btn btn-sm btn-success">Save</button>
                    <!-- Cancel swaps back and clears what was typed, no request is sent -->
                    <button type="button" class="btn btn-sm btn-secondary" onclick="toggleEdit(this)">Cancel</button>
                </td>
            </tr>

        <?php endforeach; ?>
    </table>

    <?php renderPagination(makeUrl($self, $state, ['gpage' => null]), $page, "gpage", $totalPages); ?>

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
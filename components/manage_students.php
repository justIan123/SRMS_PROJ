<?php
require_once("../public/initialize.php");
require_once(CONFIG_PATH . DS . "db_sqlite.php");
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");

$self  = COMPONENTS_URL . 'manage_students.php';
$table = 'students';

// Everything belongs to the current school year
$year = CurrentSchoolYear($pdo);
if (!$year) {
    echo alertBox('warning', 'No school year is running. Start one in School Years first.');
    return;
}
$where = ['school_year_id' => $year['id']];

// Guardians of this school year (for the dropdown and to show their names)
$guardians = FindWhere($pdo, 'guardians', ['school_year_id' => $year['id']]);
$guardianNames = [];
foreach ($guardians as $g) {
    $guardianNames[$g['id']] = $g['fname'] . ' ' . $g['lname'];
}

// ---- search ----
$search = trim($_GET['search'] ?? '');
$cols   = ['fname', 'lname', 'grade_level'];

// ---- sort ----
$headers = ['id' => 'ID', 'fname' => 'First name', 'lname' => 'Last name', 'grade_level' => 'Grade'];
$sort = array_key_exists($_GET['sort'] ?? '', $headers) ? $_GET['sort'] : 'id';
$dir  = ($_GET['dir'] ?? '') === 'DESC' ? 'DESC' : 'ASC';
$flip = $dir === 'ASC' ? 'DESC' : 'ASC';

// ---- pagination ----
$perPage    = 10;
$total      = CountList($pdo, $table, $where, $cols, $search);
$totalPages = max(1, ceil($total / $perPage));
$page       = min(max(1, (int)($_GET['page'] ?? 1)), $totalPages);

$rows   = ListRecords($pdo, $table, $where, $cols, $search, $perPage, ($page - 1) * $perPage, $sort, $dir);
$editId = (int)($_GET['edit'] ?? 0);

$s    = '&search=' . urlencode($search);
$base = $self . "?sort=$sort&dir=$dir" . $s;
$keep = ['sort' => $sort, 'dir' => $dir, 'page' => $page, 'search' => $search];
?>

<div hx-boost="true" hx-target="#main" hx-push-url="false" hx-indicator="#global-spinner">

    <h3>Students <small class="text-muted fs-6"><?= htmlspecialchars($year['name']) ?></small></h3>
    <div id="message"><?= $message ?? '' ?></div>

    <!-- CREATE -->
    <form hx-post="<?= HANDLERS_URL ?>studentProcess.php" class="mb-4 d-flex gap-2">
        <input type="hidden" name="action" value="create">
        <input name="fname" placeholder="First name" class="form-control" required>
        <input name="lname" placeholder="Last name" class="form-control" required>
        <input name="grade_level" placeholder="Grade (e.g. Grade 7)" class="form-control">
        <select name="guardian_id" class="form-select" required>
            <option value="">Choose guardian...</option>
            <?php foreach ($guardianNames as $gid => $gname): ?>
                <option value="<?= $gid ?>"><?= htmlspecialchars($gname) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary">Create</button>
    </form>

    <!-- SEARCH -->
    <form method="get" action="<?= $self ?>" class="mb-3 d-flex gap-2">
        <input type="hidden" name="sort" value="<?= $sort ?>">
        <input type="hidden" name="dir" value="<?= $dir ?>">
        <input type="text" name="search" class="form-control w-auto" value="<?= htmlspecialchars($search) ?>" placeholder="Search...">
        <button class="btn btn-outline-primary">Search</button>
        <?php if ($search !== ''): ?>
            <a href="<?= $self ?>?sort=<?= $sort ?>&dir=<?= $dir ?>" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <!-- UPDATE form (only exists while editing) -->
    <?php if ($editId): ?>
        <form id="editForm" hx-post="<?= HANDLERS_URL ?>studentProcess.php">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= $editId ?>">
            <?php foreach ($keep as $k => $v): ?>
                <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars($v) ?>">
            <?php endforeach; ?>
        </form>
    <?php endif; ?>

    <!-- TABLE -->
    <table class="table">
        <tr>
            <?php foreach ($headers as $col => $label): ?>
                <th><a href="<?= $self ?>?sort=<?= $col ?>&dir=<?= $flip . $s ?>"><?= $label ?></a></th>
            <?php endforeach; ?>
            <th>Guardian</th>
            <th>Actions</th>
        </tr>

        <?php if (!$rows): ?>
            <tr><td colspan="6" class="text-center text-muted">No students found.</td></tr>
        <?php endif; ?>

        <?php foreach ($rows as $r): ?>
            <?php if ($r['id'] == $editId): ?>

                <tr>
                    <td><?= $r['id'] ?></td>
                    <td><input form="editForm" name="fname" value="<?= htmlspecialchars($r['fname']) ?>" required></td>
                    <td><input form="editForm" name="lname" value="<?= htmlspecialchars($r['lname']) ?>" required></td>
                    <td><input form="editForm" name="grade_level" value="<?= htmlspecialchars($r['grade_level'] ?? '') ?>"></td>
                    <td>
                        <select form="editForm" name="guardian_id" required>
                            <?php foreach ($guardianNames as $gid => $gname): ?>
                                <option value="<?= $gid ?>" <?= $gid == $r['guardian_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($gname) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <button form="editForm" class="btn btn-sm btn-success">Save</button>
                        <a href="<?= $base ?>&page=<?= $page ?>" class="btn btn-sm btn-secondary">Cancel</a>
                    </td>
                </tr>

            <?php else: ?>

                <tr>
                    <td><?= $r['id'] ?></td>
                    <td><?= htmlspecialchars($r['fname']) ?></td>
                    <td><?= htmlspecialchars($r['lname']) ?></td>
                    <td><?= htmlspecialchars($r['grade_level'] ?? '') ?></td>
                    <td><?= htmlspecialchars($guardianNames[$r['guardian_id']] ?? '-') ?></td>
                    <td>
                        <a href="<?= $base ?>&page=<?= $page ?>&edit=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <button class="btn btn-sm btn-outline-danger"
                                hx-post="<?= HANDLERS_URL ?>studentProcess.php"
                                hx-vals='<?= htmlspecialchars(json_encode($keep + ['action' => 'delete', 'id' => $r['id']]), ENT_QUOTES) ?>'
                                hx-confirm="Delete this student?">Delete</button>
                    </td>
                </tr>

            <?php endif; ?>
        <?php endforeach; ?>
    </table>

    <?php renderPagination($base, $page, $totalPages); ?>

</div>
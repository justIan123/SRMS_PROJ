<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");

$self    = COMPONENTS_URL . 'manage_students.php';
$handler = HANDLERS_URL . 'studentProcess.php';

// ---- search ----
$search = trim($_GET['search'] ?? '');

// ---- sort (only a key of $headers can ever reach the SQL) ----
$headers = [
    'id'            => 'ID',
    'fname'         => 'First name',
    'lname'         => 'Last name',
    'grade_level'   => 'Grade',
    'guardian_name' => 'Parent / Guardian',
];
$sort = array_key_exists($_GET['sort'] ?? '', $headers) ? $_GET['sort'] : 'id';
$dir  = ($_GET['dir'] ?? '') === 'DESC' ? 'DESC' : 'ASC';
$flip = $dir === 'ASC' ? 'DESC' : 'ASC';

$perPage = max(1, (int)($_GET['PerPageLimit'] ?? 10));

// ---- which student is getting a parent ----
$assignId = (int)($_GET['assign'] ?? 0);
$gsearch  = trim($_GET['gsearch'] ?? '');

// ---- guardian list (only loaded while choosing a parent) ----
$assignStudent     = null;
$currentGuardianId = 0;
$gRows             = [];
$gPage             = 1;
$gTotalPages       = 1;

try {
    // Everything belongs to the current school year
    $year = CurrentSchoolYear($pdo);
    if (!$year) {
        echo alertBox('warning', 'No school year is running. Start one in School Years first.');
        return;
    }

    // ---- students: WHERE shared by the count query and the list query ----
    $sqlWhere = "WHERE s.school_year_id = :year";
    $params   = [':year' => $year['id']];
    if ($search !== '') {
        $like      = '%' . $search . '%';
        $sqlWhere .= " AND (s.fname ILIKE :s1 OR s.lname ILIKE :s2 OR CAST(s.grade_level AS TEXT) ILIKE :s3)";
        $params   += [':s1' => $like, ':s2' => $like, ':s3' => $like];
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students s $sqlWhere");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $perPage));
    $page       = min(max(1, (int)($_GET['PageNum'] ?? 1)), $totalPages);
    $offset     = ($page - 1) * $perPage;

    // The guardian's name of each student, shown as one text value (NULL when there is no guardian)
    $stmt = $pdo->prepare("SELECT s.*, g.fname || ' ' || g.lname AS guardian_name
                           FROM students s
                           LEFT JOIN guardians g ON g.id = s.guardian_id
                           $sqlWhere
                           ORDER BY $sort $dir
                           LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // ---- the student that is getting a parent ----
    if ($assignId) {
        // not found, or from another school year: forget it
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id AND school_year_id = :year");
        $stmt->execute([':id' => $assignId, ':year' => $year['id']]);
        $assignStudent = $stmt->fetch() ?: null;

        if (!$assignStudent) {
            $assignId = 0;
        }
    }

    // ---- the guardians to choose from ----
    if ($assignId) {
        $currentGuardianId = (int)($assignStudent['guardian_id'] ?? 0);

        $gWhere  = "WHERE g.school_year_id = :year";
        $gParams = [':year' => $year['id']];
        if ($gsearch !== '') {
            $gLike    = '%' . $gsearch . '%';
            $gWhere  .= " AND (g.fname ILIKE :s1 OR g.lname ILIKE :s2 OR g.phone ILIKE :s3)";
            $gParams += [':s1' => $gLike, ':s2' => $gLike, ':s3' => $gLike];
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM guardians g $gWhere");
        $stmt->execute($gParams);
        $gTotal = (int)$stmt->fetchColumn();

        $gTotalPages = max(1, (int)ceil($gTotal / $perPage));
        $gPage       = min(max(1, (int)($_GET['gPageNum'] ?? 1)), $gTotalPages);
        $gOffset     = ($gPage - 1) * $perPage;

        // the children of each guardian, shown as one text value
        $stmt = $pdo->prepare("SELECT g.*,
                                      (SELECT string_agg(c.fname || ' ' || c.lname, ', ')
                                       FROM students c
                                       WHERE c.guardian_id = g.id) AS children
                               FROM guardians g
                               $gWhere
                               ORDER BY g.id ASC
                               LIMIT $perPage OFFSET $gOffset");
        $stmt->execute($gParams);
        $gRows = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    err_log($e->getMessage());
    echo alertBox('danger', 'Could not load the students. Please try again.');
    return;
}

// ---- remember where we are (every link and form carries this) ----
$state = [
    'sort'         => $sort,
    'dir'          => $dir,
    'PageNum'      => $page,
    'search'       => $search,
    'PerPageLimit' => $perPage,
    'assign'       => $assignId,
    'gsearch'      => $gsearch,
    'gPageNum'     => $gPage,
];
?>

<div hx-boost="true" hx-target="#main" hx-push-url="false" hx-indicator="#global-spinner">

    <h3>Students <small class="text-muted fs-6"><?= htmlspecialchars($year['name']) ?></small></h3>
    <div id="message"><?= $message ?? '' ?></div>

    <!-- CREATE (the parent is assigned later from the table) -->
    <form hx-post="<?= $handler ?>" class="mb-4">
        <input type="hidden" name="action" value="create">
        <?php hiddenFields($state); ?>
        <div class="row mb-3">
            <div class="col">
                <input name="fname" placeholder="First name" class="form-control" required>
            </div>
            <div class="col">
                <input name="lname" placeholder="Last name" class="form-control" required>
            </div>
            <div class="col">
                <input name="grade_level" placeholder="Grade" class="form-control">
            </div>
            <div class="col d-flex justify-content-end">
                <button class="btn btn-primary">Create</button>
            </div>
        </div>
    </form>

    <div class="row">
        <div class="col-6">
            <!-- SEARCH -->
            <form method="get" action="<?= $self ?>" class="mb-3 d-flex gap-2">
                <?php hiddenFields($state, ['search', 'PageNum']); ?>
                <input type="text" name="search" class="form-control w-auto"
                       value="<?= htmlspecialchars($search) ?>" placeholder="Search students...">
                <button class="btn btn-outline-primary">Search</button>
                <?php if ($search !== ''): ?>
                    <a href="<?= makeUrl($self, $state, ['search' => '', 'PageNum' => null]) ?>"
                       class="btn btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="col-6 d-flex justify-content-end">
            <!-- limit rows -->
            <form method="get" action="<?= $self ?>" class="mb-3 d-flex gap-2">
                <p class="mt-3">Rows per page</p>
                <?php hiddenFields($state, ['PerPageLimit', 'PageNum', 'gPageNum']); ?>
                <input type="number" name="PerPageLimit" min="1" class="form-control w-auto"
                       value="<?= htmlspecialchars($perPage) ?>">
                <button class="btn btn-outline-primary">Set</button>
            </form>
        </div>
    </div>

    <!-- STUDENT TABLE -->
    <table class="table align-middle">
        <tr>
            <?php foreach ($headers as $col => $label): ?>
                <th>
                    <a href="<?= makeUrl($self, $state, ['sort' => $col, 'dir' => $flip, 'PageNum' => null]) ?>">
                        <?= $label ?>
                    </a>
                </th>
            <?php endforeach; ?>
            <th>Actions</th>
        </tr>

        <?php if (!$rows): ?>
            <tr><td colspan="6" class="text-center text-muted">No students found.</td></tr>
        <?php endif; ?>

        <?php foreach ($rows as $r): ?>
            <?php
            $formId      = 'editForm' . $r['id'];
            $parentName  = $r['guardian_name'] ?? 'No parent yet';
            $parentClass = $r['guardian_id'] ? '' : 'text-muted';
            $assignLabel = $r['guardian_id'] ? 'Change parent' : 'Assign parent';
            ?>

            <!-- Row type 1: a normal row -->
            <tr class="<?= $r['id'] == $assignId ? 'table-warning' : '' ?>">
                <td><?= $r['id'] ?></td>
                <td><?= htmlspecialchars($r['fname']) ?></td>
                <td><?= htmlspecialchars($r['lname']) ?></td>
                <td><?= htmlspecialchars($r['grade_level'] ?? '') ?></td>
                <td class="<?= $parentClass ?>"><?= htmlspecialchars($parentName) ?></td>
                <td class="text-nowrap">
                    <!-- Edit only swaps the rows in the browser, no request is sent -->
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleEdit(this)">Edit</button>

                    <?php if ($r['id'] == $assignId): ?>
                        <a href="<?= makeUrl($self, $state, ['assign' => null, 'gsearch' => null, 'gPageNum' => null]) ?>"
                           class="btn btn-sm btn-secondary">Done</a>
                    <?php else: ?>
                        <a href="<?= makeUrl($self, $state, ['assign' => $r['id'], 'gsearch' => null, 'gPageNum' => null]) ?>"
                           class="btn btn-sm btn-outline-success"><?= $assignLabel ?></a>
                    <?php endif; ?>

                    <button class="btn btn-sm btn-outline-danger"
                            hx-post="<?= $handler ?>"
                            hx-vals='<?= postValues($state, ['action' => 'delete', 'id' => $r['id']]) ?>'
                            hx-confirm="Delete this student?">Delete</button>
                </td>
            </tr>

            <!-- Row type 2: the edit row, hidden until Edit is pressed -->
            <tr class="edit-row d-none">
                <td>
                    <?= $r['id'] ?>
                    <!-- UPDATE form of this row (the inputs below belong to it through form="...") -->
                    <form id="<?= $formId ?>" hx-post="<?= $handler ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <?php hiddenFields($state); ?>
                    </form>
                </td>
                <td><input form="<?= $formId ?>" name="fname" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($r['fname']) ?>" required></td>
                <td><input form="<?= $formId ?>" name="lname" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($r['lname']) ?>" required></td>
                <td><input form="<?= $formId ?>" name="grade_level" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($r['grade_level'] ?? '') ?>"></td>
                <td class="<?= $parentClass ?>"><?= htmlspecialchars($parentName) ?></td>
                <td class="text-nowrap">
                    <button form="<?= $formId ?>" class="btn btn-sm btn-success">Save</button>
                    <!-- Cancel swaps back and clears what was typed, no request is sent -->
                    <button type="button" class="btn btn-sm btn-secondary" onclick="toggleEdit(this)">Cancel</button>
                </td>
            </tr>

        <?php endforeach; ?>
    </table>

    <?php renderPagination(makeUrl($self, $state, []), $page, "PageNum", $totalPages); ?>


    <!-- GUARDIANS (only shown while choosing a parent for a student) -->
    <?php if ($assignStudent): ?>
        <div id="guardiansDiv" class="mt-4">
            <h5>Parent for <?= htmlspecialchars($assignStudent['fname'] . ' ' . $assignStudent['lname']) ?></h5>

            <!-- Remove the parent the student has now -->
            <?php if ($currentGuardianId): ?>
                <form hx-post="<?= $handler ?>" class="mb-3">
                    <input type="hidden" name="action" value="unassign">
                    <input type="hidden" name="id" value="<?= $assignId ?>">
                    <?php hiddenFields($state); ?>
                    <button class="btn btn-sm btn-outline-danger">Remove current parent</button>
                </form>
            <?php endif; ?>

            <!-- SEARCH guardians -->
            <form method="get" action="<?= $self ?>" class="mb-3 d-flex gap-2">
                <?php hiddenFields($state, ['gsearch', 'gPageNum']); ?>
                <input type="text" name="gsearch" class="form-control w-auto"
                       value="<?= htmlspecialchars($gsearch) ?>" placeholder="Search guardians...">
                <button class="btn btn-outline-primary">Find</button>
                <?php if ($gsearch !== ''): ?>
                    <a href="<?= makeUrl($self, $state, ['gsearch' => '', 'gPageNum' => null]) ?>"
                       class="btn btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </form>

            <!-- GUARDIAN TABLE -->
            <table class="table align-middle">
                <tr>
                    <th>Guardian</th>
                    <th>Phone</th>
                    <th>Children</th>
                    <th></th>
                </tr>

                <?php if (!$gRows): ?>
                    <tr><td colspan="4" class="text-center text-muted">No guardians found.</td></tr>
                <?php endif; ?>

                <?php foreach ($gRows as $g): ?>
                    <?php $isCurrent = ($g['id'] == $currentGuardianId); ?>
                    <tr class="<?= $isCurrent ? 'table-success' : '' ?>">
                        <td><?= htmlspecialchars($g['fname'] . ' ' . $g['lname']) ?></td>
                        <td><?= htmlspecialchars($g['phone'] ?? '') ?></td>
                        <td><?= htmlspecialchars($g['children'] ?? '-') ?></td>
                        <td class="text-end">
                            <?php if ($isCurrent): ?>
                                <span class="badge text-bg-success">Current</span>
                            <?php else: ?>
                                <button class="btn btn-sm btn-success"
                                        hx-post="<?= $handler ?>"
                                        hx-vals='<?= postValues($state, [
                                            'action'      => 'assign',
                                            'id'          => $assignId,
                                            'guardian_id' => $g['id'],
                                        ]) ?>'>Assign</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <?php renderPagination(makeUrl($self, $state, []), $gPage, "gPageNum", $gTotalPages); ?>
        </div>
    <?php endif; ?>

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
<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");

$self    = COMPONENTS_URL . 'manage_subjects.php';
$handler = HANDLERS_URL . 'subjectsProcess.php';

// ---- search ----
$search = trim($_GET['search'] ?? '');

// ---- sort (only a key of $headers can ever reach the SQL) ----
$headers = [
    'id'                  => 'ID',
    'subject_name'        => 'Subject',
    'subject_description' => 'Description',
    'teachers'            => 'Teachers',
    'is_active'           => 'Status',
];
$sort = array_key_exists($_GET['sort'] ?? '', $headers) ? $_GET['sort'] : 'id';
$dir  = ($_GET['dir'] ?? '') === 'DESC' ? 'DESC' : 'ASC';
$flip = $dir === 'ASC' ? 'DESC' : 'ASC';

$perPage = max(1, (int)($_GET['PerPageLimit'] ?? 10));

// ---- which subject is getting teachers ----
$assignId = (int)($_GET['assign'] ?? 0);

// ---- teacher list (only loaded while assigning) ----
$assignSubject = null;
$teachers      = [];
$assignedIds   = [];

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
        $sqlWhere .= " AND (subject_name ILIKE :s1 OR subject_description ILIKE :s2)";
        $params   += [':s1' => $like, ':s2' => $like];
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM subjects $sqlWhere");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $perPage));
    $page       = min(max(1, (int)($_GET['PageNum'] ?? 1)), $totalPages);
    $offset     = ($page - 1) * $perPage;

    // Teachers of each subject, shown as one text value ("Ann Cruz, Ben Lee")
    $stmt = $pdo->prepare("SELECT subjects.*,
                                  (SELECT string_agg(u.fname || ' ' || u.lname, ', ')
                                   FROM teacher_subjects ts
                                   JOIN users u ON u.id = ts.teacher_id
                                   WHERE ts.subject_id = subjects.id) AS teachers
                           FROM subjects
                           $sqlWhere
                           ORDER BY $sort $dir
                           LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // ---- the subject that is getting teachers ----
    if ($assignId) {
        // not found, or from another school year: forget it
        $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = :id AND school_year_id = :year");
        $stmt->execute([':id' => $assignId, ':year' => $year['id']]);
        $assignSubject = $stmt->fetch() ?: null;

        if (!$assignSubject) {
            $assignId = 0;
        }
    }

    if ($assignId) {
        // all active users that have the "teacher" role
        $stmt = $pdo->prepare("SELECT u.id, u.fname, u.lname
                               FROM users u
                               JOIN user_roles ur ON ur.user_id = u.id
                               JOIN roles r ON r.role_id = ur.role
                               WHERE LOWER(r.role) = 'teacher' AND u.is_active = :active
                               ORDER BY u.lname, u.fname");
        $stmt->execute([':active' => 1]);
        $teachers = $stmt->fetchAll();

        // teachers already assigned to this subject
        $stmt = $pdo->prepare("SELECT teacher_id FROM teacher_subjects WHERE subject_id = :subject");
        $stmt->execute([':subject' => $assignId]);
        $assignedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (PDOException $e) {
    err_log($e->getMessage());
    echo alertBox('danger', 'Could not load the subjects. Please try again.');
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
];
?>

<div hx-boost="true" hx-target="#main" hx-push-url="false" hx-indicator="#global-spinner">

    <h3>Subjects <small class="text-muted fs-6"><?= htmlspecialchars($year['name']) ?></small></h3>
    <div id="message"><?= $message ?? '' ?></div>

    <!-- CREATE -->
    <form hx-post="<?= $handler ?>" class="mb-4">
        <input type="hidden" name="action" value="create">
        <?php hiddenFields($state); ?>
        <div class="row mb-3">
            <div class="col">
                <input name="subject_name" placeholder="Subject name" class="form-control" required>
            </div>
            <div class="col">
                <input name="subject_description" placeholder="Description" class="form-control">
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
                       value="<?= htmlspecialchars($search) ?>" placeholder="Search...">
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
                <?php hiddenFields($state, ['PerPageLimit', 'PageNum']); ?>
                <input type="number" name="PerPageLimit" min="1" class="form-control w-auto"
                       value="<?= htmlspecialchars($perPage) ?>">
                <button class="btn btn-outline-primary">Set</button>
            </form>
        </div>
    </div>

    <!-- SUBJECT TABLE -->
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
            <tr><td colspan="6" class="text-center text-muted">No subjects found.</td></tr>
        <?php endif; ?>

        <?php foreach ($rows as $r): ?>
            <?php $formId = 'editForm' . $r['id']; ?>

            <!-- Row type 1: a normal row -->
            <tr class="<?= $r['id'] == $assignId ? 'table-warning' : '' ?>">
                <td><?= $r['id'] ?></td>
                <td><?= htmlspecialchars($r['subject_name']) ?></td>
                <td><?= htmlspecialchars($r['subject_description'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['teachers'] ?? '-') ?></td>
                <td>
                    <?php if ($r['is_active']): ?>
                        <span class="badge text-bg-success">Active</span>
                    <?php else: ?>
                        <span class="badge text-bg-secondary">Inactive</span>
                    <?php endif; ?>
                </td>
                <td class="text-nowrap">
                    <!-- Edit only swaps the rows in the browser, no request is sent -->
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleEdit(this)">Edit</button>

                    <?php if ($r['id'] == $assignId): ?>
                        <a href="<?= makeUrl($self, $state, ['assign' => null]) ?>"
                           class="btn btn-sm btn-secondary">Done</a>
                    <?php else: ?>
                        <a href="<?= makeUrl($self, $state, ['assign' => $r['id']]) ?>"
                           class="btn btn-sm btn-outline-success">Teachers</a>
                    <?php endif; ?>

                    <button class="btn btn-sm btn-outline-danger"
                            hx-post="<?= $handler ?>"
                            hx-vals='<?= postValues($state, [
                                'action'    => 'toggle',
                                'id'        => $r['id'],
                                'is_active' => $r['is_active'] ? 0 : 1,
                            ]) ?>'
                            hx-confirm="<?= $r['is_active'] ? 'Deactivate' : 'Activate' ?> this subject?">
                        <?= $r['is_active'] ? 'Deactivate' : 'Activate' ?>
                    </button>
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
                <td><input form="<?= $formId ?>" name="subject_name" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($r['subject_name']) ?>" required></td>
                <td><input form="<?= $formId ?>" name="subject_description" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($r['subject_description'] ?? '') ?>"></td>
                <td class="text-muted"><?= htmlspecialchars($r['teachers'] ?? '-') ?></td>
                <td>
                    <select form="<?= $formId ?>" name="is_active" class="form-select form-select-sm">
                        <option value="1" <?= $r['is_active'] ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= $r['is_active'] ? '' : 'selected' ?>>Inactive</option>
                    </select>
                </td>
                <td class="text-nowrap">
                    <button form="<?= $formId ?>" class="btn btn-sm btn-success">Save</button>
                    <!-- Cancel swaps back and clears what was typed, no request is sent -->
                    <button type="button" class="btn btn-sm btn-secondary" onclick="toggleEdit(this)">Cancel</button>
                </td>
            </tr>

        <?php endforeach; ?>
    </table>

    <?php renderPagination(makeUrl($self, $state, []), $page, "PageNum", $totalPages); ?>


    <!-- TEACHERS (only shown while assigning teachers to a subject) -->
    <?php if ($assignSubject): ?>
        <div id="teachersDiv" class="mt-4">
            <h5>Teachers for <?= htmlspecialchars($assignSubject['subject_name']) ?></h5>

            <table class="table align-middle w-auto">
                <tr>
                    <th>Teacher</th>
                    <th></th>
                </tr>

                <?php if (!$teachers): ?>
                    <tr><td colspan="2" class="text-center text-muted">No teachers found.</td></tr>
                <?php endif; ?>

                <?php foreach ($teachers as $t): ?>
                    <?php $isAssigned = in_array($t['id'], $assignedIds); ?>
                    <tr class="<?= $isAssigned ? 'table-success' : '' ?>">
                        <td><?= htmlspecialchars($t['fname'] . ' ' . $t['lname']) ?></td>
                        <td class="text-end">
                            <?php if ($isAssigned): ?>
                                <button class="btn btn-sm btn-outline-danger"
                                        hx-post="<?= $handler ?>"
                                        hx-vals='<?= postValues($state, [
                                            'action'     => 'unassign',
                                            'id'         => $assignId,
                                            'teacher_id' => $t['id'],
                                        ]) ?>'>Remove</button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-success"
                                        hx-post="<?= $handler ?>"
                                        hx-vals='<?= postValues($state, [
                                            'action'     => 'assign',
                                            'id'         => $assignId,
                                            'teacher_id' => $t['id'],
                                        ]) ?>'>Assign</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
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
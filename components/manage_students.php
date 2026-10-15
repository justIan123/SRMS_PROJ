<?php
require_once("../public/initialize.php");
require_once(CONFIG_PATH . DS . "db_sqlite.php");
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");

$self    = COMPONENTS_URL . 'manage_students.php';
$handler = HANDLERS_URL . DS . 'studentProcess.php';

// 14/10/2026
// i hate this every button create a req which is this too much for infinityfree




/* STEP 1: Which school year are we in?*/
$year = CurrentSchoolYear($pdo);

if (!$year) {
    echo alertBox('warning', 'No school year is running. Start one in School Years first.');
    return;
}

$where = ['school_year_id' => $year['id']];


/*STEP 2: Read what the user typed or clicked*/
$search   = trim($_GET['search'] ?? '');    // search box for students
$gsearch  = trim($_GET['gsearch'] ?? '');   // search box for guardians
$editId   = (int)($_GET['edit'] ?? 0);      // student being edited
$assignId = (int)($_GET['assign'] ?? 0);    // student who is getting a parent

$headers = ['id' => 'ID', 'fname' => 'First name', 'lname' => 'Last name', 'grade_level' => 'Grade'];

$sort = $_GET['sort'] ?? 'id';
if (!array_key_exists($sort, $headers)) {
    $sort = 'id';
}

$dir = 'ASC';
if (($_GET['dir'] ?? '') === 'DESC') {
    $dir = 'DESC';
}

$flip = 'DESC';
if ($dir === 'DESC') {
    $flip = 'ASC';
}


/*STEP 3 Load students and guardians of this school year*/
$allGuardians = FindWhere($pdo, 'guardians', $where);
$allStudents  = FindWhere($pdo, 'students', $where);

// guardian id => "First Last"
$guardianNames = [];
foreach ($allGuardians as $g) {
    $guardianNames[$g['id']] = $g['fname'] . ' ' . $g['lname'];
}

// guardian id => list of children names (one guardian can have MANY children)
// Also find the student who is being assigned a parent right now.
$childrenOf    = [];
$assignStudent = null;

foreach ($allStudents as $st) {
    $guardianId = $st['guardian_id'];

    if ($guardianId) {
        $childrenOf[$guardianId][] = $st['fname'] . ' ' . $st['lname'];
    }

    if ($st['id'] == $assignId) {
        $assignStudent = $st;
    }
}

// If that student does not exist, forget about assigning
//for just to be sure
if ($assignStudent === null) {
    $assignId = 0;
}

$assigning         = ($assignId > 0);
$currentGuardianId = $assignStudent['guardian_id'] ?? 0;   // the parent the student has now


/* STEP 4: Guardian list (right side): search + pages*/
$matchingGuardians = [];

foreach ($allGuardians as $g) {
    $fullName     = $g['fname'] . ' ' . $g['lname'];
    $searchEmpty  = ($gsearch === '');
    $nameMatches  = (stripos($fullName, $gsearch) !== false);
    $phoneMatches = (stripos((string)$g['phone'], $gsearch) !== false);

    if ($searchEmpty || $nameMatches || $phoneMatches) {
        $matchingGuardians[] = $g;
    }
}

$gPerPage    = (int)($_GET['PerPageLimit'] ?? 10);
$gTotalPages = max(1, (int)ceil(count($matchingGuardians) / $gPerPage));
$gpage       = min(max(1, (int)($_GET['gPageNum'] ?? 1)), $gTotalPages);
$gRows       = array_slice($matchingGuardians, ($gpage - 1) * $gPerPage, $gPerPage);

$gPrevClass = 'disabled';
if ($gpage > 1) {
    $gPrevClass = '';
}

$gNextClass = 'disabled';
if ($gpage < $gTotalPages) {
    $gNextClass = '';
}


/* STEP 5: Student list (left side): search + sort + pages*/
$cols       = ['fname', 'lname', 'grade_level'];
$perPage    = (int)($_GET['PerPageLimit'] ?? 10);
$total      = CountList($pdo, 'students', $where, $cols, $search);
$totalPages = max(1, ceil($total / $perPage));
$StudentPage       = min(max(1, (int)($_GET['StudentPageNum'] ?? 1)), $totalPages);
$offset     = ($StudentPage - 1) * $perPage;

$rows = ListRecords($pdo, 'students', $where, $cols, $search, $perPage, $offset, $sort, $dir);


/* STEP 6: Remember the state (every link and form carries it along) */
$state = [
    'sort'    => $sort,
    'dir'     => $dir,
    'page'    => $StudentPage,
    'search'  => $search,
    'gsearch' => $gsearch,
    'gpage'   => $gpage,
    'assign'  => $assignId,
    'PerPageLimit'  => $perPage,
];
?>

<div hx-boost="true" hx-target="#main" hx-push-url="false" hx-indicator="#global-spinner">

    <h3>Students <small class="text-muted fs-6"><?= htmlspecialchars($year['name']) ?></small></h3>
    <div id="message"><?= $message ?? '' ?></div>

    <div class="row g-4">


        <!--STUDENTS-->
        <div id="studentsDiv">

            <!-- CREATE a student (the parent is assigned later from the table) -->
            <form hx-post="<?= $handler ?>" class="mb-3 d-flex gap-2">
                <input type="hidden" name="action" value="create">
                <?php hiddenFields($state); ?>
                <input name="fname" placeholder="First name" class="form-control" required>
                <input name="lname" placeholder="Last name" class="form-control" required>
                <input name="grade_level" placeholder="Grade" class="form-control">
                <button class="btn btn-primary">Create</button>
            </form>

            <div class="row">
                <div class="col-6">
                    <!-- SEARCH students -->
                    <form method="get" action="<?= $self ?>" class="mb-3 d-flex gap-2">
                        <?php hiddenFields($state, ['search', 'page']); ?>
                        <input type="text" name="search" class="form-control w-auto"
                            value="<?= htmlspecialchars($search) ?>" placeholder="Search students...">
                        <button class="btn btn-outline-primary">Search</button>
                        <a href="<?= makeUrl($self, $state, ['search' => null, 'page' => null]) ?>"
                        class="btn btn-outline-secondary">Clear</a>
                    </form>
                </div>

                <div class="col-6 d-flex justify-content-end">
                    <!-- limit rows -->
                    <form method="get" action="<?= $self ?>" class="mb-3 d-flex gap-2">
                        <p class="mt-3"> Set Limit the No of rows</p>
                        <?php hiddenFields($state, ['per', 'page']); ?>
                        <input type="number" name="PerPageLimit" class="form-control w-auto"
                            value="<?= htmlspecialchars($perPage) ?>" placeholder="Show limit of Rows...">
                        <button class="btn btn-outline-primary">Search</button>
                    </form>
                </div>
            </div>




            <!-- This tiny form only exists while a student is being edited -->
            <?php if ($editId): ?>
                <form id="editForm" hx-post="<?= $handler ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= $editId ?>">
                    <?php hiddenFields($state); ?>
                </form>
            <?php endif; ?>

            <!-- STUDENT TABLE -->
            <table class="table align-middle">
                <tr>
                    <?php foreach ($headers as $col => $label): ?>
                        <th>
                            <a href="<?= makeUrl($self, $state, ['sort' => $col, 'dir' => $flip, 'page' => null]) ?>">
                                <?= $label ?>
                            </a>
                        </th>
                    <?php endforeach; ?>
                    <th>Parent / Guardian</th>
                    <th>Actions</th>
                </tr>

                <?php if (!$rows): ?>
                    <tr><td colspan="6" class="text-center text-muted">No students found.</td></tr>
                <?php endif; ?>

                <?php foreach ($rows as $r): ?>
                    <?php
                    // Get everything ready for this row
                    $parentName  = $guardianNames[$r['guardian_id']] ?? 'No parent yet';
                    $parentClass = $r['guardian_id'] ? '' : 'text-muted';
                    $assignLabel = $r['guardian_id'] ? 'Change parent' : 'Assign parent';
                    $rowClass    = ($r['id'] == $assignId) ? 'table-warning' : '';
                    $isThisOne   = ($r['id'] == $assignId);
                    ?>

                    <!-- Row type 1: this student is being edited -->
                    <?php if ($r['id'] == $editId): ?>
                        <tr>
                            <td><?= $r['id'] ?></td>
                            <td><input form="editForm" name="fname" class="form-control form-control-sm" value="<?= htmlspecialchars($r['fname']) ?>" required></td>
                            <td><input form="editForm" name="lname" class="form-control form-control-sm" value="<?= htmlspecialchars($r['lname']) ?>" required></td>
                            <td><input form="editForm" name="grade_level" class="form-control form-control-sm" value="<?= htmlspecialchars($r['grade_level'] ?? '') ?>"></td>
                            <td class="<?= $parentClass ?>"><?= htmlspecialchars($parentName) ?></td>
                            <td>
                                <button form="editForm" class="btn btn-sm btn-success">Save</button>
                                <a href="<?= makeUrl($self, $state) ?>" class="btn btn-sm btn-secondary">Cancel</a>
                            </td>
                        </tr>
                        <?php continue; ?>
                    <?php endif; ?>

                    <!-- Row type 2: a normal row -->
                    <tr class="<?= $rowClass ?>">
                        <td><?= $r['id'] ?></td>
                        <td><?= htmlspecialchars($r['fname']) ?></td>
                        <td><?= htmlspecialchars($r['lname']) ?></td>
                        <td><?= htmlspecialchars($r['grade_level'] ?? '') ?></td>
                        <td class="<?= $parentClass ?>"><?= htmlspecialchars($parentName) ?></td>
                        <!-- <td><?= makeUrl($self, $state, ['assign' => $r['id'], 'scrollToDiv' => 'guardiansDiv']) ?></td> -->
                        <td class="text-nowrap">

                            <!-- Button to start choosing a parent -->
                            <?php if (!$isThisOne): ?>
                                <a href="<?= makeUrl($self, $state, ['assign' => $r['id']]) . '#guardiansDiv' ?>"
                                   class="btn btn-sm btn-outline-success"><?= $assignLabel ?></a>
                            <?php endif; ?>

                            <!-- Button to stop choosing -->
                            <?php if ($isThisOne): ?>
                                <a href="<?= makeUrl($self, $state, ['assign' => null]) ?>"
                                   class="btn btn-sm btn-secondary">Done</a>
                            <?php endif; ?>

                            <a href="<?= makeUrl($self, $state, ['edit' => $r['id'], 'assign' => null]) ?>"
                               class="btn btn-sm btn-outline-primary">Edit</a>

                            <button class="btn btn-sm btn-outline-danger"
                                    hx-post="<?= $handler ?>"
                                    hx-vals='<?= postValues($state, ['action' => 'delete', 'id' => $r['id']]) ?>'
                                    hx-confirm="Delete this student?">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <!-- i hate this code -->
            <!-- some reason when user go to guardian pagination the student pagination resets(go back to page 1) -->
            <?php renderPagination(makeUrl($self, $state, ['gpage' => $gpage - 1, 'gPageNum' => $gpage]) ,$StudentPage ,"StudentPageNum" ,$totalPages); ?>
        </div>


        <!--GUARDIANS-->
        <div id="guardiansDiv">

            <h5>Guardians</h5>

            <!-- Message shown only while choosing a parent -->
            <?php if ($assigning): ?>
                <div class="alert alert-warning py-2">
                    Choose a parent for
                    <strong><?= htmlspecialchars($assignStudent['fname'] . ' ' . $assignStudent['lname']) ?></strong>
                </div>
            <?php endif; ?>

            <!-- Button to remove the parent the student has now -->
            <?php if ($assigning && $currentGuardianId): ?>
                <form hx-post="<?= $handler ?>" class="mb-3">
                    <input type="hidden" name="action" value="assign">
                    <input type="hidden" name="id" value="<?= $assignId ?>">
                    <input type="hidden" name="guardian_id" value="">
                    <?php hiddenFields($state); ?>
                    <button class="btn btn-sm btn-outline-danger">Remove current parent</button>
                </form>
            <?php endif; ?>

            <!-- SEARCH guardians -->
            <form method="get" action="<?= $self ?>" class="mb-3 d-flex gap-2">
                <?php hiddenFields($state, ['gsearch', 'gpage']); ?>
                <input type="text" name="gsearch" class="form-control w-auto"
                       value="<?= htmlspecialchars($gsearch) ?>" placeholder="Search guardians...">
                <button class="btn btn-outline-primary">Find</button>
                <a href="<?= makeUrl($self, $state, ['gsearch' => null, 'gpage' => null]) ?>"
                   class="btn btn-outline-secondary">Clear</a>
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
                    <?php
                    $kids      = $childrenOf[$g['id']] ?? [];
                    $isCurrent = ($g['id'] == $currentGuardianId);
                    $rowClass  = $isCurrent ? 'table-success' : '';
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td><?= htmlspecialchars($g['fname'] . ' ' . $g['lname']) ?></td>
                        <td><?= htmlspecialchars($g['phone'] ?? '') ?></td>

                        <!-- All the children of this guardian, one per line -->
                        <td>
                            <?php foreach ($kids as $kidName): ?>
                                <div><?= htmlspecialchars($kidName) ?></div>
                            <?php endforeach; ?>
                            <?php if (!$kids): ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>

                        <!-- Assign button (only while choosing a parent) -->
                        <td class="text-end">
                            <?php if ($assigning && $isCurrent): ?>
                                <span class="badge text-bg-success">Current</span>
                            <?php endif; ?>

                            <?php if ($assigning && !$isCurrent): ?>
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
            <!-- Guardian pages: Prev / Next -->
            <!-- <?= makeUrl($self, $state, ['gpage' => $gpage - 1, 'StudentPageNum' => $StudentPage]) ?> -->
            <?= http_build_query($state) ?>
            <?php renderPagination(makeUrl($self, $state, ['gpage' => $gpage - 1, 'StudentPageNum' => $StudentPage]), $gpage, "gPageNum", $gTotalPages); ?>

            <!-- <div class="d-flex justify-content-between align-items-center">
                <a href="<?= makeUrl($self, $state, ['gpage' => $gpage - 1]) ?>"
                   class="btn btn-sm btn-outline-secondary <?= $gPrevClass ?>">&laquo; Prev</a>

                <small class="text-muted">Page <?= $gpage ?> of <?= $gTotalPages ?></small>

                <a href="<?= makeUrl($self, $state, ['gpage' => $gpage + 1]) ?>"
                   class="btn btn-sm btn-outline-secondary <?= $gNextClass ?>">Next &raquo;</a>
            </div> -->
        </div>

    </div>
</div>

<script>
// Source - https://stackoverflow.com/a/8780717
// Posted by ThinkingStiff, modified by community. See post 'Timeline' for change history
// Retrieved 2026-10-13, License - CC BY-SA 3.0

function top() {
    document.getElementById( 'top' ).scrollIntoView();    
};

function bottom() {
    document.getElementById( 'bottom' ).scrollIntoView();
    window.setTimeout( function () { top(); }, 2000 );
};

bottom();

</script>
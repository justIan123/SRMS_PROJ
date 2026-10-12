<?php
require_once("../public/initialize.php");
require_once(CONFIG_PATH . DS . "db_sqlite.php");
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");

$year    = CurrentSchoolYear($pdo);
$action  = $_POST['action'] ?? '';
$message = '';

if ($year) {

    if ($action === 'create') {
        InsertRecord($pdo, 'students', [
            'school_year_id' => $year['id'],
            'guardian_id'    => (int)$_POST['guardian_id'],
            'fname'          => trim($_POST['fname']),
            'lname'          => trim($_POST['lname']),
            'grade_level'    => trim($_POST['grade_level']) ?: null,
        ]);
        $message = renderAlert('Student added', 'success');
    }

    if ($action === 'update') {
        UpdateRecord($pdo, 'students', (int)$_POST['id'], [
            'guardian_id' => (int)$_POST['guardian_id'],
            'fname'       => trim($_POST['fname']),
            'lname'       => trim($_POST['lname']),
            'grade_level' => trim($_POST['grade_level']) ?: null,
        ]);
        $message = renderAlert('Student updated', 'success');
    }

    if ($action === 'delete') {
        DeleteRecord($pdo, 'students', (int)$_POST['id']);
        $message = renderAlert('Student deleted', 'success');
    }
}

// keep the same sort, search and page
$_GET = [
    'sort'   => $_POST['sort']   ?? '',
    'dir'    => $_POST['dir']    ?? '',
    'page'   => $_POST['page']   ?? 1,
    'search' => $_POST['search'] ?? '',
];

require_once(COMPONENTS_PATH . DS . "manage_students.php");
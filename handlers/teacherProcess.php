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
        InsertRecord($pdo, 'teachers', [
            'school_year_id' => $year['id'],
            'fname'          => trim($_POST['fname']),
            'lname'          => trim($_POST['lname']),
            'phone'          => trim($_POST['phone']) ?: null,
            'email'          => trim($_POST['email']) ?: null,
        ]);
        $message = renderAlert('Teacher added.', 'success');
    }

    if ($action === 'update') {
        UpdateRecord($pdo, 'teachers', (int)$_POST['id'], [
            'fname' => trim($_POST['fname']),
            'lname' => trim($_POST['lname']),
            'phone' => trim($_POST['phone']) ?: null,
            // 'email' => trim($_POST['email']) ?: null,
        ]);
        $message = renderAlert('Teacher updated.', 'success');
    }

    if ($action === 'delete') {
        DeleteRecord($pdo, 'teachers', (int)$_POST['id']);
        $message = renderAlert('Teacher deleted.', 'success');
    }
}

// keep the same sort, search and page
$_GET = [
    'sort'   => $_POST['sort']   ?? '',
    'dir'    => $_POST['dir']    ?? '',
    'page'   => $_POST['page']   ?? 1,
    'search' => $_POST['search'] ?? '',
];

require_once(COMPONENTS_PATH . DS . "manage_teachers.php");
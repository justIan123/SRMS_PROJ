<?php
require_once("../public/initialize.php");
require_once(CONFIG_PATH . DS . "db_sqlite.php");
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php");
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");

$year    = CurrentSchoolYear($pdo);
$action  = $_POST['action'] ?? '';
$message = '';

if ($year) {

    if ($action === 'create') {
        InsertRecord($pdo, 'guardians', [
            'school_year_id' => $year['id'],
            'fname'          => trim($_POST['fname']),
            'lname'          => trim($_POST['lname']),
            'phone'          => trim($_POST['phone']) ?: null,
            'email'          => trim($_POST['email']) ?: null,
        ]);
        $message = alertBox('success', 'Guardian added.');
    }

    if ($action === 'update') {
        UpdateRecord($pdo, 'guardians', (int)$_POST['id'], [
            'fname' => trim($_POST['fname']),
            'lname' => trim($_POST['lname']),
            'phone' => trim($_POST['phone']) ?: null,
            'email' => trim($_POST['email']) ?: null,
        ]);
        $message = alertBox('success', 'Guardian updated.');
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];

        // do not delete a guardian that still has students
        if (CountRecords($pdo, 'students', ['guardian_id' => $id]) > 0) {
            $message = alertBox('danger', 'This guardian still has students. Change or delete the students first.');
        } else {
            DeleteRecord($pdo, 'guardians', $id);
            $message = alertBox('success', 'Guardian deleted.');
        }
    }
}

// Hand the page its state back (same keys as $state in manage_guardians.php)
$_GET = [
    'sort'   => $_POST['sort']   ?? '',
    'dir'    => $_POST['dir']    ?? '',
    'gpage'  => $_POST['gpage']  ?? 1,
    'search' => $_POST['search'] ?? '',
];

require_once(COMPONENTS_PATH . DS . "manage_guardians.php");
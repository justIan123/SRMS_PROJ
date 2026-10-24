<?php
require_once("../public/initialize.php");
require_once(CONFIG_PATH . DS . "db_sqlite.php");
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php");
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");

$year    = CurrentSchoolYear($pdo);
$action  = $_POST['action'] ?? '';
$message = '';
$isAdminExist = RecordExists($pdo, 'user_roles', 'role', 1);

try{
    if ($year) {

    if ($action === 'create') {
        InsertRecord($pdo, 'users', [
            'school_year_id' => $year['id'],
            'fname'          => trim($_POST['fname']),
            'lname'          => trim($_POST['lname']),
            'phone'          => trim($_POST['phone']) ?: null,
            'username'          => trim($_POST['username']),
            'email'          => trim($_POST['email']) ?: null,
        ]);
        $message = renderAlert('User added', 'success.');
    }

    if ($action === 'update') {
        UpdateRecord($pdo, 'users', (int)$_POST['id'], [
            'fname' => trim($_POST['fname']),
            'lname' => trim($_POST['lname']),
            'phone' => trim($_POST['phone']) ?: null,
            'username' => trim($_POST['username']) ?: null,
            // 'email' => trim($_POST['email']) ?: null,
        ]);

        if($_POST['assignRole']){
            UpdateRecord($pdo, 'user_roles', $_POST['id'],[
                'role' => trim($_POST['assignRole'])
            ], $idColumn = 'user_id'
            );
        }

        $message = renderAlert('User updated', 'success.');
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];

        // do not delete a guardian that still has students
        if (CountRecords($pdo, 'students', ['guardian_id' => $id]) > 0) {
            $message = renderAlert('This guardian still has students. Change or delete the students first.', 'danger');
        } else {
            DeleteRecord($pdo, 'guardians', $id);
            $message = renderAlert('Guardian deleted.', 'success');
        }
    }
}
}catch(PDOException $e){
    $msg = SetErrorMessage($e);
    // err_log($e->getMessage());
    $message = renderAlert($e->getMessage(), 'success');
}


// Hand the page its state back (same keys as $state in manage_users.php)
$_GET = [
    'sort'         => $_POST['sort']         ?? '',
    'dir'          => $_POST['dir']          ?? '',
    'PageNum'      => $_POST['PageNum']      ?? 1,
    'search'       => $_POST['search']       ?? '',
    'PerPageLimit' => $_POST['PerPageLimit'] ?? 10,
];

require_once(COMPONENTS_PATH . DS . "manage_users.php");
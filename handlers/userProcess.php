<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php");
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . DS . "debugFunctions.php");

$year         = CurrentSchoolYear($pdo);
$action       = $_POST['action'] ?? '';
$message      = '';
$isAdminExist = RecordExists($pdo, 'user_roles', 'role', 1);

try {
    if ($year) {

        if ($action === 'create') {
            // FIXED: Hashing the actual password input instead of the email
            $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
            
            $pdo->beginTransaction();

            // FIXED: Included fname, lname, and phone fields in the insert statement
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, fname, lname, phone) VALUES (:username, :email, :password, :fname, :lname, :phone) RETURNING id");
            $stmt->execute([
                ':username' => trim($_POST['username']) ?: null,
                ':email'    => trim($_POST['email'])    ?: null,
                ':password' => $hashedPassword,
                ':fname'    => trim($_POST['fname'])    ?: 'NONE',
                ':lname'    => trim($_POST['lname'])    ?: 'NONE',
                ':phone'    => trim($_POST['phone'])    ?: null
            ]);

            $userId = $stmt->fetchColumn();

            $stmtRole = $pdo->prepare("INSERT INTO user_roles (user_id, role) VALUES (:user_id, :role)");
            $stmtRole->execute([
                ':user_id' => $userId,
                ':role'    => trim($_POST['createAssignedRole']) ?: null,
            ]);

            $pdo->commit();
            $message = renderAlert('User added successfully.', 'success');
        }

        if ($action === 'update') {
            UpdateRecord($pdo, 'users', (int)$_POST['id'], [
                'fname'    => trim($_POST['fname']),
                'lname'    => trim($_POST['lname']),
                'phone'    => trim($_POST['phone'])    ?: null,
                'username' => trim($_POST['username']) ?: null,
            ]);

            if (!empty($_POST['assignRole'])) {
                UpdateRecord($pdo, 'user_roles', (int)$_POST['id'], [
                    'role' => trim($_POST['assignRole'])
                ], 'user_id');
            }

            $message = renderAlert('User updated successfully.', 'success');
        }

        if ($action === 'delete') {
            $id = (int)$_POST['id'];

            // FIXED: Corrected context mismatch from 'guardians' to 'users' and managed user_roles cleanup
            $pdo->beginTransaction();

            $stmtRoleDel = $pdo->prepare("DELETE FROM user_roles WHERE user_id = :user_id");
            $stmtRoleDel->execute([':user_id' => $id]);

            DeleteRecord($pdo, 'users', $id);

            $pdo->commit();
            $message = renderAlert('User deleted successfully.', 'success');
        }
    
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $msg = SetErrorMessage($e);
    err_log($e->getMessage());
    // FIXED: Changed alert type from 'success' to 'danger' for database exceptions
    $message = renderAlert($e->getMessage(), 'danger');
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
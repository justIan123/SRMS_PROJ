<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action  = $_POST['action'] ?? '';
$message = '';
$myId    = (int)($_SESSION['user_id'] ?? 0);   // the logged-in user (you can't delete yourself)

try {
    $pdo->beginTransaction();

    $id       = (int)($_POST['id'] ?? 0);
    $fname    = trim($_POST['fname'] ?? '');
    $lname    = trim($_POST['lname'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $roleId   = (int)($_POST['assignRole'] ?? 0);   // 0 = no role chosen

    // Is the chosen role a real one, and not admin? (admin can't be given here)
    $roleOk = false;
    if ($roleId) {
        $stmt = $pdo->prepare("SELECT 1 FROM roles WHERE role_id = :role AND LOWER(role) <> 'admin'");
        $stmt->execute([':role' => $roleId]);
        $roleOk = (bool)$stmt->fetchColumn();
    }

    // For update and delete: the user, and whether it is an admin
    $target = null;
    if ($action === 'update' || $action === 'delete') {
        $stmt = $pdo->prepare("SELECT u.id,
                                      EXISTS (SELECT 1
                                              FROM user_roles ur
                                              JOIN roles r ON r.role_id = ur.role
                                              WHERE ur.user_id = u.id AND LOWER(r.role) = 'admin') AS is_admin
                               FROM users u
                               WHERE u.id = :id");
        $stmt->execute([':id' => $id]);
        $target = $stmt->fetch() ?: null;
    }

    if ($action === 'create') {
        $error = null;
        if ($fname === '' || $lname === '') {
            $error = 'First and last name are required.';
        } elseif (!preg_match('/^[A-Za-z0-9._-]{3,30}$/', $username)) {
            $error = 'Username must be 3-30 characters: letters, numbers, dot, dash or underscore.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($phone !== '' && !preg_match('/^[0-9+()\s-]{7,20}$/', $phone)) {
            $error = 'Please enter a valid phone number.';
        } elseif (strlen($password) < 8) {
            $error = 'The password must be at least 8 characters.';
        } elseif (!$roleOk) {
            $error = 'Pick a role.';
        }

        if ($error) {
            $message = renderAlert($error, 'danger');
        } else {
            // Postgres: lastInsertId() needs a sequence name, so ask the INSERT to return the id instead
            $stmt = $pdo->prepare("INSERT INTO users (fname, lname, phone, username, email, password)
                                   VALUES (:fname, :lname, :phone, :username, :email, :password)
                                   RETURNING id");
            $stmt->execute([
                ':fname'    => $fname,
                ':lname'    => $lname,
                ':phone'    => $phone ?: null,
                ':username' => $username,
                ':email'    => $email,
                ':password' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $userId = $stmt->fetchColumn();

            $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role) VALUES (:user_id, :role)");
            $stmt->execute([':user_id' => $userId, ':role' => $roleId]);

            $message = renderAlert('User added.', 'success');
        }

    } elseif ($action === 'update') {
        $error = null;
        if (!$target) {
            $error = 'User not found.';
        } elseif ($target['is_admin']) {
            $error = 'The admin account cannot be changed here.';
        } elseif ($fname === '' || $lname === '') {
            $error = 'First and last name are required.';
        } elseif (!preg_match('/^[A-Za-z0-9._-]{3,30}$/', $username)) {
            $error = 'Username must be 3-30 characters: letters, numbers, dot, dash or underscore.';
        } elseif ($phone !== '' && !preg_match('/^[0-9+()\s-]{7,20}$/', $phone)) {
            $error = 'Please enter a valid phone number.';
        } elseif ($roleId && !$roleOk) {
            $error = 'Pick a valid role.';
        }

        if ($error) {
            $message = renderAlert($error, 'danger');
        } else {
            // the email is not changed here
            $stmt = $pdo->prepare("UPDATE users
                                   SET fname = :fname, lname = :lname, phone = :phone, username = :username
                                   WHERE id = :id");
            $stmt->execute([
                ':fname'    => $fname,
                ':lname'    => $lname,
                ':phone'    => $phone ?: null,
                ':username' => $username,
                ':id'       => $id,
            ]);

            // a new role replaces the old one(s); no role chosen = leave the roles alone
            if ($roleId) {
                $stmt = $pdo->prepare("DELETE FROM user_roles WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $id]);

                $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role) VALUES (:user_id, :role)");
                $stmt->execute([':user_id' => $id, ':role' => $roleId]);
            }

            $message = renderAlert('User updated.', 'success');
        }

    } elseif ($action === 'delete') {
        if (!$target) {
            $message = renderAlert('User not found.', 'danger');
        } elseif ($target['is_admin']) {
            $message = renderAlert('The admin account cannot be deleted.', 'danger');
        } elseif ($id === $myId) {
            $message = renderAlert('You cannot delete your own account.', 'danger');
        } else {
            // a user that still owns tests is not deleted
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tests WHERE teacher_id = :id");
            $stmt->execute([':id' => $id]);

            if ((int)$stmt->fetchColumn() > 0) {
                $message = renderAlert('This user still has tests. Delete the tests first.', 'danger');
            } else {
                $stmt = $pdo->prepare("DELETE FROM teacher_subjects WHERE teacher_id = :id");
                $stmt->execute([':id' => $id]);

                $stmt = $pdo->prepare("DELETE FROM user_roles WHERE user_id = :id");
                $stmt->execute([':id' => $id]);

                $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
                $stmt->execute([':id' => $id]);

                $message = renderAlert('User deleted.', 'success');
            }
        }
    }

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    err_log($e->getMessage());
    $message = renderAlert(SetErrorMessage($e), 'danger');
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

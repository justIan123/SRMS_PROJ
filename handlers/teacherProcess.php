<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");

// manage_teachers.php lists rows of the "users" table, so this handler works on "users" too.
// (The old version wrote to a "teachers" table that the page never read.)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action  = $_POST['action'] ?? '';
$message = '';
$myId    = (int)($_SESSION['user_id'] ?? 0);   // the logged-in user (you can't delete yourself)

try {
    $pdo->beginTransaction();

    $id    = (int)($_POST['id'] ?? 0);
    $fname = trim($_POST['fname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $phone = trim($_POST['phone'] ?? '') ?: null;

    if ($action === 'create') {
        // A user needs a username and a password, which this form does not have.
        $message = renderAlert('Teachers are added from the Users page: create the user and pick the Teacher role.', 'warning');

    } elseif ($action === 'update') {
        if ($fname === '' || $lname === '') {
            $message = renderAlert('First and last name are required.', 'danger');
        } else {
            // the email is not changed here
            $stmt = $pdo->prepare("UPDATE users SET fname = :fname, lname = :lname, phone = :phone WHERE id = :id");
            $stmt->execute([':fname' => $fname, ':lname' => $lname, ':phone' => $phone, ':id' => $id]);

            $message = $stmt->rowCount()
                ? renderAlert('Teacher updated.', 'success')
                : renderAlert('Teacher not found.', 'danger');
        }

    } elseif ($action === 'delete') {
        // is it an admin? does the user exist?
        $stmt = $pdo->prepare("SELECT u.id,
                                      EXISTS (SELECT 1
                                              FROM user_roles ur
                                              JOIN roles r ON r.role_id = ur.role
                                              WHERE ur.user_id = u.id AND LOWER(r.role) = 'admin') AS is_admin
                               FROM users u
                               WHERE u.id = :id");
        $stmt->execute([':id' => $id]);
        $target = $stmt->fetch() ?: null;

        if (!$target) {
            $message = renderAlert('Teacher not found.', 'danger');
        } elseif ($target['is_admin']) {
            $message = renderAlert('The admin account cannot be deleted.', 'danger');
        } elseif ($id === $myId) {
            $message = renderAlert('You cannot delete your own account.', 'danger');
        } else {
            // a teacher that still owns tests is not deleted
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tests WHERE teacher_id = :id");
            $stmt->execute([':id' => $id]);

            if ((int)$stmt->fetchColumn() > 0) {
                $message = renderAlert('This teacher still has tests. Delete the tests first.', 'danger');
            } else {
                $stmt = $pdo->prepare("DELETE FROM teacher_subjects WHERE teacher_id = :id");
                $stmt->execute([':id' => $id]);

                $stmt = $pdo->prepare("DELETE FROM user_roles WHERE user_id = :id");
                $stmt->execute([':id' => $id]);

                $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
                $stmt->execute([':id' => $id]);

                $message = renderAlert('Teacher deleted.', 'success');
            }
        }
    }

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    err_log($e->getMessage());
    $message = renderAlert('Could not save. Please check the data and try again.', 'danger');
}

// Hand the page its state back (same keys as $state in manage_teachers.php)
$_GET = [
    'sort'         => $_POST['sort']         ?? '',
    'dir'          => $_POST['dir']          ?? '',
    'tpage'        => $_POST['tpage']        ?? 1,
    'search'       => $_POST['search']       ?? '',
    'PerPageLimit' => $_POST['PerPageLimit'] ?? 10,
];

require_once(COMPONENTS_PATH . DS . "manage_teachers.php");

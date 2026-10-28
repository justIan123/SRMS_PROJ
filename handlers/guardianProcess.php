<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");

$action  = $_POST['action'] ?? '';
$message = '';

try {
    // Everything belongs to the current school year
    $year = CurrentSchoolYear($pdo);

    if ($year) {
        $pdo->beginTransaction();

        $id    = (int)($_POST['id'] ?? 0);
        $fname = trim($_POST['fname'] ?? '');
        $lname = trim($_POST['lname'] ?? '');
        $phone = trim($_POST['phone'] ?? '') ?: null;
        $email = trim($_POST['email'] ?? '') ?: null;

        if ($action === 'create') {
            if ($fname === '' || $lname === '') {
                $message = alertBox('danger', 'First name and last name are required.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO guardians (school_year_id, fname, lname, phone, email)
                                       VALUES (:year, :fname, :lname, :phone, :email)");
                $stmt->execute([
                    ':year'  => $year['id'],
                    ':fname' => $fname,
                    ':lname' => $lname,
                    ':phone' => $phone,
                    ':email' => $email,
                ]);
                $message = alertBox('success', 'Guardian added.');
            }

        } elseif ($action === 'update') {
            if ($fname === '' || $lname === '') {
                $message = alertBox('danger', 'First name and last name are required.');
            } else {
                $stmt = $pdo->prepare("UPDATE guardians
                                       SET fname = :fname, lname = :lname, phone = :phone, email = :email
                                       WHERE id = :id AND school_year_id = :year");
                $stmt->execute([
                    ':fname' => $fname,
                    ':lname' => $lname,
                    ':phone' => $phone,
                    ':email' => $email,
                    ':id'    => $id,
                    ':year'  => $year['id'],
                ]);
                $message = $stmt->rowCount()
                    ? alertBox('success', 'Guardian updated.')
                    : alertBox('danger', 'Guardian not found.');
            }

        } elseif ($action === 'delete') {
            // do not delete a guardian that still has students
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE guardian_id = :id");
            $stmt->execute([':id' => $id]);

            if ((int)$stmt->fetchColumn() > 0) {
                $message = alertBox('danger', 'This guardian still has students. Change or delete the students first.');
            } else {
                $stmt = $pdo->prepare("DELETE FROM guardians WHERE id = :id AND school_year_id = :year");
                $stmt->execute([':id' => $id, ':year' => $year['id']]);
                $message = $stmt->rowCount()
                    ? alertBox('success', 'Guardian deleted.')
                    : alertBox('danger', 'Guardian not found.');
            }
        }

        $pdo->commit();
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    err_log($e->getMessage());
    $message = alertBox('danger', 'Could not save. Please check the data and try again.');
}

// Hand the page its state back (same keys as $state in manage_guardians.php)
$_GET = [
    'sort'   => $_POST['sort']   ?? '',
    'dir'    => $_POST['dir']    ?? '',
    'gpage'  => $_POST['gpage']  ?? 1,
    'search' => $_POST['search'] ?? '',
];

require_once(COMPONENTS_PATH . DS . "manage_guardians.php");
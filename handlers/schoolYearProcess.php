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
    $pdo->beginTransaction();

    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'create') {
        $name  = trim($_POST['name'] ?? '');
        $start = $_POST['start_date'] ?? '';
        $end   = $_POST['end_date'] ?? '';

        if ($name === '' || $start === '' || $end === '') {
            $message = renderAlert('Name, start date and end date are required.', 'danger');
        } elseif ($end <= $start) {
            $message = renderAlert('The end date must be after the start date.', 'danger');
        } else {
            $stmt = $pdo->prepare("INSERT INTO school_years (name, start_date, end_date, is_active)
                                   VALUES (:name, :start_date, :end_date, :active)");
            $stmt->execute([
                ':name'       => $name,
                ':start_date' => $start,
                ':end_date'   => $end,
                ':active'     => 0,
            ]);
            $message = renderAlert('School year created. Press Start to make it the current one.', 'success');
        }

    } elseif ($action === 'start') {
        $stmt = $pdo->prepare("SELECT 1 FROM school_years WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if (!$stmt->fetchColumn()) {
            $message = renderAlert('School year not found.', 'danger');
        } else {
            // only one can run at a time: end all, then start this one.
            // If the second statement fails, the rollback brings the old one back.
            $stmt = $pdo->prepare("UPDATE school_years SET is_active = :active");
            $stmt->execute([':active' => 0]);

            $stmt = $pdo->prepare("UPDATE school_years SET is_active = :active WHERE id = :id");
            $stmt->execute([':active' => 1, ':id' => $id]);

            $message = renderAlert('School year started.', 'success');
        }

    } elseif ($action === 'end') {
        $stmt = $pdo->prepare("UPDATE school_years SET is_active = :active WHERE id = :id");
        $stmt->execute([':active' => 0, ':id' => $id]);

        $message = $stmt->rowCount()
            ? renderAlert('<strong>Success!</strong> School year ended.', 'success')
            : renderAlert('School year not found.', 'danger');
    }

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    err_log($e->getMessage());
    $message = renderAlert('Something went wrong.', 'warning');
}

// show the page again ($message is printed there)
require_once(COMPONENTS_PATH . DS . "manage_schoolYear.php");

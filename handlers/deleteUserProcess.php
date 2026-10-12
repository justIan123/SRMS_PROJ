<?php
require_once("../public/initialize.php");
require_once(CONFIG_PATH . DS . "db_sqlite.php");
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php");

DeleteRecord($pdo, 'users', (int)$_POST['id']);

try {
    DeleteRecord($pdo, 'users', (int)$_POST['id']);

    $msg = renderAlert("User successfully Deleted", "success");
} catch (PDOException $e) {
    $msg = SetErrorMessage($e);
    $msg = renderAlert(
        "Something went wrong.",
        "warning"
    );

}

// keep the same sort, search and page (the page clamps itself if it became empty)
$_GET = [
    'search' => $_POST['search'] ?? '',
    'sort'   => $_POST['sort']   ?? '',
    'dir'    => $_POST['dir']    ?? '',
    'page'   => $_POST['page']   ?? 1,
];

require_once(COMPONENTS_PATH . DS . "manage_users.php");
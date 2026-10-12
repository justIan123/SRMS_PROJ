<?php
require_once("../public/initialize.php");
require_once(CONFIG_PATH . DS . "db_sqlite.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php");

try {

    UpdateRecord($pdo, 'users', (int)$_POST['id'], [
        'username' => trim($_POST['username']),
        'email'    => trim($_POST['email']) ?: null,
    ]);

    $msg = renderAlert("User successfully updated", "success");

} catch (PDOException $e) {
    $msg = SetErrorMessage($e);
    $msg = renderAlert(
        "Email already exists. Please use another email.",
        "warning"
    );

}
// keep the same sort, search and page, leave edit mode
$_GET = [
    'sort'   => $_POST['sort']   ?? '',
    'dir'    => $_POST['dir']    ?? '',
    'page'   => $_POST['page']   ?? 1,
    'search' => $_POST['search'] ?? '',
];

require_once(COMPONENTS_PATH . DS . "manage_users.php");
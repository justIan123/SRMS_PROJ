<?php
require_once("../public/initialize.php");
require_once(CONFIG_PATH . DS . "db_sqlite.php");
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php");

try {

    $result = UpdateRecord($pdo, 'users', (int)$_POST['id'], [
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

InsertRecord($pdo, 'users', [
    'username' => trim($_POST['username']),
    'email' => trim($_POST['email']) ?: null,
    'password' => password_hash($_POST['password'] ?? null, PASSWORD_DEFAULT)
]);

// show the list again (page 1, default sort)
require_once(COMPONENTS_PATH . DS . "manage_users.php");
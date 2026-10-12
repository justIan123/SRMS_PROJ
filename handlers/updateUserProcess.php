<?php
require_once("../public/initialize.php");
require_once(CONFIG_PATH . DS . "db_sqlite.php");
require_once(FUNCTIONS_PATH . DS . "securityFunctions.php");
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php");

// Plain-text error (shown in an alert by the modal)
function fail(int $status, string $message): void
{
    http_response_code($status);
    echo $message;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, 'Method not allowed.');
}

// 1. Read + validate
$id       = (int)($_POST['id'] ?? 0);
$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');
$role     = (int)($_POST['role'] ?? 0);   // 1 = Admin, 2 = Teacher

if ($id < 1)                                   fail(422, 'Invalid user.');
if ($username === '')                          fail(422, 'Username is required.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail(422, 'Invalid email address.');
if (!in_array($role, [1, 2], true))            fail(422, 'Invalid role.');

try {
    // 2. Username / email must not belong to a different user
    foreach (['username' => $username, 'email' => $email] as $column => $value) {
        $found = FindWhere($pdo, 'users', [$column => $value], 1);
        if ($found && (int)$found[0]['id'] !== $id) {
            fail(409, ucfirst($column) . ' is already taken.');
        }
    }

    // 3. Save
    UpdateRecord($pdo, 'users', $id, ['username' => $username, 'email' => $email]);

    if (RecordExists($pdo, 'user_roles', 'user_id', $id)) {
        UpdateRecord($pdo, 'user_roles', $id, ['role' => $role], 'user_id');
    } else {
        InsertRecord($pdo, 'user_roles', ['user_id' => $id, 'role' => $role]);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    fail(500, 'Could not update the user.');
}

// 4. Return the refreshed table
require_once("readUsers.php");
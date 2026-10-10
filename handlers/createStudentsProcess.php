<?php
require_once("../public/index.php");
require_once(CONFIG_PATH . DS . "db_sqlite.php");
require_once(FUNCTIONS_PATH . DS . "securityFunctions.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    exit;
}

$username = sanitize($_POST['username'] ?? null, 'string');
$email    = sanitize($_POST['email'] ?? null, 'email');
$password = $_POST['password'] ?? null;

if (empty($username) || empty($email) || empty($password)) {
    echo '<div class="alert alert-danger mb-3">Please fill in all required fields.</div>';
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (:username, :email, :password)");
    $stmt->execute([
        ':username' => $username,
        ':email'    => $email,
        ':password' => password_hash($password, PASSWORD_DEFAULT)
    ]);

    $userId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role) VALUES (:user_id, :role)");
    $stmt->execute([
        ':user_id' => $userId,
        ':role'    => 2
    ]);

    $pdo->commit();

    // Sends HX-Trigger header so HTMX reloads the user table automatically
    header("HX-Trigger: reloadUsers");

    echo '<div class="alert alert-success mb-3">User created successfully!</div>';

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo '<div class="alert alert-danger mb-3">Failed to create user. Email or Username may already exist.</div>';
}
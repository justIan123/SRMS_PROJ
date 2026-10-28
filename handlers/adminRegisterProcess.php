<?php
require_once("../public/initialize.php");

require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "securityFunctions.php");
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");

// check if admin exists
/*
    SELECT roles.role
    FROM users 
    JOIN user_roles ON user_roles.user_id = users.id
    JOIN roles on user_roles.role = role_id
    WHERE role_id = 1

    result must be admin
*/
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users JOIN user_roles ON user_roles.user_id = users.id JOIN roles on user_roles.role = role_id WHERE role_id = 1");
$stmt->execute();
$adminExists = $stmt->fetchColumn();
if ($adminExists > 0) {
    header("Location: ../app/login_page.php");
    exit;   // stop here, or the POST below still creates another admin
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = sanitize($_POST['username'] ?? null, 'string');
    $email = sanitize($_POST['email'] ?? null, 'email');
    $password = $_POST['password'] ?? null; 

    
    if (empty($username) || empty($password)) {
        $err = "Please fill in all fields.";
        return; // stop the process and  go back to registerProcess.php
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    

    try {

        $pdo->beginTransaction();

        // Postgres: lastInsertId() needs a sequence name, so ask the INSERT to return the id instead
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (:username, :email, :password) RETURNING id");
        $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password' => $hashedPassword
        ]);

        $userId = $stmt->fetchColumn();
        show($userId);

        $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role) VALUES (:user_id, :role)");
        $stmt->execute([
            ':user_id' => $userId,
            ':role' => 1,
        ]);

        $pdo->commit();
        alert("Registered Successfully");
        header("Location: ../app/admin_register_page.php");
        exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // error_log($e->getMessage(), 3, LOGS_PATH . DS . "logs.txt");
        err_log($e->getMessage());

        $err = SetErrorMessage($e);

    }
}
?>
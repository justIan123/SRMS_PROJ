<?php
require_once("../public/initialize.php");

require_once(CONFIG_PATH . DS . "db_sqlite.php");
require_once(FUNCTIONS_PATH . DS . "securityFunctions.php");
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = sanitize($_POST['username'] ?? null, 'string');
    $email = sanitize($_POST['email'] ?? null, 'email');
    $password = $_POST['password'] ?? null; 

    
    if (empty($username) || empty($password) || empty($email) ){
        $err = "Please fill in all fields.";
        return; // stop the process and  go back to registerProcess.php
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (:username, :email, :password)");
        $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password' => $hashedPassword
        ]);

        $pdo->commit();

        header("Location: ../app/login_page.php");
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
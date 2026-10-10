<?php
require_once("../public/initialize.php");
session_start();

require_once(CONFIG_PATH . DS . "db_sqlite.php");
require_once(FUNCTIONS_PATH . DS . "securityFunctions.php");
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = sanitize($_POST['email'], 'string');
    $password = $_POST['password'];

    //create a password checker that checks if the password is 8 chars long

    if (empty($password) || empty($email) ){
        $err = "Please fill in all fields.";
        return; // stop the process and  go back to registerProcess.php
    }

    try{
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute([
            ':email' => $email
        ]);
        $user = $stmt->fetch();
    
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user['username'];
            header("Location: ../app/dashboard.php");
            exit;
        } else {
            $err = "Invalid username or password.";
        }

    }catch(PDOException $e){
        $err = "Invalid username or password.";
    }
}
?>

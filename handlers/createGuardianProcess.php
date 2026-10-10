<?php
require_once("../public/index.php");
require_once(CONFIG_PATH . DS . "db_sqlite.php");
require_once(FUNCTIONS_PATH . DS . "securityFunctions.php");
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . DS . "debugFunctions.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    exit;
}

$fname = sanitize($_POST['fname'] ?? null, 'string');
$lname = sanitize($_POST['lname'] ?? null, 'string');
$mname = sanitize($_POST['mname'] ?? null, 'string');
$email = sanitize($_POST['email'] ?? null, 'email');
$phone = sanitize($_POST['phone'] ?? null, 'phone');



if (empty($fname) || empty($lname)) {
    echo '<div class="alert alert-danger mb-3">Please fill in all required fields.</div>';
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO guardians (fname, mname, lname, phone, email) VALUES (:fname, :mname, :lname, :phone, :email)");
    $stmt->execute([
        ':fname' => $fname,
        ':mname' => $mname,
        ':lname' => $lname,
        ':phone' => $phone,
        ':email' => $email,
    ]);

    // $parentId = $pdo->lastInsertId();

    // $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role) VALUES (:user_id, :role)");
    // $stmt->execute([
    //     ':user_id' => $userId,
    //     ':role'    => 2
    // ]);

    $pdo->commit();

    // Sends HX-Trigger header so HTMX reloads the user table automatically
    header("HX-Trigger: reloadGuardians");

    echo '<div class="alert alert-success mb-3">User created successfully!</div>';

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    err_log($e->getMessage());

    $err = SetErrorMessage($e);

    echo '<div class="alert alert-danger mb-3">'. $err .'</div>';
}
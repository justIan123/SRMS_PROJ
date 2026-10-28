<?php
require_once("../public/initialize.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "securityFunctions.php");
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");

$err = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = sanitize(trim($_POST['email'] ?? ''), 'string');
    $password = $_POST['password'] ?? '';

    // No length rule here on purpose: the 8-character rule belongs to
    // registration and change-password, not to login.
    if ($email === '' || $password === '') {
        $err = "Please fill in all fields.";
        return; // stop and go back to the page that included this file
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT id, username, password, is_active FROM users WHERE email = :email"
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            // Same message for "no such email" and "wrong password"
            $err = "Invalid email or password.";

        } elseif (!(int)$user['is_active']) {
            $err = "This account is inactive. Please contact an administrator.";

        } else {
            // Upgrade the stored hash if PHP's default algorithm/cost has changed
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
                $stmt->execute([
                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                    ':id'       => $user['id'],
                ]);

                $pdo->commit();
            }

            // Load role ids for permission checks
            $stmt = $pdo->prepare("SELECT role FROM user_roles WHERE user_id = :id");
            $stmt->execute([':id' => $user['id']]);
            $roles = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            alert(implode(", ", $roles));
            // New session id after login prevents session fixation
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int)$user['id'];   // used by account.php / accountProcess.php
            $_SESSION['user']    = $user['username'];  // kept so existing pages keep working
            $_SESSION['roles']   = $roles;

            header("Location: ../app/dashboard.php");
            exit;
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        err_log($e->getMessage());

        $err = SetErrorMessage($e);
    }
}
?>
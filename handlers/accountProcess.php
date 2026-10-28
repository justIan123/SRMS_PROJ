<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = '';
$old     = [];
$action  = $_POST['action'] ?? '';

// The user id ALWAYS comes from the session, never from the form.
$userId = (int)($_SESSION['user_id'] ?? 0);

try {
    if (!$userId) {
        throw new RuntimeException('Your session has expired. Please log in again.');
    }

    // CSRF check
    $token = $_SESSION['csrf'] ?? '';
    if ($token === '' || !hash_equals($token, (string)($_POST['csrf'] ?? ''))) {
        throw new RuntimeException('Invalid form token. Reload the page and try again.');
    }

    // ---------- PROFILE ----------
    if ($action === 'profile') {
        $old = $_POST; // keep typed values if validation fails

        $fname    = trim($_POST['fname'] ?? '');
        $lname    = trim($_POST['lname'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');

        if ($fname === '' || $lname === '') {
            throw new RuntimeException('First and last name are required.');
        }
        if (!preg_match('/^[A-Za-z0-9._-]{3,30}$/', $username)) {
            throw new RuntimeException('Username must be 3–30 characters: letters, numbers, dot, dash or underscore.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid email address.');
        }
        if ($phone !== '' && !preg_match('/^[0-9+()\s-]{7,20}$/', $phone)) {
            throw new RuntimeException('Please enter a valid phone number.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE users
                               SET fname = :fname, lname = :lname, username = :username, email = :email, phone = :phone
                               WHERE id = :id");
        $stmt->execute([
            ':fname'    => $fname,
            ':lname'    => $lname,
            ':username' => $username,
            ':email'    => $email,
            ':phone'    => $phone ?: null,
            ':id'       => $userId,
        ]);

        $pdo->commit();

        $old     = [];
        $message = renderAlert('Profile updated.', 'success');
    }

    // ---------- PASSWORD ----------
    elseif ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($current, $hash)) {
            throw new RuntimeException('Your current password is incorrect.');
        }
        if (strlen($new) < 8) {
            throw new RuntimeException('The new password must be at least 8 characters.');
        }
        if ($new !== $confirm) {
            throw new RuntimeException('The new password and its confirmation do not match.');
        }
        if (password_verify($new, $hash)) {
            throw new RuntimeException('The new password must be different from the current one.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
        $stmt->execute([
            ':password' => password_hash($new, PASSWORD_DEFAULT),
            ':id'       => $userId,
        ]);

        $pdo->commit();

        session_regenerate_id(true);   // new session id after a credential change
        $message = renderAlert('Password changed.', 'success');
    }

    else {
        throw new RuntimeException('Unknown action.');
    }

} catch (PDOException $e) {
    // This one MUST come before RuntimeException: PDOException is a kind of RuntimeException,
    // so with the order the other way round, database errors were caught there, the rollback
    // never ran and the raw database message was shown to the user.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    err_log($e->getMessage());

    // 23505 = Postgres "unique violation" (23000 was the SQLite/MySQL code)
    if ($e->getCode() === '23505') {
        $what = (str_contains($e->getMessage(), 'Key (email)') || str_contains($e->getMessage(), 'users_email')) ? 'email' : 'username';
        $message = renderAlert("That $what is already in use.", 'danger');
    } else {
        $message = renderAlert(SetErrorMessage($e), 'danger');
    }

} catch (RuntimeException $e) {
    // Messages we wrote ourselves, safe to show.
    $message = renderAlert($e->getMessage(), 'danger');
}

require_once(COMPONENTS_PATH . DS . "manage_myAccount.php");
<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");

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

        UpdateRecord($pdo, 'users', $userId, [
            'fname'    => $fname,
            'lname'    => $lname,
            'username' => $username,
            'email'    => $email,
            'phone'    => $phone ?: null,
        ]);

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

        UpdateRecord($pdo, 'users', $userId, [
            'password' => password_hash($new, PASSWORD_DEFAULT),
        ]);

        session_regenerate_id(true);   // new session id after a credential change
        $message = renderAlert('Password changed.', 'success');
    }

    else {
        throw new RuntimeException('Unknown action.');
    }

} catch (RuntimeException $e) {
    // Messages we wrote ourselves, safe to show.
    $message = renderAlert($e->getMessage(), 'danger');

} catch (PDOException $e) {
    // Never show raw database errors to the user.
    if ($e->getCode() === '23000') {
        $what = str_contains($e->getMessage(), 'users.email') ? 'email' : 'username';
        $message = renderAlert("That $what is already in use.", 'danger');
    } else {
        error_log($e->getMessage());
        $message = renderAlert('Something went wrong. Please try again.', 'danger');
    }
}

require_once(COMPONENTS_PATH . DS . "manage_myAccount.php");
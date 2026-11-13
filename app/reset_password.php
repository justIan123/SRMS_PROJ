<?php
// reset_password.php  (public page, opened from the link in the email)
require_once("../config/initialize.php");   // adjust the path if this file is not in the web root
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");

// The token is in the URL, so stop the browser from leaking it to other sites or caching the page.
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$message = '';
$done    = false;   // becomes true once the password is changed
$userId  = false;   // stays false if the link is bad or the database fails

// The token arrives in the link (GET) or in the hidden field of the form (POST).
$resetToken = (string)($_POST['token'] ?? $_GET['token'] ?? '');

try {
    // STEP 1: whose reset link is this? ($userId is false if the link is wrong or expired)
    $stmt = $pdo->prepare("SELECT user_id
                           FROM password_resets
                           WHERE token_hash = :hash AND expires_at > now()");
    $stmt->execute([':hash' => hash('sha256', $resetToken)]);
    $userId = $stmt->fetchColumn();

    if (!$userId) {
        $message = renderAlert('This reset link is invalid or has expired.', 'danger');
    }

    // STEP 2: the form was submitted, so check it and save the new password
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $csrf = $_SESSION['csrf'] ?? '';
        if ($csrf === '' || !hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Invalid form token. Reload the page and try again.');
        }

        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($new) < 8) {
            throw new RuntimeException('The new password must be at least 8 characters.');
        }
        if ($new !== $confirm) {
            throw new RuntimeException('The new password and its confirmation do not match.');
        }

        $pdo->beginTransaction();

        // Save the new password
        $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
        $stmt->execute([
            ':password' => password_hash($new, PASSWORD_DEFAULT),
            ':id'       => $userId,
        ]);

        // Delete this user's reset links so none of them can be used again
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = :id");
        $stmt->execute([':id' => $userId]);

        $pdo->commit();

        $done    = true;
        $message = renderAlert('Your password has been changed. You can log in now.', 'success');
    }

} catch (PDOException $e2) {
    // Must come before RuntimeException (PDOException is a kind of RuntimeException).
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    err_log($e2->getMessage());
    $message = renderAlert(SetErrorMessage($e2), 'danger');

} catch (RuntimeException $e2) {
    // Messages we wrote ourselves, safe to show.
    $message = renderAlert($e2->getMessage(), 'danger');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Reset password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
<div class="container py-5" style="max-width:460px;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h4 class="mb-3">Reset your password</h4>

            <?= $message ?>

            <?php if ($done): ?>
                <a href="<?= $e(BASE_URL) ?>" class="btn btn-primary w-100">Go to login</a>

            <?php elseif (!$userId): ?>
                <a href="forgot_password.php" class="btn btn-outline-primary w-100">Request a new link</a>

            <?php else: ?>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>">
                    <input type="hidden" name="token" value="<?= $e($resetToken) ?>">

                    <div class="mb-3">
                        <label for="new_password" class="form-label">New password</label>
                        <input id="new_password" name="new_password" type="password" class="form-control"
                               minlength="8" autocomplete="new-password" required autofocus>
                        <div class="form-text">At least 8 characters.</div>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm new password</label>
                        <input id="confirm_password" name="confirm_password" type="password" class="form-control"
                               minlength="8" autocomplete="new-password" required>
                    </div>
                    <button class="btn btn-primary w-100">Change password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
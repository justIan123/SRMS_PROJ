<?php
// forgot_password.php  (public page, no login needed)
require_once("../config/initialize.php");   // adjust the path if this file is not in the web root
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");
require_once(FUNCTIONS_PATH . DS . "mailFunctions.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$e       = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$message = '';
$sent    = false;
$email   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF check
        $csrf = $_SESSION['csrf'] ?? '';
        if ($csrf === '' || !hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Invalid form token. Reload the page and try again.');
        }

        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid email address.');
        }

        // Only active accounts can reset.
        $stmt = $pdo->prepare("SELECT id, username, fname, email
                               FROM users
                               WHERE lower(email) = lower(:email) AND is_active = 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Throttle: at most one email per minute per account (also protects your daily email quota).
            $stmt = $pdo->prepare("SELECT 1 FROM password_resets
                                   WHERE user_id = :id AND created_at > now() - interval '1 minute'");
            $stmt->execute([':id' => $user['id']]);
            $tooSoon = (bool)$stmt->fetchColumn();

            if (!$tooSoon) {
                // The token goes in the email; only its hash goes in the database.
                $resetToken = bin2hex(random_bytes(32));

                $pdo->beginTransaction();

                // Clean up expired rows (free hosting has no cron) and drop this user's older links.
                $pdo->exec("DELETE FROM password_resets WHERE expires_at < now()");
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = :id");
                $stmt->execute([':id' => $user['id']]);

                $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at)
                                       VALUES (:uid, :hash, now() + interval '30 minutes')");
                $stmt->execute([
                    ':uid'  => $user['id'],
                    ':hash' => hash('sha256', $resetToken),
                ]);

                $pdo->commit();

                // ---- send the email through Brevo's HTTP API (InfinityFree blocks PHP mail()) ----
                $link = APP_URL . 'reset_password.php?token=' . $resetToken;
                $name = ($user['fname'] && $user['fname'] !== 'NONE') ? $user['fname'] : $user['username'];

                $html = '<p>Hi ' . $e($name) . ',</p>'
                      . '<p>We received a request to reset your password. Click the button below to choose a new one. '
                      . 'The link works once and expires in 30 minutes.</p>'
                      . '<p><a href="' . $e($link) . '" style="background:#0d6efd;color:#fff;padding:10px 18px;'
                      . 'text-decoration:none;border-radius:6px;display:inline-block;">Reset password</a></p>'
                      . '<p>Or paste this link into your browser:<br>' . $e($link) . '</p>'
                      . '<p>If you did not ask for this, you can ignore this email. Your password will not change.</p>';

                $text = "Hi $name,\n\n"
                      . "We received a request to reset your password. Open this link to choose a new one "
                      . "(it works once and expires in 30 minutes):\n$link\n\n"
                      . "If you did not ask for this, ignore this email. Your password will not change.";

                // A failure is logged inside sendEmail() but never shown to the visitor,
                // so they can't tell whether the email exists.
                sendEmail($user['email'], $name, 'Reset your password', $html, $text);
            }
        }

        // Same answer whether or not the email exists (prevents account enumeration).
        $sent    = true;
        $message = renderAlert('If that email belongs to an active account, a reset link is on its way. It expires in 30 minutes. Check your spam folder too.', 'success');

    } catch (PDOException $e2) {
        // Must come before RuntimeException (PDOException extends it).
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        err_log($e2->getMessage());
        $message = renderAlert(SetErrorMessage($e2), 'danger');

    } catch (RuntimeException $e2) {
        $message = renderAlert($e2->getMessage(), 'danger');
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
<div class="container py-5" style="max-width:460px;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h4 class="mb-1">Forgot your password?</h4>
            <p class="text-muted">Enter your account email and we'll send you a link to reset it.</p>

            <?= $message ?>

            <?php if (!$sent): ?>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input id="email" name="email" type="email" class="form-control"
                               value="<?= $e($email) ?>" required autofocus>
                    </div>
                    <button class="btn btn-primary w-100">Send reset link</button>
                </form>
            <?php endif; ?>

            <div class="text-center mt-3">
                <a href="login_page.php">Back to login</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
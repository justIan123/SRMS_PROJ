<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$self   = COMPONENTS_URL . 'manage_myAccount.php';
$userId = (int)($_SESSION['user_id'] ?? 0);   // adjust if your login stores the id under another key

if (!$userId) {
    echo alertBox('warning', 'Please log in to manage your account.');
    return;
}

// CSRF token (created once per session, checked in accountProcess.php)
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Load the current user. The password column is never selected.
$stmt = $pdo->prepare(
    "SELECT u.id, u.username, u.email, u.fname, u.lname, u.phone, u.is_active,
            (SELECT string_agg(r.role, ', ')
             FROM user_roles ur
             JOIN roles r ON r.role_id = ur.role
             WHERE ur.user_id = u.id) AS roles
     FROM users u
     WHERE u.id = :id"
);
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo alertBox('danger', 'Account not found.');
    return;
}

// $old is set by accountProcess.php when a profile update fails, so the form keeps what was typed
$old = $old ?? [];
$e   = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$val = fn($k) => $e($old[$k] ?? $user[$k] ?? '');

//yeah i found this randomly online //2026/10/22
$initials = mb_strtoupper(mb_substr($user['fname'] ?? '', 0, 1) . mb_substr($user['lname'] ?? '', 0, 1));
if ($initials === '') {
    $initials = mb_strtoupper(mb_substr($user['username'], 0, 1));
}
$fullName = trim(($user['fname'] ?? '') . ' ' . ($user['lname'] ?? ''));
?>

<div hx-boost="true" hx-target="#main" hx-push-url="false" hx-indicator="#global-spinner">

    <h3>My Account</h3>
    <div id="message"><?= $message ?? '' ?></div>

    <div class="row g-4">

        <!-- SUMMARY CARD -->
        <div class="col-lg-4">
            <div class="card text-center">
                <div class="card-body">
                    <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3"
                         style="width:96px;height:96px;font-size:2rem;">
                        <?= $e($initials) ?>
                    </div>
                    <h5 class="mb-0"><?= $e($fullName ?: $user['username']) ?></h5>
                    <div class="text-muted mb-2">@<?= $e($user['username']) ?></div>

                    <?php foreach (array_filter(explode(', ', $user['roles'] ?? '')) as $role): ?>
                        <span class="badge text-bg-secondary"><?= $e(ucfirst($role)) ?></span>
                    <?php endforeach; ?>
                    <span class="badge <?= $user['is_active'] ? 'text-bg-success' : 'text-bg-danger' ?>">
                        <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>

                    <hr>
                    <div class="small text-muted text-start">
                        <div><strong>Email:</strong> <?= $e($user['email']) ?></div>
                        <div><strong>Phone:</strong> <?= $e($user['phone'] ?: '—') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">

            <!-- PROFILE -->
            <div class="card mb-4">
                <div class="card-header">Profile</div>
                <div class="card-body">
                    <form hx-post="<?= HANDLERS_URL ?>accountProcess.php">
                        <input type="hidden" name="action" value="profile">
                        <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="fname" class="form-label">First name</label>
                                <input id="fname" name="fname" class="form-control" value="<?= $val('fname') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="lname" class="form-label">Last name</label>
                                <input id="lname" name="lname" class="form-control" value="<?= $val('lname') ?>" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="username" class="form-label">Username</label>
                                <input id="username" name="username" class="form-control" value="<?= $val('username') ?>"
                                       pattern="[A-Za-z0-9._\-]{3,30}" title="3–30 letters, numbers, dot, dash or underscore" required>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone</label>
                                <input id="phone" name="phone" type="tel" class="form-control" value="<?= $val('phone') ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input id="email" name="email" type="email" class="form-control" value="<?= $val('email') ?>" required>
                        </div>

                        <button class="btn btn-primary">Save changes</button>
                    </form>
                </div>
            </div>

            <!-- PASSWORD -->
            <div class="card">
                <div class="card-header">Change password</div>
                <div class="card-body">
                    <form hx-post="<?= HANDLERS_URL ?>accountProcess.php" autocomplete="off">
                        <input type="hidden" name="action" value="password">
                        <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>">

                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current password</label>
                            <input id="current_password" name="current_password" type="password" class="form-control"
                                   autocomplete="current-password" required>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="new_password" class="form-label">New password</label>
                                <input id="new_password" name="new_password" type="password" class="form-control"
                                       minlength="8" autocomplete="new-password" required>
                                <div class="form-text">At least 8 characters.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Confirm new password</label>
                                <input id="confirm_password" name="confirm_password" type="password" class="form-control"
                                       minlength="8" autocomplete="new-password" required>
                            </div>
                        </div>

                        <button class="btn btn-outline-primary">Update password</button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
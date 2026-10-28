<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");

$guardiansUrl = COMPONENTS_URL . 'manage_guardians.php';
$teachersUrl  = COMPONENTS_URL . 'manage_teachers.php';
$usersUrl     = COMPONENTS_URL . 'manage_users.php';

try {
    // Everything belongs to the current school year
    $year = CurrentSchoolYear($pdo);
    if (!$year) {
        echo alertBox('warning', 'No school year is running. Start one in School Years first.');
        return;
    }

    // ---- numbers ----
    $totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $totalUsers    = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM guardians WHERE school_year_id = :year");
    $stmt->execute([':year' => $year['id']]);
    $totalGuardians = (int)$stmt->fetchColumn();

    // people by role
    $roles = $pdo->query("SELECT r.role AS name, COUNT(*) AS total
                          FROM user_roles ur
                          JOIN roles r ON r.role_id = ur.role
                          GROUP BY r.role
                          ORDER BY total DESC")->fetchAll();

    $totalTeachers = 0;
    foreach ($roles as $role) {
        if (strtolower($role['name']) === 'teacher') {
            $totalTeachers = $role['total'];
        }
    }

    // guardians that have no student yet
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM guardians g
                           WHERE g.school_year_id = :year
                             AND NOT EXISTS (SELECT 1 FROM students s WHERE s.guardian_id = g.id)");
    $stmt->execute([':year' => $year['id']]);
    $noStudent = (int)$stmt->fetchColumn();

    // newest 5 users (with their role)
    $newUsers = $pdo->query("SELECT u.id, u.fname, u.lname, u.email,
                                    (SELECT string_agg(r.role, ', ')
                                     FROM user_roles ur
                                     JOIN roles r ON r.role_id = ur.role
                                     WHERE ur.user_id = u.id) AS roles
                             FROM users u
                             ORDER BY u.id DESC
                             LIMIT 5")->fetchAll();

    // newest 5 guardians of this school year
    $stmt = $pdo->prepare("SELECT id, fname, lname, phone, email
                           FROM guardians
                           WHERE school_year_id = :year
                           ORDER BY id DESC
                           LIMIT 5");
    $stmt->execute([':year' => $year['id']]);
    $newGuardians = $stmt->fetchAll();
} catch (PDOException $e) {
    err_log($e->getMessage());
    echo alertBox('danger', 'Could not load the dashboard. Please try again.');
    return;
}

// the four number cards: label, value, color, link
$cards = [
    ['Students',  $totalStudents,  'primary', null],
    ['Guardians', $totalGuardians, 'warning', $guardiansUrl],
    ['Teachers',  $totalTeachers,  'success', $teachersUrl],
    ['All users', $totalUsers,     'info',    $usersUrl],
];
?>

<div hx-boost="true" hx-target="#main" hx-push-url="false" hx-indicator="#global-spinner">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Dashboard</h3>
        <span class="badge text-bg-light border fs-6 fw-normal"><?= htmlspecialchars($year['name']) ?></span>
    </div>

    <!-- NUMBERS -->
    <div class="row g-3 mb-4">
        <?php foreach ($cards as [$label, $value, $color, $url]): ?>
            <div class="col-6 col-lg-3">
                <div class="card h-100 shadow-sm border-0 border-start border-4 border-<?= $color ?>">
                    <div class="card-body">
                        <div class="text-muted small"><?= $label ?></div>
                        <div class="display-6 fw-semibold text-<?= $color ?>"><?= $value ?></div>
                        <?php if ($url): ?>
                            <a href="<?= $url ?>" class="small stretched-link text-decoration-none">View all</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-3 mb-4">

        <!-- ROLES -->
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title mb-3">People by role</h5>
                    <?php if (!$roles): ?>
                        <p class="text-muted mb-0">No roles assigned yet.</p>
                    <?php endif; ?>
                    <?php foreach ($roles as $role): ?>
                        <?php $percent = round($role['total'] / array_sum(array_column($roles, 'total')) * 100); ?>
                        <div class="d-flex justify-content-between small mb-1">
                            <span><?= htmlspecialchars(ucfirst($role['name'])) ?></span>
                            <span class="text-muted"><?= $role['total'] ?></span>
                        </div>
                        <div class="progress mb-3" style="height: 8px;">
                            <div class="progress-bar" style="width: <?= $percent ?>%"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- NEEDS ATTENTION -->
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title mb-3">Needs attention</h5>
                    <?php if ($noStudent > 0): ?>
                        <div class="alert alert-warning d-flex justify-content-between align-items-center mb-0">
                            <span><?= $noStudent ?> guardian<?= $noStudent == 1 ? '' : 's' ?> without a student</span>
                            <a href="<?= $guardiansUrl ?>" class="btn btn-sm btn-warning">Review</a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success mb-0">All caught up. Every guardian has a student.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">

        <!-- NEWEST USERS -->
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title">Newest users</h5>
                    <?php if (!$newUsers): ?>
                        <p class="text-muted mb-0">No users yet.</p>
                    <?php endif; ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($newUsers as $u): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <div class="fw-semibold"><?= htmlspecialchars($u['fname'] . ' ' . $u['lname']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($u['email'] ?? '') ?></div>
                                </div>
                                <?php if (!empty($u['roles'])): ?>
                                    <span class="badge text-bg-secondary"><?= htmlspecialchars(ucfirst($u['roles'])) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- NEWEST GUARDIANS -->
        <div class="col-lg-6">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title">Newest guardians</h5>
                    <?php if (!$newGuardians): ?>
                        <p class="text-muted mb-0">No guardians this school year yet.</p>
                    <?php endif; ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($newGuardians as $g): ?>
                            <li class="list-group-item px-0">
                                <div class="fw-semibold"><?= htmlspecialchars($g['fname'] . ' ' . $g['lname']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($g['phone'] ?: ($g['email'] ?? '')) ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div>
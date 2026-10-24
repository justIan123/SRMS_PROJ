<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php");
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");

$years = FindAll($pdo, 'school_years', 50, 0, 'start_date', 'DESC'); // newest first
?>

<div hx-target="#main" hx-indicator="#global-spinner">

    <h3>School Years</h3>
    <div id="message"><?= $message ?? '' ?></div>

    <!-- CREATE -->
    <form hx-post="<?= HANDLERS_URL ?>schoolYearProcess.php" class="mb-4 d-flex gap-2 align-items-end">
        <input type="hidden" name="action" value="create">
        <div>
            <label class="form-label">Name</label>
            <input name="name" class="form-control" placeholder="SY 2026-2027" required>
        </div>
        <div>
            <label class="form-label">Starts</label>
            <input type="date" name="start_date" class="form-control" required>
        </div>
        <div>
            <label class="form-label">Ends</label>
            <input type="date" name="end_date" class="form-control" required>
        </div>
        <button class="btn btn-primary">Create</button>
    </form>

    <!-- LIST -->
    <table class="table">
        <tr>
            <th>Name</th>
            <th>Starts</th>
            <th>Ends</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>

        <?php if (!$years): ?>
            <tr><td colspan="5" class="text-center text-muted">No school year yet. Create one first.</td></tr>
        <?php endif; ?>

        <?php foreach ($years as $y): ?>
            <tr>
                <td><?= htmlspecialchars($y['name']) ?></td>
                <td><?= $y['start_date'] ?></td>
                <td><?= $y['end_date'] ?></td>
                <td>
                    <?php if ($y['is_active']): ?>
                        <span class="badge bg-success">Current</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Not running</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($y['is_active']): ?>
                        <!-- END -->
                        <button class="btn btn-sm btn-outline-danger"
                                hx-post="<?= HANDLERS_URL ?>schoolYearProcess.php"
                                hx-vals='{"action": "end", "id": "<?= $y['id'] ?>"}'
                                hx-confirm="End this school year?">End</button>
                    <?php else: ?>
                        <!-- START -->
                        <button class="btn btn-sm btn-outline-success"
                                hx-post="<?= HANDLERS_URL ?>schoolYearProcess.php"
                                hx-vals='{"action": "start", "id": "<?= $y['id'] ?>"}'
                                hx-confirm="Start this school year? Any running school year will be ended.">Start</button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

</div>
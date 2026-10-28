<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "testFunctions.php");

$self     = COMPONENTS_URL . 'manage_questions.php';
$testsUrl = COMPONENTS_URL . 'manage_tests.php';

$testId = (int)($_GET['test'] ?? 0);
$editId = (int)($_GET['edit'] ?? 0);

try {
    // Everything belongs to the current school year
    $year = CurrentSchoolYear($pdo);
    if (!$year) {
        echo alertBox('warning', 'No school year is running. Start one in School Years first.');
        return;
    }

    // Only the teacher who made the test can open it
    $teacherId = CurrentUserId();
    if (!IsTeacher($pdo, $teacherId)) {
        echo alertBox('warning', 'Only teachers can create tests.');
        return;
    }
    $test = FindMyTest($pdo, $testId, $teacherId, $year['id']);
    if (!$test) {
        echo alertBox('warning', 'Test not found.');
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = :subject");
    $stmt->execute([':subject' => $test['subject_id']]);
    $subject = $stmt->fetch() ?: null;

    $stmt = $pdo->prepare("SELECT * FROM questions WHERE test_id = :test ORDER BY id");
    $stmt->execute([':test' => $testId]);
    $questions = $stmt->fetchAll();

    // choices grouped by question
    $choices = [];
    $stmt = $pdo->prepare("SELECT * FROM choices
                           WHERE question_id IN (SELECT id FROM questions WHERE test_id = :test)
                           ORDER BY id");
    $stmt->execute([':test' => $testId]);
    foreach ($stmt->fetchAll() as $c) {
        $choices[$c['question_id']][] = $c;
    }
} catch (PDOException $e) {
    err_log($e->getMessage());
    echo alertBox('danger', 'Could not load the questions. Please try again.');
    return;
}

$totalPoints = array_sum(array_column($questions, 'points'));
$locked      = (bool)$test['is_published'];   // a published test can't be changed

// what the question form shows: empty for a new question, filled in while editing
$form = ['text' => '', 'points' => 1, 'choices' => ['', '', '', ''], 'correct' => -1];
foreach ($questions as $q) {
    if ($q['id'] == $editId) {
        $form = ['text' => $q['question_text'], 'points' => $q['points'], 'choices' => ['', '', '', ''], 'correct' => -1];
        foreach (array_slice($choices[$q['id']] ?? [], 0, 4) as $i => $c) {
            $form['choices'][$i] = $c['choice_text'];
            if ($c['is_correct']) {
                $form['correct'] = $i;
            }
        }
    }
}
$isEditing = $editId && $form['text'] !== '';

$state = ['test' => $testId];
?>

<div hx-boost="true" hx-target="#main" hx-push-url="false" hx-indicator="#global-spinner">

    <a href="<?= makeUrl($testsUrl, []) ?>" class="btn btn-sm btn-outline-secondary mb-3">&larr; Back to my tests</a>

    <h3>
        <?= htmlspecialchars($test['title']) ?>
        <?php if ($locked): ?>
            <span class="badge text-bg-success fs-6">Published</span>
        <?php else: ?>
            <span class="badge text-bg-secondary fs-6">Draft</span>
        <?php endif; ?>
    </h3>
    <p class="text-muted">
        <?= htmlspecialchars($subject['subject_name'] ?? '') ?> &middot;
        <?= htmlspecialchars($year['name']) ?> &middot;
        <?= count($questions) ?> question<?= count($questions) == 1 ? '' : 's' ?> &middot;
        <?= $totalPoints ?> point<?= $totalPoints == 1 ? '' : 's' ?> &middot;
        <?= $test['time_limit'] ? (int)$test['time_limit'] . ' min' : 'No time limit' ?>
    </p>

    <div id="message"><?= $message ?? '' ?></div>

    <!-- QUESTION FORM (add, or edit when ?edit=ID) -->
    <?php if ($locked): ?>
        <div class="alert alert-info">This test is published. Unpublish it in My Tests to change its questions.</div>
    <?php else: ?>
        <form hx-post="<?= HANDLERS_URL ?>questionProcess.php" class="card card-body mb-4">
            <h5><?= $isEditing ? 'Edit question' : 'Add a question' ?></h5>
            <input type="hidden" name="action" value="<?= $isEditing ? 'update' : 'create' ?>">
            <input type="hidden" name="test" value="<?= $testId ?>">
            <?php if ($isEditing): ?>
                <input type="hidden" name="id" value="<?= $editId ?>">
            <?php endif; ?>

            <div class="row g-2 mb-3">
                <div class="col-md-10">
                    <textarea name="question_text" class="form-control" rows="2" placeholder="Question" required><?= htmlspecialchars($form['text']) ?></textarea>
                </div>
                <div class="col-md-2">
                    <input name="points" type="number" min="1" max="100" class="form-control" value="<?= (int)$form['points'] ?>" title="Points">
                </div>
            </div>

            <p class="small text-muted mb-1">Fill in at least two choices and select the correct one.</p>
            <?php foreach ($form['choices'] as $i => $choiceText): ?>
                <div class="input-group mb-2">
                    <div class="input-group-text">
                        <input class="form-check-input mt-0" type="radio" name="correct" value="<?= $i ?>" <?= $form['correct'] === $i ? 'checked' : '' ?> required>
                    </div>
                    <input name="choice[<?= $i ?>]" class="form-control" placeholder="Choice <?= chr(65 + $i) ?>" value="<?= htmlspecialchars($choiceText) ?>" <?= $i < 2 ? 'required' : '' ?>>
                </div>
            <?php endforeach; ?>

            <div>
                <button class="btn btn-primary"><?= $isEditing ? 'Save question' : 'Add question' ?></button>
                <?php if ($isEditing): ?>
                    <a href="<?= makeUrl($self, $state) ?>" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>

    <!-- QUESTIONS -->
    <?php if (!$questions): ?>
        <p class="text-center text-muted">No questions yet.</p>
    <?php endif; ?>

    <?php foreach ($questions as $n => $q): ?>
        <div class="card mb-3 <?= $q['id'] == $editId ? 'border-primary' : '' ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <h6 class="mb-2"><?= $n + 1 ?>. <?= nl2br(htmlspecialchars($q['question_text'])) ?></h6>
                    <span class="badge text-bg-light border"><?= (int)$q['points'] ?> pt<?= $q['points'] == 1 ? '' : 's' ?></span>
                </div>

                <ul class="list-group mb-2">
                    <?php foreach ($choices[$q['id']] ?? [] as $i => $c): ?>
                        <li class="list-group-item d-flex justify-content-between <?= $c['is_correct'] ? 'list-group-item-success' : '' ?>">
                            <span><?= chr(65 + $i) ?>. <?= htmlspecialchars($c['choice_text']) ?></span>
                            <?php if ($c['is_correct']): ?><span class="badge text-bg-success">Correct</span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if (!$locked): ?>
                    <a href="<?= makeUrl($self, $state, ['edit' => $q['id']]) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                    <button class="btn btn-sm btn-outline-danger"
                            hx-post="<?= HANDLERS_URL ?>questionProcess.php"
                            hx-vals='<?= htmlspecialchars(json_encode(['action' => 'delete', 'test' => $testId, 'id' => $q['id']]), ENT_QUOTES) ?>'
                            hx-confirm="Delete this question?">Delete</button>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

</div>
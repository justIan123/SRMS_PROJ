<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "testFunctions.php");

$testsUrl = COMPONENTS_URL . 'manage_tests.php';

$testId = (int)($_GET['test'] ?? 0);

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

// What each Edit button carries: the question as the form needs it (text, points, 4 choices, which one is correct).
// The browser fills the form from this when Edit is pressed, so no request is sent.
$editData = [];
foreach ($questions as $q) {
    $data = [
        'id'      => $q['id'],
        'text'    => $q['question_text'],
        'points'  => (int)$q['points'],
        'choices' => ['', '', '', ''],
        'correct' => -1,
    ];
    foreach (array_slice($choices[$q['id']] ?? [], 0, 4) as $i => $c) {
        $data['choices'][$i] = $c['choice_text'];
        if ($c['is_correct']) {
            $data['correct'] = $i;
        }
    }
    $editData[$q['id']] = $data;
}
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

    <!-- QUESTION FORM (adds a question; the Edit buttons below turn it into an edit form) -->
    <?php if ($locked): ?>
        <div class="alert alert-info">This test is published. Unpublish it in My Tests to change its questions.</div>
    <?php else: ?>
        <form id="questionForm" hx-post="<?= HANDLERS_URL ?>questionsProcess.php" class="card card-body mb-4">
            <h5 id="questionFormTitle">Add a question</h5>
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="test" value="<?= $testId ?>">
            <!-- disabled = not sent. It is switched on only while editing -->
            <input type="hidden" name="id" value="" disabled>

            <div class="row g-2 mb-3">
                <div class="col-md-10">
                    <textarea name="question_text" class="form-control" rows="2" placeholder="Question" required></textarea>
                </div>
                <div class="col-md-2">
                    <input name="points" type="number" min="1" max="100" class="form-control" value="1" title="Points">
                </div>
            </div>

            <p class="small text-muted mb-1">Fill in at least two choices and select the correct one.</p>
            <?php for ($i = 0; $i < 4; $i++): ?>
                <div class="input-group mb-2">
                    <div class="input-group-text">
                        <input class="form-check-input mt-0" type="radio" name="correct" value="<?= $i ?>" required>
                    </div>
                    <input name="choice[<?= $i ?>]" class="form-control" placeholder="Choice <?= chr(65 + $i) ?>" <?= $i < 2 ? 'required' : '' ?>>
                </div>
            <?php endfor; ?>

            <div>
                <button id="questionSubmit" class="btn btn-primary">Add question</button>
                <!-- Cancel only resets the form in the browser, no request is sent -->
                <button type="button" id="questionCancel" class="btn btn-secondary d-none" onclick="resetQuestionForm()">Cancel</button>
            </div>
        </form>
    <?php endif; ?>

    <!-- QUESTIONS -->
    <?php if (!$questions): ?>
        <p class="text-center text-muted">No questions yet.</p>
    <?php endif; ?>

    <?php foreach ($questions as $n => $q): ?>
        <div class="card mb-3 question-card">
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
                    <!-- Edit fills the form above from data-question, no request is sent -->
                    <button type="button" class="btn btn-sm btn-outline-primary"
                            onclick="editQuestion(this)"
                            data-question='<?= htmlspecialchars(json_encode($editData[$q['id']]), ENT_QUOTES) ?>'>Edit</button>
                    <button class="btn btn-sm btn-outline-danger"
                            hx-post="<?= HANDLERS_URL ?>questionsProcess.php"
                            hx-vals='<?= htmlspecialchars(json_encode(['action' => 'delete', 'test' => $testId, 'id' => $q['id']]), ENT_QUOTES) ?>'
                            hx-confirm="Delete this question?">Delete</button>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <script>
        // Edit: copy the question into the form at the top and switch it to "update" mode.
        function editQuestion(button) {
            const q    = JSON.parse(button.dataset.question);
            const form = document.getElementById('questionForm');

            form.querySelector('[name="action"]').value = 'update';

            const idInput = form.querySelector('[name="id"]');
            idInput.value    = q.id;
            idInput.disabled = false;               // now it is sent with the form

            form.querySelector('[name="question_text"]').value = q.text;
            form.querySelector('[name="points"]').value = q.points;

            for (let i = 0; i < 4; i++) {
                form.querySelector('[name="choice[' + i + ']"]').value = q.choices[i];
            }
            form.querySelectorAll('[name="correct"]').forEach(function (radio, i) {
                radio.checked = (i === q.correct);
            });

            document.getElementById('questionFormTitle').textContent = 'Edit question';
            document.getElementById('questionSubmit').textContent    = 'Save question';
            document.getElementById('questionCancel').classList.remove('d-none');

            // highlight the question being edited
            document.querySelectorAll('.question-card').forEach(function (card) {
                card.classList.remove('border-primary');
            });
            button.closest('.question-card').classList.add('border-primary');

            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            form.querySelector('[name="question_text"]').focus({ preventScroll: true });
        }

        // Cancel: back to an empty "add a question" form.
        function resetQuestionForm() {
            const form = document.getElementById('questionForm');

            form.reset();                           // empty text and choices, points back to 1, no radio chosen
            form.querySelector('[name="action"]').value = 'create';

            const idInput = form.querySelector('[name="id"]');
            idInput.value    = '';
            idInput.disabled = true;                // not sent while adding

            document.getElementById('questionFormTitle').textContent = 'Add a question';
            document.getElementById('questionSubmit').textContent    = 'Add question';
            document.getElementById('questionCancel').classList.add('d-none');

            document.querySelectorAll('.question-card').forEach(function (card) {
                card.classList.remove('border-primary');
            });
        }
    </script>

</div>
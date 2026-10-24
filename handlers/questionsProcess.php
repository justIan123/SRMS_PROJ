<?php
require_once("../public/initialize.php");
require_once(CONFIG_PATH . DS . "db_sqlite.php");
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php");
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "testFunctions.php");

$year      = CurrentSchoolYear($pdo);
$teacherId = CurrentUserId();
$action    = $_POST['action'] ?? '';
$testId    = (int)($_POST['test'] ?? 0);
$message   = '';
$editId    = 0;

// the test must be this teacher's own, from this school year
$test = ($year && IsTeacher($pdo, $teacherId)) ? FindMyTest($pdo, $testId, $teacherId, $year['id']) : null;

if (!$test) {
    $message = alertBox('danger', 'Test not found.');
} elseif ($test['is_published']) {
    $message = alertBox('danger', 'Unpublish the test before changing its questions.');
} else {

    // for update and delete: the question must belong to this test
    $question = null;
    if ($action === 'update' || $action === 'delete') {
        $question = FindById($pdo, 'questions', (int)($_POST['id'] ?? 0));
        if ($question && (int)$question['test_id'] !== $testId) {
            $question = null;
        }
    }

    if ($action === 'create' || $action === 'update') {
        [$text, $points, $choices, $correct, $error] = ReadQuestionForm($_POST);

        if ($error) {
            $message = alertBox('danger', $error);
            $editId  = $action === 'update' ? (int)($_POST['id'] ?? 0) : 0;   // stay in edit mode
        } elseif ($action === 'update' && !$question) {
            $message = alertBox('danger', 'Question not found.');
        } else {
            SaveQuestion($pdo, $testId, $action === 'update' ? (int)$question['id'] : null, $text, $points, $choices, $correct);
            $message = alertBox('success', $action === 'update' ? 'Question updated.' : 'Question added.');
        }
    }

    if ($action === 'delete') {
        if ($question) {
            DeleteQuestion($pdo, (int)$question['id']);
            $message = alertBox('success', 'Question deleted.');
        } else {
            $message = alertBox('danger', 'Question not found.');
        }
    }
}

// Hand the page its state back (same keys as the page reads in manage_questions.php)
$_GET = [
    'test' => $testId,
    'edit' => $editId,
];

require_once(COMPONENTS_PATH . DS . "manage_questions.php");
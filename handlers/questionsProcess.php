<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "testFunctions.php");
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");

$teacherId = CurrentUserId();
$action    = $_POST['action'] ?? '';
$testId    = (int)($_POST['test'] ?? 0);
$message   = '';
$editId    = 0;

try {
    $year = CurrentSchoolYear($pdo);

    // the test must be this teacher's own, from this school year
    $test = ($year && IsTeacher($pdo, $teacherId)) ? FindMyTest($pdo, $testId, $teacherId, $year['id']) : null;

    if (!$test) {
        $message = alertBox('danger', 'Test not found.');
    } elseif ($test['is_published']) {
        $message = alertBox('danger', 'Unpublish the test before changing its questions.');
    } else {
        $pdo->beginTransaction();

        // for update and delete: the question must belong to this test
        $question = null;
        if ($action === 'update' || $action === 'delete') {
            $stmt = $pdo->prepare("SELECT * FROM questions WHERE id = :id AND test_id = :test");
            $stmt->execute([':id' => (int)($_POST['id'] ?? 0), ':test' => $testId]);
            $question = $stmt->fetch() ?: null;
        }

        if ($action === 'create' || $action === 'update') {
            [$text, $points, $choices, $correct, $error] = ReadQuestionForm($_POST);

            if ($error) {
                $message = alertBox('danger', $error);
                $editId  = $action === 'update' ? (int)($_POST['id'] ?? 0) : 0;   // stay in edit mode
            } elseif ($action === 'update' && !$question) {
                $message = alertBox('danger', 'Question not found.');
            } else {
                if ($action === 'create') {
                    // Postgres: lastInsertId() needs a sequence name, so ask the INSERT to return the id instead
                    $stmt = $pdo->prepare("INSERT INTO questions (test_id, question_text, points)
                                           VALUES (:test, :text, :points)
                                           RETURNING id");
                    $stmt->execute([':test' => $testId, ':text' => $text, ':points' => $points]);
                    $questionId = (int)$stmt->fetchColumn();
                } else {
                    $questionId = (int)$question['id'];

                    $stmt = $pdo->prepare("UPDATE questions SET question_text = :text, points = :points WHERE id = :id");
                    $stmt->execute([':text' => $text, ':points' => $points, ':id' => $questionId]);

                    // the choices are re-added below
                    $stmt = $pdo->prepare("DELETE FROM choices WHERE question_id = :question");
                    $stmt->execute([':question' => $questionId]);
                }

                $stmt = $pdo->prepare("INSERT INTO choices (question_id, choice_text, is_correct)
                                       VALUES (:question, :choice, :correct)");
                foreach ($choices as $i => $choice) {
                    $stmt->execute([
                        ':question' => $questionId,
                        ':choice'   => $choice,
                        ':correct'  => $i === $correct ? 1 : 0,
                    ]);
                }

                $message = alertBox('success', $action === 'update' ? 'Question updated.' : 'Question added.');
            }
        }

        if ($action === 'delete') {
            if ($question) {
                $stmt = $pdo->prepare("DELETE FROM choices WHERE question_id = :question");
                $stmt->execute([':question' => $question['id']]);

                $stmt = $pdo->prepare("DELETE FROM questions WHERE id = :question");
                $stmt->execute([':question' => $question['id']]);

                $message = alertBox('success', 'Question deleted.');
            } else {
                $message = alertBox('danger', 'Question not found.');
            }
        }

        $pdo->commit();
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    err_log($e->getMessage());
    $message = alertBox('danger', 'Could not save. Please check the data and try again.');
}

// Hand the page its state back (same keys as the page reads in manage_questions.php)
$_GET = [
    'test' => $testId,
    'edit' => $editId,
];

require_once(COMPONENTS_PATH . DS . "manage_questions.php");

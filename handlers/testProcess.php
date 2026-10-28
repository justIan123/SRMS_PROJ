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
$message   = '';

try {
    $year = CurrentSchoolYear($pdo);

    if (!$year || !IsTeacher($pdo, $teacherId)) {
        $message = alertBox('danger', 'Only teachers can manage tests.');
    } else {
        $pdo->beginTransaction();

        // the subjects this teacher is allowed to use this year
        $mySubjects = array_map('intval', array_column(TeacherSubjects($pdo, $teacherId, $year['id']), 'id'));

        $title     = trim($_POST['title'] ?? '');
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $timeLimit = (int)($_POST['time_limit'] ?? 0);
        $timeLimit = ($timeLimit >= 1 && $timeLimit <= 600) ? $timeLimit : null;

        // for update, toggle and delete: the test must be this teacher's own
        $test = null;
        if (in_array($action, ['update', 'toggle', 'delete'], true)) {
            $test = FindMyTest($pdo, (int)($_POST['id'] ?? 0), $teacherId, $year['id']);
        }

        if ($action === 'create') {
            if ($title === '') {
                $message = alertBox('danger', 'Give the test a title.');
            } elseif (!in_array($subjectId, $mySubjects, true)) {
                $message = alertBox('danger', 'Pick one of your own subjects.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO tests (school_year_id, subject_id, teacher_id, title, time_limit)
                                       VALUES (:year, :subject, :teacher, :title, :time_limit)");
                $stmt->execute([
                    ':year'       => $year['id'],
                    ':subject'    => $subjectId,
                    ':teacher'    => $teacherId,
                    ':title'      => $title,
                    ':time_limit' => $timeLimit,
                ]);
                $message = alertBox('success', 'Test created. Click Questions to add questions.');
            }

        } elseif (!$test) {
            $message = alertBox('danger', 'Test not found.');

        } elseif ($action === 'update') {
            // keeping the old subject is always fine, a new one must be one of mine
            if ($title === '') {
                $message = alertBox('danger', 'Give the test a title.');
            } elseif ($subjectId !== (int)$test['subject_id'] && !in_array($subjectId, $mySubjects, true)) {
                $message = alertBox('danger', 'Pick one of your own subjects.');
            } else {
                $stmt = $pdo->prepare("UPDATE tests
                                       SET title = :title, subject_id = :subject, time_limit = :time_limit
                                       WHERE id = :id");
                $stmt->execute([
                    ':title'      => $title,
                    ':subject'    => $subjectId,
                    ':time_limit' => $timeLimit,
                    ':id'         => $test['id'],
                ]);
                $message = alertBox('success', 'Test updated.');
            }

        } elseif ($action === 'toggle') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE test_id = :test");
            $stmt->execute([':test' => $test['id']]);
            $questionCount = (int)$stmt->fetchColumn();

            if (!$test['is_published'] && $questionCount === 0) {
                $message = alertBox('danger', 'Add at least one question before publishing.');
            } else {
                $stmt = $pdo->prepare("UPDATE tests SET is_published = :published WHERE id = :id");
                $stmt->execute([':published' => $test['is_published'] ? 0 : 1, ':id' => $test['id']]);
                $message = alertBox('success', $test['is_published'] ? 'Test unpublished.' : 'Test published.');
            }

        } elseif ($action === 'delete') {
            // the test, its questions and their choices go together
            $stmt = $pdo->prepare("DELETE FROM choices
                                   WHERE question_id IN (SELECT id FROM questions WHERE test_id = :test)");
            $stmt->execute([':test' => $test['id']]);

            $stmt = $pdo->prepare("DELETE FROM questions WHERE test_id = :test");
            $stmt->execute([':test' => $test['id']]);

            $stmt = $pdo->prepare("DELETE FROM tests WHERE id = :test");
            $stmt->execute([':test' => $test['id']]);

            $message = alertBox('success', 'Test deleted.');
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

// Hand the page its state back (same keys as $state in manage_tests.php)
$_GET = [
    'sort'    => $_POST['sort']    ?? '',
    'dir'     => $_POST['dir']     ?? '',
    'search'  => $_POST['search']  ?? '',
    'subject' => $_POST['subject'] ?? 0,
    'page'    => $_POST['page']    ?? 1,
];

require_once(COMPONENTS_PATH . DS . "manage_tests.php");

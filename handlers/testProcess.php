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
$message   = '';

if (!$year || !IsTeacher($pdo, $teacherId)) {
    $message = alertBox('danger', 'Only teachers can manage tests.');
} else {

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
            InsertRecord($pdo, 'tests', [
                'school_year_id' => $year['id'],
                'subject_id'     => $subjectId,
                'teacher_id'     => $teacherId,
                'title'          => $title,
                'time_limit'     => $timeLimit,
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
            UpdateRecord($pdo, 'tests', $test['id'], [
                'title'      => $title,
                'subject_id' => $subjectId,
                'time_limit' => $timeLimit,
            ]);
            $message = alertBox('success', 'Test updated.');
        }
    } elseif ($action === 'toggle') {
        if (!$test['is_published'] && CountRecords($pdo, 'questions', ['test_id' => $test['id']]) === 0) {
            $message = alertBox('danger', 'Add at least one question before publishing.');
        } else {
            UpdateRecord($pdo, 'tests', $test['id'], ['is_published' => $test['is_published'] ? 0 : 1]);
            $message = alertBox('success', $test['is_published'] ? 'Test unpublished.' : 'Test published.');
        }
    } elseif ($action === 'delete') {
        DeleteTest($pdo, (int)$test['id']);
        $message = alertBox('success', 'Test deleted.');
    }
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
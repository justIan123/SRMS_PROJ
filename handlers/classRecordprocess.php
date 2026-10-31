<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");
require_once(FUNCTIONS_PATH . DS . "gradeFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action     = $_POST['action'] ?? '';
$message    = '';
$formValues = null;                              // what was typed, handed back to the page if the save fails
$subjectId  = (int)($_POST['subject_id'] ?? 0);
$myId       = (int)($_SESSION['user_id'] ?? 0);  // the logged-in user (saved as updated_by)

try {
    $pdo->beginTransaction();

    if ($action === 'save') {
        $error = null;
        $typed = null;

        // the subject must be one this user may grade in the current school year
        $year = CurrentSchoolYear($pdo);
        if (!$year) {
            $error = 'No school year is running.';
        } else {
            $gradable = GradableSubjects($pdo, (int)$year['id'], $myId);
            if (!isset($gradable[$subjectId])) {
                $error = 'Subject not found.';
            }
        }

        // The boxes that can be saved: the students enrolled in this subject.
        // Any other student id in the request is ignored.
        $items = [];
        if (!$error) {
            $stmt = $pdo->prepare("SELECT s.id, s.fname, s.lname
                                   FROM students s
                                   JOIN enrollments e ON e.student_id = s.id
                                   WHERE e.subject_id = :sub AND s.school_year_id = :year");
            $stmt->execute([':sub' => $subjectId, ':year' => $year['id']]);

            $posted = is_array($_POST['grades'] ?? null) ? $_POST['grades'] : [];

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $studentId = (int)$s['id'];
                // a student that was not on the form is left alone (never treated as "cleared")
                if (!is_array($posted[$studentId] ?? null)) {
                    continue;
                }
                $items[$studentId] = [
                    'student_id' => $studentId,
                    'subject_id' => $subjectId,
                    'label'      => $s['lname'] . ', ' . $s['fname'],
                    'row'        => $posted[$studentId],
                ];
            }

            if (!$items) {
                $error = 'There are no students to save.';
            }
        }

        if (!$error) {
            $result = SaveGradeItems($pdo, $items, $myId);   // checks everything, then writes
            $error  = $result['error'];
            $typed  = $result['typed'];
        }

        if ($error) {
            $message    = renderAlert($error, 'danger');
            $formValues = $typed;
        } else {
            $message = renderAlert('Grades saved.', 'success');
        }
    }

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    err_log($e->getMessage());
    $message    = renderAlert(SetErrorMessage($e), 'danger');
    $formValues = null;
}

// Hand the page its state back (same keys as the GET of manage_classrecord.php)
$_GET = [
    'subject_id' => $subjectId,
];

require_once(COMPONENTS_PATH . DS . "manage_classrecord.php");
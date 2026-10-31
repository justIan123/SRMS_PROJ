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
$studentId  = (int)($_POST['student_id'] ?? 0);
$search     = is_string($_POST['search'] ?? null) ? trim($_POST['search']) : '';   // the search the page was opened with
$goNext     = !empty($_POST['next']);            // the "Save & next student" button
$myId       = (int)($_SESSION['user_id'] ?? 0);  // the logged-in user (saved as updated_by)

try {
    $pdo->beginTransaction();

    if ($action === 'save') {
        $error = null;
        $typed = null;

        // the student must be in the current school year
        $year = CurrentSchoolYear($pdo);
        if (!$year) {
            $error = 'No school year is running.';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM students WHERE id = :id AND school_year_id = :year");
            $stmt->execute([':id' => $studentId, ':year' => $year['id']]);
            if (!$stmt->fetchColumn()) {
                $error = 'Student not found.';
            }
        }

        // The boxes that can be saved: subjects this user grades AND the student is enrolled in.
        // Any other subject id in the request is ignored.
        $items = [];
        if (!$error) {
            $gradable = GradableSubjects($pdo, (int)$year['id'], $myId);

            $stmt = $pdo->prepare("SELECT subject_id FROM enrollments WHERE student_id = :sid");
            $stmt->execute([':sid' => $studentId]);
            $enrolled = array_flip(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));

            $posted = is_array($_POST['grades'] ?? null) ? $_POST['grades'] : [];

            foreach ($gradable as $subjectId => $subjectName) {
                // a subject that was not on the form is left alone (it is never treated as "cleared")
                if (!isset($enrolled[$subjectId]) || !is_array($posted[$subjectId] ?? null)) {
                    continue;
                }
                $items[$subjectId] = [
                    'student_id' => $studentId,
                    'subject_id' => $subjectId,
                    'label'      => $subjectName,
                    'row'        => $posted[$subjectId],
                ];
            }

            if (!$items) {
                $error = 'There are no subjects you can grade for this student.';
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

            // Save & next: open the next student of the same list the dropdown shows (search included)
            if ($goNext) {
                $ids = array_map(fn($s) => (int)$s['id'], GradableStudents($pdo, (int)$year['id'], array_keys($gradable), $search));
                $pos = array_search($studentId, $ids, true);

                if ($pos !== false && isset($ids[$pos + 1])) {
                    $studentId = $ids[$pos + 1];
                } elseif ($pos !== false) {
                    $message = renderAlert('Grades saved. That was the last student on the list.', 'success');
                }
            }
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

// Hand the page its state back (same keys as the GET of manage_grades.php)
$_GET = [
    'student_id' => $studentId,
    'search'     => $search,
];

require_once(COMPONENTS_PATH . DS . "manage_grades.php");
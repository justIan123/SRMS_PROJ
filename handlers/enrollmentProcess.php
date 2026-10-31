<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action     = $_POST['action'] ?? '';
$message    = '';
$by         = ($_POST['by'] ?? '') === 'student' ? 'student' : 'subject';
$subjectId  = (int)($_POST['subject_id'] ?? 0);
$studentId  = (int)($_POST['student_id'] ?? 0);
$gradeLevel = trim($_POST['grade_level'] ?? '');

// a list of ids from the form (enrolled[] / shown[]), integers only
$idList = function (string $key): array {
    $v = $_POST[$key] ?? [];
    return is_array($v) ? array_values(array_unique(array_map('intval', array_filter($v, 'is_scalar')))) : [];
};

try {
    $pdo->beginTransaction();

    if ($action === 'save') {
        $error = null;

        $year = CurrentSchoolYear($pdo);
        if (!$year) {
            $error = 'No school year is running.';
        }

        // By subject: the subject stays fixed and the students vary. By student: the other way round.
        // These column names are fixed here, never taken from the request.
        $fixedCol = $by === 'subject' ? 'subject_id' : 'student_id';
        $varCol   = $by === 'subject' ? 'student_id' : 'subject_id';
        $fixedId  = $by === 'subject' ? $subjectId : $studentId;

        // $names: id => name of everything that may be ticked here (this school year only)
        $names = [];
        if (!$error) {
            if ($by === 'subject') {
                $stmt = $pdo->prepare("SELECT 1 FROM subjects WHERE id = :id AND school_year_id = :year AND is_active = 1");
                $stmt->execute([':id' => $fixedId, ':year' => $year['id']]);
                if (!$stmt->fetchColumn()) {
                    $error = 'Subject not found.';
                } else {
                    $stmt = $pdo->prepare("SELECT id, lname || ', ' || fname AS name FROM students WHERE school_year_id = :year");
                    $stmt->execute([':year' => $year['id']]);
                    $names = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                }
            } else {
                $stmt = $pdo->prepare("SELECT 1 FROM students WHERE id = :id AND school_year_id = :year");
                $stmt->execute([':id' => $fixedId, ':year' => $year['id']]);
                if (!$stmt->fetchColumn()) {
                    $error = 'Student not found.';
                } else {
                    $stmt = $pdo->prepare("SELECT id, subject_name FROM subjects WHERE school_year_id = :year AND is_active = 1");
                    $stmt->execute([':year' => $year['id']]);
                    $names = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                }
            }
        }

        if ($error) {
            $message = renderAlert($error, 'danger');
        } else {
            // only ids that exist in this school year count; a ticked id must also have been on the screen
            $shown    = array_values(array_intersect($idList('shown'), array_keys($names)));
            $wanted   = array_values(array_intersect($idList('enrolled'), $shown));

            $stmt = $pdo->prepare("SELECT $varCol FROM enrollments WHERE $fixedCol = :fixed");
            $stmt->execute([':fixed' => $fixedId]);
            $existing = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

            $toAdd    = array_diff($wanted, $existing);
            $toRemove = array_diff(array_intersect($shown, $existing), $wanted);   // only what was on the screen

            $pair = fn(int $varId): array => $by === 'subject'
                ? [':student_id' => $varId,   ':subject_id' => $fixedId]
                : [':student_id' => $fixedId, ':subject_id' => $varId];

            $insert    = $pdo->prepare("INSERT INTO enrollments (student_id, subject_id) VALUES (:student_id, :subject_id)
                                        ON CONFLICT (student_id, subject_id) DO NOTHING");
            $hasGrades = $pdo->prepare("SELECT 1 FROM grades WHERE student_id = :student_id AND subject_id = :subject_id");
            $delete    = $pdo->prepare("DELETE FROM enrollments WHERE student_id = :student_id AND subject_id = :subject_id");

            $added = 0;
            foreach ($toAdd as $varId) {
                $insert->execute($pair($varId));
                $added++;
            }

            // an enrollment that already has grades is not removed
            $removed = 0;
            $kept    = [];
            foreach ($toRemove as $varId) {
                $hasGrades->execute($pair($varId));
                if ($hasGrades->fetchColumn()) {
                    $kept[] = $names[$varId];
                } else {
                    $delete->execute($pair($varId));
                    $removed++;
                }
            }

            $parts = [];
            if ($added)   { $parts[] = $added . ' enrolled'; }
            if ($removed) { $parts[] = $removed . ' removed'; }
            $message = renderAlert($parts ? 'Enrollment saved: ' . implode(', ', $parts) . '.' : 'Nothing changed.', 'success');

            if ($kept) {
                $message .= renderAlert(
                    'Not removed, because they already have grades: ' . htmlspecialchars(implode('; ', $kept))
                    . '. Empty their scores in Grades first, then remove them.',
                    'warning'
                );
            }
        }
    }

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    err_log($e->getMessage());
    $message = renderAlert(SetErrorMessage($e), 'danger');
}

// Hand the page its state back (same keys as the GET of manage_enrollments.php)
$_GET = [
    'by'          => $by,
    'subject_id'  => $subjectId,
    'student_id'  => $studentId,
    'grade_level' => $gradeLevel,
];

require_once(COMPONENTS_PATH . DS . "manage_enrollments.php");
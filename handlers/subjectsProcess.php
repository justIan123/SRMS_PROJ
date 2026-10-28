<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "testFunctions.php");   // IsTeacher()
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");

$action  = $_POST['action'] ?? '';
$message = '';

try {
    // Everything belongs to the current school year
    $year = CurrentSchoolYear($pdo);

    if ($year) {
        $pdo->beginTransaction();

        $id          = (int)($_POST['id'] ?? 0);
        $name        = trim($_POST['subject_name'] ?? '');
        $description = trim($_POST['subject_description'] ?? '');
        $isActive    = (int)($_POST['is_active'] ?? 0) ? 1 : 0;   // 1 / 0, never a PHP bool
        $teacherId   = (int)($_POST['teacher_id'] ?? 0);

        if ($action === 'create') {
            if ($name === '') {
                $message = renderAlert('Subject name is required', 'danger');
            } else {
                $stmt = $pdo->prepare("INSERT INTO subjects (school_year_id, subject_name, subject_description)
                                       VALUES (:year, :name, :description)");
                $stmt->execute([
                    ':year'        => $year['id'],
                    ':name'        => $name,
                    ':description' => $description,
                ]);
                $message = renderAlert('Subject added', 'success');
            }

        } elseif ($action === 'update') {
            if ($name === '') {
                $message = renderAlert('Subject name is required', 'danger');
            } else {
                $stmt = $pdo->prepare("UPDATE subjects
                                       SET subject_name = :name, subject_description = :description, is_active = :active
                                       WHERE id = :id AND school_year_id = :year");
                $stmt->execute([
                    ':name'        => $name,
                    ':description' => $description,
                    ':active'      => $isActive,
                    ':id'          => $id,
                    ':year'        => $year['id'],
                ]);
                $_POST['edit'] = 0;   // close the edit row
                $message = $stmt->rowCount()
                    ? renderAlert('Subject updated', 'success')
                    : renderAlert('Subject not found', 'danger');
            }

        } elseif ($action === 'toggle') {
            // Activate / Deactivate
            $stmt = $pdo->prepare("UPDATE subjects SET is_active = :active
                                   WHERE id = :id AND school_year_id = :year");
            $stmt->execute([':active' => $isActive, ':id' => $id, ':year' => $year['id']]);
            $message = $stmt->rowCount()
                ? renderAlert('Subject status changed', 'success')
                : renderAlert('Subject not found', 'danger');

        } elseif ($action === 'assign') {
            // Give a subject to a teacher: the subject must be from this school year
            $stmt = $pdo->prepare("SELECT 1 FROM subjects WHERE id = :id AND school_year_id = :year");
            $stmt->execute([':id' => $id, ':year' => $year['id']]);

            if (!$stmt->fetchColumn()) {
                $message = renderAlert('Subject not found', 'danger');
            } elseif (!IsTeacher($pdo, $teacherId)) {
                $message = renderAlert('Pick a teacher', 'danger');
            } else {
                $stmt = $pdo->prepare("SELECT 1 FROM teacher_subjects WHERE subject_id = :subject AND teacher_id = :teacher");
                $stmt->execute([':subject' => $id, ':teacher' => $teacherId]);

                if ($stmt->fetchColumn()) {
                    $message = renderAlert('This teacher already has this subject', 'warning');
                } else {
                    $stmt = $pdo->prepare("INSERT INTO teacher_subjects (subject_id, teacher_id)
                                           VALUES (:subject, :teacher)");
                    $stmt->execute([':subject' => $id, ':teacher' => $teacherId]);
                    $message = renderAlert('Teacher assigned', 'success');
                }
            }

        } elseif ($action === 'unassign') {
            // Take a subject away from a teacher (only for subjects of this school year)
            $stmt = $pdo->prepare("DELETE FROM teacher_subjects
                                   WHERE subject_id = :subject AND teacher_id = :teacher
                                     AND subject_id IN (SELECT id FROM subjects WHERE school_year_id = :year)");
            $stmt->execute([':subject' => $id, ':teacher' => $teacherId, ':year' => $year['id']]);
            $message = renderAlert('Teacher removed from subject', 'success');
        }

        $pdo->commit();
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    err_log($e->getMessage());
    // usually a UNIQUE that failed (same subject name, or teacher already has this subject)
    $message = renderAlert('Could not save. That subject or assignment already exists.', 'danger');
}

// keep the same sort, search, page, edit and assign
$_GET = [
    'sort'         => $_POST['sort']         ?? '',
    'dir'          => $_POST['dir']          ?? '',
    'PageNum'      => $_POST['PageNum']      ?? 1,
    'search'       => $_POST['search']       ?? '',
    'PerPageLimit' => $_POST['PerPageLimit'] ?? 10,
    'edit'         => $_POST['edit']         ?? 0,
    'assign'       => $_POST['assign']       ?? 0,
];

require_once(COMPONENTS_PATH . DS . "manage_subjects.php");

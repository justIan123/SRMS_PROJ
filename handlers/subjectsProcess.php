<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");

$year    = CurrentSchoolYear($pdo);
$action  = $_POST['action'] ?? '';
$message = '';

if ($year) {
    try {

        if ($action === 'create') {
            $name = trim($_POST['subject_name'] ?? '');

            if ($name === '') {
                $message = renderAlert('Subject name is required', 'danger');
            } else {
                InsertRecord($pdo, 'subjects', [
                    'school_year_id'      => $year['id'],
                    'subject_name'        => $name,
                    'subject_description' => trim($_POST['subject_description'] ?? ''),
                ]);
                $message = renderAlert('Subject added', 'success');
            }
        }

        if ($action === 'update') {
            UpdateRecord($pdo, 'subjects', (int)$_POST['id'], [
                'subject_name'        => trim($_POST['subject_name']),
                'subject_description' => trim($_POST['subject_description'] ?? ''),
                'is_active'           => (int)$_POST['is_active'],
            ]);
            $_POST['edit'] = 0;   // close the edit row
            $message = renderAlert('Subject updated', 'success');
        }

        // Activate / Deactivate
        if ($action === 'toggle') {
            UpdateRecord($pdo, 'subjects', (int)$_POST['id'], [
                'is_active' => (int)$_POST['is_active'],
            ]);
            $message = renderAlert('Subject status changed', 'success');
        }

        // Give a subject to a teacher
        if ($action === 'assign') {
            InsertRecord($pdo, 'teacher_subjects', [
                'subject_id' => (int)$_POST['id'],
                'teacher_id' => (int)$_POST['teacher_id'],
            ], null);   // null = this pivot table has no id column to return
            $message = renderAlert('Teacher assigned', 'success');
        }

        // Take a subject away from a teacher
        if ($action === 'unassign') {
            $stmt = $pdo->prepare("DELETE FROM teacher_subjects WHERE subject_id = :s AND teacher_id = :t");
            $stmt->execute([
                ':s' => (int)$_POST['id'],
                ':t' => (int)$_POST['teacher_id'],
            ]);
            $message = renderAlert('Teacher removed from subject', 'success');
        }

    } catch (PDOException $e) {
        // UNIQUE failed (same subject name, or teacher already has this subject)
        $message = renderAlert('Could not save. That subject or assignment already exists.', 'danger');
    }
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
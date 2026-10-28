<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "setErrorFunctions.php");
require_once(DEBUG_PATH . "debugFunctions.php");

$action  = $_POST['action'] ?? '';
$message = '';

try {
    // Everything belongs to the current school year
    $year = CurrentSchoolYear($pdo);

    if ($year) {
        $pdo->beginTransaction();

        $id    = (int)($_POST['id'] ?? 0);
        $fname = trim($_POST['fname'] ?? '');
        $lname = trim($_POST['lname'] ?? '');
        $grade = trim($_POST['grade_level'] ?? '') ?: null;

        if ($action === 'create') {
            if ($fname === '' || $lname === '') {
                $message = renderAlert('First name and last name are required', 'danger');
            } else {
                // no guardian yet, it is assigned later from the table
                $stmt = $pdo->prepare("INSERT INTO students (school_year_id, fname, lname, grade_level)
                                       VALUES (:year, :fname, :lname, :grade)");
                $stmt->execute([
                    ':year'  => $year['id'],
                    ':fname' => $fname,
                    ':lname' => $lname,
                    ':grade' => $grade,
                ]);
                $message = renderAlert('Student added', 'success');
            }

        } elseif ($action === 'update') {
            if ($fname === '' || $lname === '') {
                $message = renderAlert('First name and last name are required', 'danger');
            } else {
                // the guardian is NOT touched here, only the student's own info
                $stmt = $pdo->prepare("UPDATE students
                                       SET fname = :fname, lname = :lname, grade_level = :grade
                                       WHERE id = :id AND school_year_id = :year");
                $stmt->execute([
                    ':fname' => $fname,
                    ':lname' => $lname,
                    ':grade' => $grade,
                    ':id'    => $id,
                    ':year'  => $year['id'],
                ]);
                $_POST['edit'] = 0;   // close the edit row
                $message = $stmt->rowCount()
                    ? renderAlert('Student updated', 'success')
                    : renderAlert('Student not found', 'danger');
            }

        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM students WHERE id = :id AND school_year_id = :year");
            $stmt->execute([':id' => $id, ':year' => $year['id']]);

            // if we were choosing a parent for this student, stop
            if ((int)($_POST['assign'] ?? 0) === $id) {
                $_POST['assign'] = 0;
            }
            $message = $stmt->rowCount()
                ? renderAlert('Student deleted', 'success')
                : renderAlert('Student not found', 'danger');

        } elseif ($action === 'assign') {
            // Give the student a parent (the guardian must be from this school year too)
            $guardianId = (int)($_POST['guardian_id'] ?? 0);

            $stmt = $pdo->prepare("SELECT 1 FROM guardians WHERE id = :guardian AND school_year_id = :year");
            $stmt->execute([':guardian' => $guardianId, ':year' => $year['id']]);

            if (!$stmt->fetchColumn()) {
                $message = renderAlert('Guardian not found', 'danger');
            } else {
                $stmt = $pdo->prepare("UPDATE students SET guardian_id = :guardian
                                       WHERE id = :id AND school_year_id = :year");
                $stmt->execute([':guardian' => $guardianId, ':id' => $id, ':year' => $year['id']]);
                $message = $stmt->rowCount()
                    ? renderAlert('Parent assigned', 'success')
                    : renderAlert('Student not found', 'danger');
            }

        } elseif ($action === 'unassign') {
            // Take the parent away (guardian_id becomes NULL, not 0)
            $stmt = $pdo->prepare("UPDATE students SET guardian_id = NULL
                                   WHERE id = :id AND school_year_id = :year");
            $stmt->execute([':id' => $id, ':year' => $year['id']]);
            $message = $stmt->rowCount()
                ? renderAlert('Parent removed', 'success')
                : renderAlert('Student not found', 'danger');
        }

        $pdo->commit();
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    err_log($e->getMessage());
    $message = renderAlert('Could not save. Please check the data and try again.', 'danger');
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
    'gsearch'      => $_POST['gsearch']      ?? '',
    'gPageNum'     => $_POST['gPageNum']     ?? 1,
];

require_once(COMPONENTS_PATH . DS . "manage_students.php");

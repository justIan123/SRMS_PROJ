<?php
require_once("../public/initialize.php");
require_once(CONFIG_PATH . DS . "db_sqlite.php");
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");

$year    = CurrentSchoolYear($pdo);
$action  = $_POST['action'] ?? '';
$message = '';

if ($year) {
    try {

        if ($action === 'create') {
            $fname = trim($_POST['fname'] ?? '');
            $lname = trim($_POST['lname'] ?? '');

            if ($fname === '' || $lname === '') {
                $message = renderAlert('First name and last name are required', 'danger');
            } else {
                // no guardian yet, it is assigned later from the table
                InsertRecord($pdo, 'students', [
                    'school_year_id' => $year['id'],
                    'fname'          => $fname,
                    'lname'          => $lname,
                    'grade_level'    => trim($_POST['grade_level'] ?? '') ?: null,
                ]);
                $message = renderAlert('Student added', 'success');
            }
        }

        if ($action === 'update') {
            // the guardian is NOT touched here, only the student's own info
            UpdateRecord($pdo, 'students', (int)$_POST['id'], [
                'fname'       => trim($_POST['fname']),
                'lname'       => trim($_POST['lname']),
                'grade_level' => trim($_POST['grade_level'] ?? '') ?: null,
            ]);
            $_POST['edit'] = 0;   // close the edit row
            $message = renderAlert('Student updated', 'success');
        }

        if ($action === 'delete') {
            DeleteRecord($pdo, 'students', (int)$_POST['id']);

            // if we were choosing a parent for this student, stop
            if ((int)($_POST['assign'] ?? 0) === (int)$_POST['id']) {
                $_POST['assign'] = 0;
            }
            $message = renderAlert('Student deleted', 'success');
        }

        // Give the student a parent
        if ($action === 'assign') {
            UpdateRecord($pdo, 'students', (int)$_POST['id'], [
                'guardian_id' => (int)$_POST['guardian_id'],
            ]);
            $message = renderAlert('Parent assigned', 'success');
        }

        // Take the parent away (guardian_id becomes NULL, not 0)
        if ($action === 'unassign') {
            UpdateRecord($pdo, 'students', (int)$_POST['id'], [
                'guardian_id' => null,
            ]);
            $message = renderAlert('Parent removed', 'success');
        }

    } catch (PDOException $e) {
        $message = renderAlert('Could not save. Please check the data and try again.', 'danger');
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
    'gsearch'      => $_POST['gsearch']      ?? '',
    'gPageNum'     => $_POST['gPageNum']     ?? 1,
];

require_once(COMPONENTS_PATH . DS . "manage_students.php");
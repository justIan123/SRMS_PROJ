<?php
require_once("../public/initialize.php");
require_once(DB_PATH);
require_once(FUNCTIONS_PATH . DS . "DbFunctions.php");
require_once(FUNCTIONS_PATH . DS . "renderFunctions.php");
require_once(FUNCTIONS_PATH . DS . "schoolFunctions.php");

$action  = $_POST['action'] ?? '';
$message = '';
try{
    if ($action === 'create') {
        $start = $_POST['start_date'];
        $end   = $_POST['end_date'];
    
        if ($end <= $start) {
            $message = renderAlert('The end date must be after the start date.', 'danger');
        } else {
            InsertRecord($pdo, 'school_years', [
                'name'       => trim($_POST['name']),
                'start_date' => $start,
                'end_date'   => $end,
                'is_active'  => 0,
            ]);
            $message = renderAlert('School year created. Press Start to make it the current one.', 'success');
        }
    }
    
    if ($action === 'start') {
        $pdo->exec("UPDATE school_years SET is_active = 0");          // only one can run at a time
        UpdateRecord($pdo, 'school_years', (int)$_POST['id'], ['is_active' => 1]);
        $message = renderAlert('School year started.', 'success');
    }
    
    if ($action === 'end') {
        UpdateRecord($pdo, 'school_years', (int)$_POST['id'], ['is_active' => 0]);
        $message = renderAlert('<strong>Success!</strong> School year ended.', 'success');
    }
}catch(PDOException $e){
    $message = renderAlert('Something went wrong.', 'warning');
}

// show the page again ($message is printed there)
require_once(COMPONENTS_PATH . DS . "manage_schoolYear.php");

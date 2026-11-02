<?php
//must include require_once(DB_PATH);
 
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

require_once(DB_PATH); // Replaces DB_PATH connection

$isUserIsAdmin = false;
$isUserIsTeacher = false;

try {
    // Query Supabase for admin role (role = 1)
    $adminRows = supabase_query('user_roles?select=role&user_id=eq.' . $_SESSION['user_id'] . '&role=eq.1&limit=1');
    $isUserIsAdmin = !empty($adminRows);
} catch (Exception $e) {
    // Handle or log exception if needed
}

try {
    // Query Supabase for teacher/employee role (role = 2 based on your roles table)
    $teacherRows = supabase_query('user_roles?select=role&user_id=eq.' . $_SESSION['user_id'] . '&role=eq.2&limit=1');
    $isUserIsTeacher = !empty($teacherRows);
} catch (Exception $e) {
    // Handle or log exception if needed
}
?>
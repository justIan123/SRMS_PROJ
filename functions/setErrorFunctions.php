<?php
/**
 * @param string PDOException $e - SQL error code
 * Purpose: set error msg to render to the user
 * mostly used in try-catch block in tryng to get any errors in the sql query 
 */
function SetErrorMessage(PDOException $e) {
    $errorCode = $e->getCode();          // SQLSTATE code
    $driverCode = $e->errorInfo[1] ?? ''; // Driver-specific error code
    $msg = "Registration failed due to a database error.";

    // Handle integrity constraint violations
    if ($errorCode === '23000') {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            $msg = "Username already exists. Please choose another.";
        } elseif (strpos($e->getMessage(), 'users.email') !== false) {
            $msg = "Email already exist. Try logging in.";
        } elseif (strpos($e->getMessage(), 'profile_pic') !== false) {
            $msg = "Profile picture could not be saved.";
        } elseif (strpos($e->getMessage(), 'face_descriptions') !== false) {
            $msg = "Face data could not be saved.";
        }
    }

    return $msg;
}
?>
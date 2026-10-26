<?php
/**
 * @param PDOException $e - the database exception
 * Purpose: set error msg to render to the user
 * mostly used in try-catch block in trying to get any errors in the sql query
 *
 * Postgres (Supabase) version. The SQLSTATE codes are different from SQLite/MySQL:
 *   23505 = unique violation (duplicate value)
 *   23502 = not-null violation (a required column was empty)
 * Postgres puts the details in the message, for example:
 *   duplicate key value violates unique constraint "users_email_key"
 *   DETAIL:  Key (email)=(a@b.com) already exists.
 *
 * Tip: name your unique constraints clearly (users_email_key, guardians_email_key),
 * because the guardian/user email check below reads the constraint name.
 */
function SetErrorMessage(PDOException $e): string
{
    $errorCode = (string) $e->getCode();   // SQLSTATE code
    $text      = $e->getMessage();
    $msg       = "Registration failed due to a database error.";

    // name of the constraint that failed, e.g. users_email_key (may be empty)
    $constraint = '';
    if (preg_match('/constraint "([^"]+)"/', $text, $m)) {
        $constraint = strtolower($m[1]);
    }

    // Duplicate value
    if ($errorCode === '23505') {
        if (strpos($text, 'Key (username)') !== false || strpos($constraint, 'username') !== false) {
            $msg = "Username already exists. Please choose another.";
        } elseif (strpos($constraint, 'guardian') !== false) {
            $msg = "guardians email already exist.";
        } elseif (strpos($text, 'Key (email)') !== false || strpos($constraint, 'email') !== false) {
            $msg = "Email already exist. Try logging in.";
        }
    }

    // A required column was empty
    if ($errorCode === '23502' && strpos($text, 'profile_pic') !== false) {
        $msg = "Profile picture could not be saved.";
    }

    return $msg;
}
?>
<?php
//password = omzpwzCPHrpayUiB
// $dsn = "pgsql:host=db.jomzzddiybsqwrkhfyap.supabase.co;port=5432;dbname=postgres;user=postgres;password=omzpwzCPHrpayUiB;sslmode=require";
// $dsn = "postgresql://postgres:omzpwzCPHrpayUiB@db.jomzzddiybsqwrkhfyap.supabase.co:5432/postgres";

$host     = 'aws-0-ap-southeast-1.pooler.supabase.com'; // Your Supabase host
$db       = 'postgres';                          // Default DB name
$user     = 'postgres.jomzzddiybsqwrkhfyap';          // Database user
$pass     = 'omzpwzCPHrpayUiB';            // Database password
$port     = '5432';                              // Transaction pooler port (or 5432)

$dsn = "pgsql:host=$host;port=$port;dbname=$db;";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
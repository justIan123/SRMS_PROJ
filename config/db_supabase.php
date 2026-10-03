<?php
//password = omzpwzCPHrpayUiB
// $dsn = "pgsql:host=db.jomzzddiybsqwrkhfyap.supabase.co;port=5432;dbname=postgres;user=postgres;password=omzpwzCPHrpayUiB;sslmode=require";
// $dsn = "postgresql://postgres:omzpwzCPHrpayUiB@db.jomzzddiybsqwrkhfyap.supabase.co:5432/postgres";

$host     = "";
$db       = "";
$user     = "";
$pass     = "";
$port     = "";

$dsn = "pgsql:host=$host;port=$port;dbname=$db;";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
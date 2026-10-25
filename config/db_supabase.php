<?php

require_once __DIR__ . "/bootstrap.php";

$host = $_ENV['SUPABASE_HOST'];
$db   = $_ENV['SUPABASE_DB'];
$user = $_ENV['SUPABASE_USER'];
$pass = $_ENV['SUPABASE_PASS'];
$port = $_ENV['SUPABASE_PORT'];

$dsn = "pgsql:host=$host;port=$port;dbname=$db";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("PostgreSQL connection failed: " . $e->getMessage());
}
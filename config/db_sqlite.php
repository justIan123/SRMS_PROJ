<?php
// Path to your SQLite database file
$db_path = __DIR__ . "/sqlite.db";

try {
    $pdo = new PDO("sqlite:" . $db_path, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT => 5 // give sqlite 5 second to finish the query to the next one 
    ]);

    $pdo->exec("PRAGMA journal_mode = WAL;");

} catch (PDOException $e) {
    die("SQLite connection failed: " . $e->getMessage());
}
?>
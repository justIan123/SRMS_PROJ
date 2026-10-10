<?php
require_once("../public/index.php");
require_once(CONFIG_PATH . DS . "db_sqlite.php");

if ($_SERVER["REQUEST_METHOD"] === "DELETE") {
    $id = (int)($_GET['id'] ?? 0);

    if ($id > 0) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("DELETE FROM guardians WHERE id = :id");
            $stmt->execute([
                ':id' => $id
            ]);

            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }
}

// Render updated table directly
// if dont u dont get the updated data
require_once("readGuardians.php");
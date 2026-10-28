<?php

/**
 * The school year that is running now (is_active = 1), or null if none started.
 * Plain PDO. It can throw PDOException, so call it inside your try/catch.
 */
function CurrentSchoolYear(PDO $pdo): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM school_years WHERE is_active = :active LIMIT 1");
    $stmt->execute([':active' => 1]);

    return $stmt->fetch() ?: null;
}
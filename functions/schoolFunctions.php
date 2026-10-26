<?php

/**
 * The school year that is running now (is_active = 1), or null if none started.
 */
function CurrentSchoolYear(PDO $pdo): ?array
{
    $rows = FindWhere($pdo, 'school_years', ['is_active' => 1], 1);
    return $rows[0] ?? null;
}
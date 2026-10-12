<?php

/**
 * The school year that is running now (is_active = 1), or null if none started.
 */
function CurrentSchoolYear(PDO $pdo): ?array
{
    $rows = FindWhere($pdo, 'school_years', ['is_active' => 1], 1);
    return $rows[0] ?? null;
}

/**
 * How many rows ListRecords() would find in total (for the page numbers).
 */
function CountList(PDO $pdo, string $table, array $conditions, array $searchCols, string $keyword): int
{
    [$where, $params] = buildListWhere($conditions, $searchCols, $keyword);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . safeIdentifier($table) . $where);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

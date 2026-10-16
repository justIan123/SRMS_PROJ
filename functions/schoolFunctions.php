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
 * Count the total rows matching a list query, for building pagination.
 *
 * Takes the same filter arguments as ListRecords(), so the total always
 * matches what the list actually shows. Pass the same $conditions,
 * $searchCols and $keyword to both, then divide the count by your page size.
 *
 * @param PDO      $pdo         Active database connection.
 * @param string   $table       Table name (validated by safeIdentifier()).
 * @param array    $conditions  Exact-match filters, e.g. ['status' => 'active'].
 *                              Pass [] for no filters.
 * @param string[] $searchCols  Columns the keyword is searched against,
 *                              e.g. ['name', 'email']. Pass [] to skip search.
 * @param string   $keyword     Search text. An empty string means no search.
 *
 * @return int Total number of matching rows (ignores LIMIT/OFFSET).
 *
 * @throws PDOException If the query fails.
 *
 * @example
 * $conditions = ['status' => 'active'];
 * $searchCols = ['name', 'email'];
 * $keyword    = $_GET['q'] ?? '';
 * $perPage    = 20;
 * $page       = max(1, (int) ($_GET['page'] ?? 1));
 *
 * $total      = CountList($pdo, 'users', $conditions, $searchCols, $keyword);
 * $totalPages = (int) ceil($total / $perPage);
 *
 * // Fetch the current page's rows with the same filters:
 * $rows = ListRecords($pdo, 'users', $conditions, $searchCols, $keyword, $page, $perPage);
 */
function CountList(PDO $pdo, string $table, array $conditions, array $searchCols, string $keyword): int
{
    [$where, $params] = buildListWhere($conditions, $searchCols, $keyword);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM " . safeIdentifier($table) . $where);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}
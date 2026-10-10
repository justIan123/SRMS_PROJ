<?php
/**
 * @param string $table - Name of the database table to search.
 * @param array $columns - List of columns that will be searched.
 * @param string $keyword - Search value entered by the user.
 * @param PDO $pdo - Active PDO database connection.
 * Expected value:TableSearch(WHERE TO SEARCH,WHAT COLUMNS TO SEARCH,WHAT TO SEARCH FOR,DATABASE CONNECTION);
 * $students = TableSearch(
 *      'students',
 *      ['firstname', 'lastname', 'email'],
 *      $_GET['search'],
 *      $pdo
 *   );
 */
function TableSearch(string $table, array $columns, $keyword, PDO $pdo)
{
    $conditions = [];

    foreach ($columns as $column) {
        $conditions[] = "$column LIKE ?";
    }

    $sql = "SELECT * FROM $table WHERE " .
           implode(' OR ', $conditions);

    $stmt = $pdo->prepare($sql);

    $search = "%{$keyword}%";
    $params = array_fill(0, count($columns), $search);

    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * @param string $table - Name of the database table to search.
 * @param int $page - page that will be included.
 * @param int $limit - Limmit rows to get.
 * @param PDO $pdo - Active PDO database connection.
 * Expected value:TableSearch(WHERE TO SEARCH,WHAT COLUMNS TO SEARCH,WHAT TO SEARCH FOR,DATABASE CONNECTION);
 * $students = TablePaginate(
 *   'students',
 *   $_GET['page'] ?? 1,
 *   10,
 *   $pdo
 * );
 */
function TablePaginate(
    string $table,
    int $page,
    int $limit,
    PDO $pdo
): array {

    $page = max(1, $page);
    $limit = max(1, $limit);

    $offset = ($page - 1) * $limit;

    // Get total records
    $countStmt = $pdo->query("SELECT COUNT(*) FROM $table");
    $totalRecords = $countStmt->fetchColumn();

    // Get paginated records
    $stmt = $pdo->prepare("
        SELECT *
        FROM $table
        LIMIT :limit OFFSET :offset
    ");

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();

    return [
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'total_records' => $totalRecords,
        'total_pages' => ceil($totalRecords / $limit),
        'current_page' => $page,
        'limit' => $limit
    ];
}
/**
 * @param string $table - Name of the database table to search.
 * @param array $columns - List of columns that will be searched.
 * @param string $keyword - Search value entered by the user.
 * @param int $page - page that will be included.
 * @param int $limit - Limmit rows to get.
 * @param PDO $pdo - Active PDO database connection.
 * USecase:<br>
 * $keyword = $_GET['search'] ?? '';
 * $page = $_GET['page'] ?? 1;
 *
 * $result = TableSearchPaginate(
 *   'students',
 *   ['firstname', 'lastname', 'email'],
 *   $keyword,
 *   $page,
 *   10,
 *   $pdo
 * );
 * 
 * foreach ($result['data'] as $student) {
 * ?>
 * <tr>
 *   <td><?= $student['firstname'] ?></td>
 *   <td><?= $student['lastname'] ?></td>
 *   <td><?= $student['email'] ?></td>
 * </tr>
 * <?php
 * }
 * ?>
 */
function TableSearchPaginate(
    string $table,
    array $columns,
    string $keyword,
    int $page,
    int $limit,
    PDO $pdo
): array {

    $page = max(1, $page);
    $offset = ($page - 1) * $limit;

    $conditions = [];

    foreach ($columns as $column) {
        $conditions[] = "$column LIKE ?";
    }

    $whereClause = implode(' OR ', $conditions);

    $search = "%{$keyword}%";
    $params = array_fill(0, count($columns), $search);

    // Count total matching records
    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM $table
        WHERE $whereClause
    ");

    $countStmt->execute($params);

    $totalRecords = $countStmt->fetchColumn();

    // Get matching records with pagination
    $sql = "
        SELECT *
        FROM $table
        WHERE $whereClause
        LIMIT $limit
        OFFSET $offset
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return [
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'total_records' => $totalRecords,
        'total_pages' => ceil($totalRecords / $limit),
        'current_page' => $page
    ];
}

function TableSearchPaginate1(
    string $table,
    array $joins,
    array $columns,
    string $keyword,
    int $page,
    int $limit,
    PDO $pdo
): array {

    $page = max(1, $page);
    $offset = ($page - 1) * $limit;

    $conditions = [];

    foreach ($columns as $column) {
        $conditions[] = "$column LIKE ?";
    }

    $whereClause = implode(' OR ', $conditions);

    $joinClause = implode(' ', $joins);

    $search = "%{$keyword}%";
    $params = array_fill(0, count($columns), $search);

    // Count matching records
    $countSql = "
        SELECT COUNT(*)
        FROM {$table}
        {$joinClause}
        WHERE {$whereClause}
    ";

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);

    $totalRecords = $countStmt->fetchColumn();

    // Fetch records
    $sql = "
        SELECT *
        FROM {$table}
        {$joinClause}
        WHERE {$whereClause}
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $index => $value) {
        $stmt->bindValue($index + 1, $value);
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();

    return [
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'total_records' => $totalRecords,
        'total_pages' => ceil($totalRecords / $limit),
        'current_page' => $page,
        'limit' => $limit
    ];
}
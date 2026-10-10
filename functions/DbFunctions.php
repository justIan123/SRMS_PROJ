<?php


/**
 * @param string $name - Name of the database table to search.
 * Helper: table/column names can't be bound as parameters, so we
 * validate them to prevent SQL injection.
 * 
 */
function safeIdentifier(string $name): string
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
        throw new InvalidArgumentException("Invalid identifier: $name");
    }
    return "`$name`";
}
 
/**
 * @param PDO $pdo - Name of the database table to search.
 * @param string $table - Name of the table to able to get the id.
 * @param int $id - id number you want to get.
 * @param string $idColumn - Name of the column of the id you want to get.
 * Helper: table/column names can't be bound as parameters, so we
 * validate them to prevent SQL injection.
 * 
 */
function FindById(PDO $pdo, string $table, $id, string $idColumn = 'id'): ?array
{
    $sql  = "SELECT * FROM " . safeIdentifier($table) .
            " WHERE " . safeIdentifier($idColumn) . " = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
 
    $row = $stmt->fetch();
    return $row ?: null;
}
 
/**
 * @param PDO $pdo - Name of the database table to search.
 * @param string $table - Name of the table you want to insert data.
 * @param array $data - associative array.
 * Helper: insert associative array data like this <br>
 * // INSERT: returns the new auto-increment ID <br>
 * $id = InsertRecord($pdo, 'users', [ <br>
 * 'name'  => 'Juan Dela Cruz', <br>
 * 'email' => 'juan@example.com', <br>
 * ])
 */
function InsertRecord(PDO $pdo, string $table, array $data): string
{
    if (empty($data)) {
        throw new InvalidArgumentException('No data provided for insert.');
    }
 
    $columns      = array_map('safeIdentifier', array_keys($data));
    $placeholders = array_map(fn($col) => ':' . $col, array_keys($data));
 
    $sql = "INSERT INTO " . safeIdentifier($table) .
           " (" . implode(', ', $columns) . ")" .
           " VALUES (" . implode(', ', $placeholders) . ")";
 
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
 
    return $pdo->lastInsertId();
}
 
/**
 * @param PDO $pdo - Name of the database table to search.
 * @param string $table - Name of the table you want to update data.
 * @param int $id - Name of the id you want to update data.
 * @param array $data - associative array.
 * @param string $idColumn - Name of the column of the id you want to update.
 * Helper: use the func like this <br>
 * // UPDATE: returns the number of rows changed <br>
 * UpdateRecord($pdo, 'users', $id, ['name' => 'Juan D. Cruz']);
 */
function UpdateRecord(PDO $pdo, string $table, $id, array $data, string $idColumn = 'id'): int
{
    if (empty($data)) {
        throw new InvalidArgumentException('No data provided for update.');
    }
 
    $sets   = [];
    $params = [];
    foreach ($data as $column => $value) {
        $sets[]                 = safeIdentifier($column) . " = :set_" . $column;
        $params[':set_' . $column] = $value;
    }
    $params[':id'] = $id;
 
    $sql = "UPDATE " . safeIdentifier($table) .
           " SET " . implode(', ', $sets) .
           " WHERE " . safeIdentifier($idColumn) . " = :id";
 
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
 
    return $stmt->rowCount();
}
 
/**
 * @param PDO $pdo - Name of the database table to search.
 * @param string $table - Name of the table you want to delete data.
 * @param int $id - Name of the id you want to delte data.
 * @param string $idColumn - Name of the column of the id you want to delte.
 * Helper: use the func  like this <br>
 * // DELETE: returns the number of rows deleted<br>
 * DeleteRecord($pdo, 'users', $id);
 */
function DeleteRecord(PDO $pdo, string $table, $id, string $idColumn = 'id'): int
{
    $sql  = "DELETE FROM " . safeIdentifier($table) .
            " WHERE " . safeIdentifier($idColumn) . " = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
 
    return $stmt->rowCount();
}
 

/**
 * @param PDO $pdo - The database connection from getConnection().
 * @param string $table - Name of the table you want to read from.
 * @param int $limit - Max number of rows to return (page size). Default is 50.
 * @param int $offset - Number of rows to skip. Use (page - 1) * $limit for page numbers. Default is 0.
 * @param string $orderBy - Column to sort by. Default is 'id'.
 * @param string $direction - 'ASC' (oldest first) or 'DESC' (newest first). Default is 'ASC'.
 * @return array - A list of rows (each row is an associative array). Empty array if none.
 * Helper: get many rows with pagination like this <br>
 * // FIND ALL: page 2, 10 rows per page, newest first <br>
 * $page = 2; $perPage = 10; <br>
 * $users = FindAll($pdo, 'users', $perPage, ($page - 1) * $perPage, 'id', 'DESC');
 */
function FindAll(
    PDO $pdo,
    string $table,
    int $limit = 50,
    int $offset = 0,
    string $orderBy = 'id',
    string $direction = 'ASC'
): array {
    $direction = strtoupper($direction);
    if (!in_array($direction, ['ASC', 'DESC'], true)) {
        throw new InvalidArgumentException("Direction must be ASC or DESC.");
    }
 
    // (int) casts make it safe to place limit/offset directly in the SQL.
    $sql = "SELECT * FROM " . safeIdentifier($table) .
           " ORDER BY " . safeIdentifier($orderBy) . " $direction" .
           " LIMIT " . max(0, $limit) . " OFFSET " . max(0, $offset);
 
    return $pdo->query($sql)->fetchAll();
}

/**
 * @param array $conditions - associative array of column => value. A null value becomes "IS NULL".
 * @return array - [ $whereSql, $params ]. $whereSql is an empty string when there are no conditions.
 * Helper: builds a "WHERE a = :a AND b = :b" clause. Used internally by FindWhere() and CountRecords().
 * 
 * idk this is use for find where
 */
function buildWhereClause(array $conditions): array
{
    if (empty($conditions)) {
        return ['', []];
    }
 
    $parts  = [];
    $params = [];
 
    foreach ($conditions as $column => $value) {
        $quoted = safeIdentifier($column);
 
        if ($value === null) {
            $parts[] = "$quoted IS NULL";
            continue;
        }
 
        $placeholder          = ':w_' . $column;
        $parts[]              = "$quoted = $placeholder";
        $params[$placeholder] = $value;
    }
 
    return [' WHERE ' . implode(' AND ', $parts), $params];
}

/**
 * @param PDO $pdo - The database connection from getConnection().
 * @param string $table - Name of the table you want to search.
 * @param array $conditions - associative array of column => value. All conditions must match (AND). Use null to match NULL.
 * @param int|null $limit - Max number of rows to return. Default is null (no limit).
 * @param int $offset - Number of rows to skip (only used when $limit is set). Default is 0.
 * @return array - A list of matching rows. Empty array if none match.
 * Helper: find rows by any column like this <br>
 * // FIND WHERE: login lookup by email <br>
 * $rows = FindWhere($pdo, 'users', ['email' => 'juan@example.com']); <br>
 * $user = $rows[0] ?? null; <br>
 * <br>
 * // Multiple conditions (AND) <br>
 * $admins = FindWhere($pdo, 'users', ['role' => 'admin', 'active' => 1]);
 */
function FindWhere(PDO $pdo, string $table, array $conditions, ?int $limit = null, int $offset = 0): array
{
    [$where, $params] = buildWhereClause($conditions);
 
    $sql = "SELECT * FROM " . safeIdentifier($table) . $where;
 
    if ($limit !== null) {
        $sql .= " LIMIT " . max(0, $limit) . " OFFSET " . max(0, $offset);
    }
 
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
 
    return $stmt->fetchAll();
}

 
/**
 * @param PDO $pdo - The database connection from getConnection().
 * @param string $table - Name of the table you want to count rows in.
 * @param array $conditions - Optional associative array of column => value (AND). Default is empty (count everything).
 * @return int - The number of matching rows.
 * Helper: count rows, handy for pagination <br>
 * // COUNT: all users <br>
 * $total = CountRecords($pdo, 'users'); <br>
 * <br>
 * // COUNT: only active users <br>
 * $active = CountRecords($pdo, 'users', ['active' => 1]); <br>
 * <br>
 * // Total pages when showing 10 per page <br>
 * $totalPages = (int) ceil($total / 10);
 */
function CountRecords(PDO $pdo, string $table, array $conditions = []): int
{
    [$where, $params] = buildWhereClause($conditions);
 
    $sql  = "SELECT COUNT(*) FROM " . safeIdentifier($table) . $where;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
 
    return (int) $stmt->fetchColumn();
}


/**
 * @param PDO $pdo - The database connection from getConnection().
 * @param string $table - Name of the table you want to check.
 * @param string $column - Name of the column to look in.
 * @param mixed $value - The value you are looking for.
 * @return bool - true if at least one row has that value, otherwise false.
 * Helper: quick duplicate check before inserting <br>
 * // EXISTS: stop duplicate emails <br>
 * if (RecordExists($pdo, 'users', 'email', 'juan@example.com')) { <br>
 * echo 'Email already taken.'; <br>
 * } else { <br>
 * InsertRecord($pdo, 'users', [...]); <br>
 * }
 */
function RecordExists(PDO $pdo, string $table, string $column, $value): bool
{
    $sql  = "SELECT 1 FROM " . safeIdentifier($table) .
            " WHERE " . safeIdentifier($column) . " = :value LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':value' => $value]);
 
    return (bool) $stmt->fetchColumn();
}


/**
 * @param PDO $pdo - The database connection from getConnection().
 * @param string $table - Name of the table you want to search.
 * @param array $columns - List of column names to search in, e.g. ['name', 'email']. A row matches if ANY column contains the keyword.
 * @param string $keyword - The text to look for (matches anywhere inside the value, case-insensitive in most MySQL setups).
 * @param int $limit - Max number of rows to return. Default is 50.
 * @param int $offset - Number of rows to skip. Default is 0.
 * @param string $orderBy - Column to sort by. Default is 'id'.
 * @param string $direction - 'ASC' or 'DESC'. Default is 'ASC'.
 * @return array - A list of matching rows. Empty array if none match.
 * Helper: keyword search for a search box like this <br>
 * // SEARCH: look for "juan" in name OR email <br>
 * $results = SearchRecords($pdo, 'users', ['name', 'email'], 'juan'); <br>
 * <br>
 * // With pagination (page 2, 10 per page) <br>
 * $results = SearchRecords($pdo, 'users', ['name', 'email'], $_GET['q'], 10, 10);
 */
function SearchRecords(
    PDO $pdo,
    string $table,
    array $columns,
    string $keyword,
    int $limit = 50,
    int $offset = 0,
    string $orderBy = 'id',
    string $direction = 'ASC'
): array {
    $direction = strtoupper($direction);
    if (!in_array($direction, ['ASC', 'DESC'], true)) {
        throw new InvalidArgumentException("Direction must be ASC or DESC.");
    }
 
    if (empty($columns)) {
        throw new InvalidArgumentException('Provide at least one column to search.');
    }
 
    // Escape %, _ and the escape character itself so the text is matched literally.
    // '!' is used as the escape character because it works in both MySQL and SQLite.
    $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword);
 
    $conditions = [];
    $params     = [];
    foreach (array_values($columns) as $i => $column) {
        $placeholder          = ':kw' . $i;   // one placeholder per column
        $conditions[]         = safeIdentifier($column) . " LIKE $placeholder ESCAPE '!'";
        $params[$placeholder] = '%' . $escaped . '%';
    }
 
    $sql = "SELECT * FROM " . safeIdentifier($table) .
           " WHERE (" . implode(' OR ', $conditions) . ")" .
           " ORDER BY " . safeIdentifier($orderBy) . " $direction" .
           " LIMIT " . max(0, $limit) . " OFFSET " . max(0, $offset);
 
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
 
    return $stmt->fetchAll();
}
 
 
/**
 * @param PDO $pdo - The database connection from getConnection().
 * @param string $table - Name of the table you want to search.
 * @param array $columns - List of column names to search in, e.g. ['username', 'email'].
 * @param string $keyword - The text to look for. Same matching rules as SearchRecords().
 * @return int - The number of rows SearchRecords() would find in total (ignoring limit/offset).
 * Helper: total matches for a search, used to work out the number of pages <br>
 * // COUNT SEARCH: how many users match "juan"? <br>
 * $total = CountSearchRecords($pdo, 'users', ['username', 'email'], 'juan'); <br>
 * $totalPages = (int) ceil($total / 10);
 */
function CountSearchRecords(PDO $pdo, string $table, array $columns, string $keyword): int
{
    if (empty($columns)) {
        throw new InvalidArgumentException('Provide at least one column to search.');
    }
 
    $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword);
 
    $conditions = [];
    $params     = [];
    foreach (array_values($columns) as $i => $column) {
        $placeholder          = ':kw' . $i;
        $conditions[]         = safeIdentifier($column) . " LIKE $placeholder ESCAPE '!'";
        $params[$placeholder] = '%' . $escaped . '%';
    }
 
    $sql = "SELECT COUNT(*) FROM " . safeIdentifier($table) .
           " WHERE (" . implode(' OR ', $conditions) . ")";
 
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
 
    return (int) $stmt->fetchColumn();
}
 
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

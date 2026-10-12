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

/* ======================================================================
 * JOIN FUNCTIONS (this is bad practice seryoso )
 * ====================================================================== */
 
/**
 * @param string $name - Column like "students.id" (table.column) or just "id".
 * @return string - Safely quoted, e.g. `students`.`id`
 * Helper: same protection as safeIdentifier(), but allows one dot for table.column.
 */
function safeQualified(string $name): string
{
    return implode('.', array_map('safeIdentifier', explode('.', $name)));
}
 
/**
 * Helper (internal): builds the "FROM ... JOIN ..." part. Used by FindWithJoin() and CountWithJoin().
 * @param array $joins - [[TYPE, joinTable, leftColumn, rightColumn], ...] TYPE = INNER, LEFT or RIGHT.
 */
function buildJoinFrom(string $table, array $joins): string
{
    $sql = " FROM " . safeIdentifier($table);
 
    foreach ($joins as $join) {
        if (count($join) !== 4) {
            throw new InvalidArgumentException('Each join must be [TYPE, table, leftColumn, rightColumn].');
        }
        [$type, $joinTable, $left, $right] = $join;
 
        $type = strtoupper($type);
        if (!in_array($type, ['INNER', 'LEFT', 'RIGHT'], true)) {
            throw new InvalidArgumentException("Invalid join type: $type");
        }
 
        $sql .= " $type JOIN " . safeIdentifier($joinTable) .
                " ON " . safeQualified($left) . " = " . safeQualified($right);
    }
 
    return $sql;
}
 
/**
 * Helper (internal): like buildWhereClause() but accepts "table.column" keys.
 * Placeholders are numbered (:jw0, :jw1...) because a dot can't be part of a placeholder name.
 * @return array - [ $whereSql, $params ]
 */
function buildJoinWhere(array $conditions): array
{
    if (empty($conditions)) {
        return ['', []];
    }
 
    $parts  = [];
    $params = [];
    $i      = 0;
 
    foreach ($conditions as $column => $value) {
        $quoted = safeQualified($column);
 
        if ($value === null) {
            $parts[] = "$quoted IS NULL";
            continue;
        }
 
        $placeholder          = ':jw' . $i++;
        $parts[]              = "$quoted = $placeholder";
        $params[$placeholder] = $value;
    }
 
    return [' WHERE ' . implode(' AND ', $parts), $params];
}
 
/**
 * @param PDO $pdo - The database connection from getConnection().
 * @param string $table - Main table (the one after FROM), e.g. 'students'.
 * @param array $joins - List of [TYPE, joinTable, leftColumn, rightColumn]. TYPE is INNER, LEFT or RIGHT.
 * @param array $select - Columns to return. Use "table.column" and optionally "AS alias". Default ['*'].
 * @param array $conditions - column => value (AND). Use "table.column" keys. null becomes IS NULL.
 * @param int|null $limit - Max rows. Default null (no limit).
 * @param int $offset - Rows to skip (only used when $limit is set). Default 0.
 * @param string|null $orderBy - Column to sort by, e.g. 'students.lastname'. Default null (no ORDER BY).
 * @param string $direction - 'ASC' or 'DESC'. Default 'ASC'.
 * @return array - A list of rows (associative arrays). Empty array if none.
 * Helper: SQL JOIN like this <br>
 * // students + their course name, page 1 of 10, sorted by last name <br>
 * $rows = FindWithJoin( <br>
 *     $pdo, 'students', <br>
 *     [['INNER', 'courses', 'students.course_id', 'courses.id']], <br>
 *     ['students.id', 'students.firstname', 'courses.name AS course_name'], <br>
 *     ['students.active' => 1], <br>
 *     10, 0, 'students.lastname', 'ASC' <br>
 * ); <br>
 * <br>
 * Tip: give columns that share a name an alias (AS), or the later one overwrites the earlier one.
 */
function FindWithJoin(
    PDO $pdo,
    string $table,
    array $joins,
    array $select = ['*'],
    array $conditions = [],
    ?int $limit = null,
    int $offset = 0,
    ?string $orderBy = null,
    string $direction = 'ASC'
): array {
    if (empty($select)) {
        throw new InvalidArgumentException('Provide at least one column to select.');
    }
 
    // SELECT list: "*", "table.*", "col", "table.col" or any of those with "AS alias"
    $cols = [];
    foreach ($select as $col) {
        if ($col === '*') {
            $cols[] = '*';
        } elseif (preg_match('/^(\w+)\.\*$/', $col, $m)) {
            $cols[] = safeIdentifier($m[1]) . '.*';
        } elseif (preg_match('/^([\w.]+)(?:\s+AS\s+(\w+))?$/i', $col, $m)) {
            $cols[] = safeQualified($m[1]) . (isset($m[2]) ? ' AS ' . safeIdentifier($m[2]) : '');
        } else {
            throw new InvalidArgumentException("Invalid select column: $col");
        }
    }
 
    [$where, $params] = buildJoinWhere($conditions);
 
    $sql = "SELECT " . implode(', ', $cols) . buildJoinFrom($table, $joins) . $where;
 
    if ($orderBy !== null) {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException("Direction must be ASC or DESC.");
        }
        $sql .= " ORDER BY " . safeQualified($orderBy) . " $direction";
    }
 
    if ($limit !== null) {
        $sql .= " LIMIT " . max(0, $limit) . " OFFSET " . max(0, $offset);
    }
 
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
 
    return $stmt->fetchAll();
}
 
/**
 * @param PDO $pdo - The database connection from getConnection().
 * @param string $table - Main table, e.g. 'students'.
 * @param array $joins - Same format as FindWithJoin().
 * @param array $conditions - Same format as FindWithJoin(). Default is empty (count everything).
 * @return int - Number of rows the join would return (ignoring limit/offset).
 * Helper: total rows for paginating a FindWithJoin() <br>
 * $joins = [['INNER', 'courses', 'students.course_id', 'courses.id']]; <br>
 * $where = ['students.active' => 1]; <br>
 * $total = CountWithJoin($pdo, 'students', $joins, $where); <br>
 * $totalPages = (int) ceil($total / 10);
 */
function CountWithJoin(PDO $pdo, string $table, array $joins, array $conditions = []): int
{
    [$where, $params] = buildJoinWhere($conditions);
 
    $sql  = "SELECT COUNT(*)" . buildJoinFrom($table, $joins) . $where;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
 
    return (int) $stmt->fetchColumn();
}
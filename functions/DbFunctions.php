<?php

/**
 * Works with SQLite and PostgreSQL (Supabase). The driver is detected from the
 * PDO connection, so the same function calls run on both.
 *
 * What differs between the two, and how it is handled here:
 *  - Identifier quoting: backticks (MySQL style) don't work in Postgres, so we use
 *    double quotes, which both SQLite and Postgres accept.
 *  - Insert IDs: lastInsertId() needs a sequence name in Postgres, so InsertRecord()
 *    uses INSERT ... RETURNING there.
 *  - Booleans: PHP false is sent as '' by PDO, which Postgres rejects for boolean
 *    columns (and SQLite stores wrongly). Booleans are converted to 1/0 first.
 *  - LIKE: case-sensitive in Postgres, case-insensitive in SQLite. Searches now use
 *    LOWER(CAST(col AS TEXT)) LIKE ..., which behaves the same in both.
 *  - GROUP_CONCAT (SQLite) is STRING_AGG (Postgres): use sqlGroupConcat().
 */

/**
 * @param PDO $pdo - The database connection.
 * @return string - 'sqlite', 'pgsql' (Postgres / Supabase) or whatever PDO reports.
 */
function dbDriver(PDO $pdo): string
{
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
}

/**
 * @param PDO $pdo - The database connection.
 * @return bool - true when connected to Postgres (Supabase).
 */
function isPostgres(PDO $pdo): bool
{
    return dbDriver($pdo) === 'pgsql';
}

/**
 * @param mixed $value - A value you are about to bind as a parameter.
 * @return mixed - Same value, except booleans become 1 / 0.
 * Helper: PDO's execute() sends false as '' (empty string), which breaks boolean
 * columns in Postgres. '1' and '0' are accepted by Postgres booleans AND by
 * SQLite integer columns.
 */
function dbValue($value)
{
    return is_bool($value) ? (int) $value : $value;
}

/**
 * @param array $values - Parameters (any keys).
 * @return array - Same array with dbValue() applied to each value, keys kept.
 */
function dbValues(array $values): array
{
    return array_map('dbValue', $values);
}

/**
 * @param string $name - Name of the database table to search.
 * Helper: table/column names can't be bound as parameters, so we
 * validate them to prevent SQL injection.
 * Quoted with double quotes, which work in both SQLite and Postgres.
 * (Postgres treats quoted names as case-sensitive, so the name must match
 * the table/column exactly as it was created. Supabase tables are normally lowercase.)
 */
function safeIdentifier(string $name): string
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
        throw new InvalidArgumentException("Invalid identifier: $name");
    }
    return '"' . $name . '"';
}

/**
 * @param string $expr - SQL expression to join together, e.g. 'r.role'.
 *                       Write it in your code; never build it from user input.
 * @param string $separator - Text placed between the values. Default ', '.
 * @return string - GROUP_CONCAT(...) on SQLite, STRING_AGG(...) on Postgres.
 * Helper: use inside the $extra subqueries of ListRecords() so they run on both. <br>
 * 'roles' => "SELECT " . sqlGroupConcat($pdo, 'r.role') . " FROM user_roles ur ..."
 */
function sqlGroupConcat(PDO $pdo, string $expr, string $separator = ', '): string
{
    $sep = "'" . str_replace("'", "''", $separator) . "'";

    return isPostgres($pdo)
        ? "STRING_AGG(CAST($expr AS TEXT), $sep)"
        : "GROUP_CONCAT($expr, $sep)";
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
    $stmt->execute([':id' => dbValue($id)]);
 
    $row = $stmt->fetch();
    return $row ?: null;
}
 
/**
 * @param PDO $pdo - Name of the database table to search.
 * @param string $table - Name of the table you want to insert data.
 * @param array $data - associative array.
 * @param string|null $idColumn - Column to return the new id from. Default is 'id'.
 *                                Pass null for tables with no single id column
 *                                (e.g. a pivot table like user_roles); it then returns ''
 *                                on Postgres.
 * Helper: insert associative array data like this <br>
 * // INSERT: returns the new auto-increment ID (or the new uuid on Supabase) <br>
 * $id = InsertRecord($pdo, 'users', [ <br>
 * 'name'  => 'Juan Dela Cruz', <br>
 * 'email' => 'juan@example.com', <br>
 * ]) <br>
 * <br>
 * Postgres: uses INSERT ... RETURNING (lastInsertId() needs a sequence name there). <br>
 * SQLite: uses lastInsertId().
 */
function InsertRecord(PDO $pdo, string $table, array $data, ?string $idColumn = 'id'): string
{
    if (empty($data)) {
        throw new InvalidArgumentException('No data provided for insert.');
    }
 
    $columns      = array_map('safeIdentifier', array_keys($data));
    $placeholders = array_map(fn($col) => ':' . $col, array_keys($data));
 
    $sql = "INSERT INTO " . safeIdentifier($table) .
           " (" . implode(', ', $columns) . ")" .
           " VALUES (" . implode(', ', $placeholders) . ")";

    $postgres  = isPostgres($pdo);
    $returning = $postgres && $idColumn !== null;
    if ($returning) {
        $sql .= " RETURNING " . safeIdentifier($idColumn);
    }
 
    $stmt = $pdo->prepare($sql);
    $stmt->execute(dbValues($data));

    if ($returning) {
        return (string) $stmt->fetchColumn();
    }
    if ($postgres) {
        return '';   // no id column to return
    }
 
    return $pdo->lastInsertId();
}
 
/**
 * @param PDO $pdo - Name of the database table to search.
 * @param string $table - Name of the table you want to update data.
 * @param int $id - Name of the id you want to update data.
 * @param array $data - associative array.
 * @param string $idColumn - Name of the column of the id you want to update.
 * @param string $idColumn Name of the primary key or identifier column. Defaults to 'id'.
 * 
 * @return int Number of rows affected by the update operation.
 * @throws InvalidArgumentException If the provided data array is empty.
 * @throws PDOException If the query execution fails.
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
        $params[':set_' . $column] = dbValue($value);
    }
    $params[':id'] = dbValue($id);
 
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
    $stmt->execute([':id' => dbValue($id)]);
 
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
        $params[$placeholder] = dbValue($value);
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
    $stmt->execute([':value' => dbValue($value)]);
 
    return (bool) $stmt->fetchColumn();
}


/**
 * @param array $columns - Column names to search in. A row matches if ANY column contains the keyword.
 * @param string $keyword - The text to look for.
 * @return array - [ $sqlFragment, $params ]. $sqlFragment looks like
 *                 (LOWER(CAST("a" AS TEXT)) LIKE :kw0 ESCAPE '!' OR ...) and has no leading AND/WHERE.
 * Helper (internal): case-insensitive "contains" search that behaves the same on SQLite and Postgres.
 *  - LIKE is case-sensitive in Postgres, so both sides are lowercased.
 *  - CAST(... AS TEXT) lets you search number/uuid columns too (Postgres refuses LIKE on those).
 * Used by SearchRecords(), CountSearchRecords() and buildListWhere().
 */
function buildSearchClause(array $columns, string $keyword): array
{
    // Escape %, _ and the escape character itself so the text is matched literally.
    // '!' is used as the escape character because it works in both Postgres and SQLite.
    $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($keyword));

    $parts  = [];
    $params = [];
    foreach (array_values($columns) as $i => $column) {
        $placeholder          = ':kw' . $i;   // one placeholder per column
        $parts[]              = "LOWER(CAST(" . safeIdentifier($column) . " AS TEXT)) LIKE $placeholder ESCAPE '!'";
        $params[$placeholder] = '%' . $escaped . '%';
    }

    return ['(' . implode(' OR ', $parts) . ')', $params];
}

/**
 * @param PDO $pdo - The database connection from getConnection().
 * @param string $table - Name of the table you want to search.
 * @param array $columns - List of column names to search in, e.g. ['name', 'email']. A row matches if ANY column contains the keyword.
 * @param string $keyword - The text to look for (matches anywhere inside the value, case-insensitive on both SQLite and Postgres).
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
 
    [$search, $params] = buildSearchClause($columns, $keyword);
 
    $sql = "SELECT * FROM " . safeIdentifier($table) .
           " WHERE " . $search .
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
 
    [$search, $params] = buildSearchClause($columns, $keyword);
 
    $sql = "SELECT COUNT(*) FROM " . safeIdentifier($table) .
           " WHERE " . $search;
 
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
 
    return (int) $stmt->fetchColumn();
}

/* ======================================================================
 * JOIN FUNCTIONS (this is bad practice seryoso )
 * ====================================================================== */
 
/**
 * @param string $name - Column like "students.id" (table.column) or just "id".
 * @return string - Safely quoted, e.g. "students"."id"
 * Helper: same protection as safeIdentifier(), but allows one dot for table.column.
 */
function safeQualified(string $name): string
{
    return implode('.', array_map('safeIdentifier', explode('.', $name)));
}
 
/**
 * Helper (internal): builds the "FROM ... JOIN ..." part. Used by FindWithJoin() and CountWithJoin().
 * @param array $joins - [[TYPE, joinTable, leftColumn, rightColumn], ...] TYPE = INNER, LEFT or RIGHT.
 * (RIGHT JOIN needs SQLite 3.39+; Postgres has always supported it.)
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
        $params[$placeholder] = dbValue($value);
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
 * @param array $left - The main list of rows (each row is an associative array), e.g. the result of fetching all students.
 * @param array $right - The list of rows to attach to the left rows, e.g. the guardians.
 * @param array $on - The join rule as ["left_table.column" => "right_table.column"], e.g. ["students.guardian_id" => "guardians.id"]. Only the first pair is used. The right table's name is also used as the prefix for the added columns.
 * @param string $type - 'LEFT' (default) keeps left rows that have no match. 'INNER' drops them. Not case-sensitive. Any other value behaves like 'LEFT'.
 * @return array - A new list of rows. Each left row keeps all its own columns, plus every right column renamed to "righttable_column" (e.g. guardians_id, guardians_fname, guardians_lname). Left rows with no match get no extra columns (LEFT join only).
 * Helper: joins two arrays of rows in PHP, like SQL JOIN, when you can't or don't want to join in the query <br>
 * // LEFT JOIN: list every student, with guardian info if they have one <br>
 * $rows = joinRecords($students, $guardians, ["students.guardian_id" => "guardians.id"]); <br>
 * echo $rows[0]['guardians_fname']; <br>
 * // INNER JOIN: only students that have a guardian <br>
 * $rows = joinRecords($students, $guardians, ["students.guardian_id" => "guardians.id"], 'INNER');
 */
function joinRecords(array $left, array $right, array $on, string $type = 'LEFT'): array
{
    $leftCol                 = explode('.', array_key_first($on))[1];
    [$rightTable, $rightCol] = explode('.', $on[array_key_first($on)]);

    // index the right rows by their id so lookups are fast
    $index = [];
    foreach ($right as $r) {
        $index[$r[$rightCol]] = $r;
    }

    $out = [];
    foreach ($left as $l) {
        $match = $index[$l[$leftCol]] ?? null;

        if ($match === null && strtoupper($type) === 'INNER') {
            continue;
        }
        foreach ($match ?? [] as $col => $val) {
            $l[$rightTable . '_' . $col] = $val;
        }
        $out[] = $l;
    }
    return $out;
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

/**
 * Helper (internal): WHERE for a list = exact conditions AND (search in any column).
 * Example: school_year_id = 3 AND (fname LIKE '%ann%' OR lname LIKE '%ann%')
 * The search part is case-insensitive on both SQLite and Postgres (see buildSearchClause()).
 */
function buildListWhere(array $conditions, array $searchCols, string $keyword): array
{
    [$where, $params] = buildWhereClause($conditions);

    if ($keyword !== '' && $searchCols) {
        [$search, $searchParams] = buildSearchClause($searchCols, $keyword);
        $where .= ($where === '' ? ' WHERE ' : ' AND ') . $search;
        $params = array_merge($params, $searchParams);
    }

    return [$where, $params];
}

/**
 * Fetch one page of rows from a table, with filtering, searching and sorting.
 * Optionally adds computed columns (such as a user's roles) through $extra.
 *
 * Takes the same filter arguments as CountList(), so the rows returned always
 * match the total that CountList() reports. Pass the same $conditions,
 * $searchCols and $keyword to both.
 *
 * @param PDO      $pdo        Active database connection.
 * @param string   $table      Table name (validated by safeIdentifier()).
 * @param array    $conditions Exact-match filters, e.g. ['is_active' => 1].
 *                             Pass [] for no filters.
 * @param string[] $searchCols Columns the keyword is searched against,
 *                             e.g. ['fname', 'lname']. Pass [] to skip search.
 * @param string   $keyword    Search text. An empty string means no search.
 * @param int      $limit      Max rows to return (page size). Negative values become 0.
 * @param int      $offset     Rows to skip: ($page - 1) * $limit. Negative values become 0.
 * @param string   $orderBy    Column to sort by. Either a real column of $table or
 *                             an alias key from $extra. Default 'id'.
 * @param string   $direction  'ASC' or 'DESC' (case-insensitive). Anything else falls back to 'ASC'.
 * @param array    $extra      Computed columns as [alias => subquery SQL]. Each subquery
 *                             should return one value per row of $table and may refer to
 *                             the main table by name (e.g. users.id). Pass [] for none.
 *                             Use sqlGroupConcat() for "a, b, c" style values so the
 *                             subquery runs on both SQLite and Postgres.
 *                             WARNING: this is raw SQL. Write it in your code and never
 *                             build it from $_GET, $_POST or any other user input.
 *
 * @return array[] Matching rows, each including all columns of $table plus one
 *                 column per $extra alias. Empty array if nothing matches.
 *
 * @throws PDOException If the query fails.
 *
 * @example
 * // Simple: first 10 active users matching "ann", sorted by id.
 * $users = ListRecords($pdo, 'users', ['is_active' => 1], ['fname', 'lname'], 'ann', 10, 0, 'id', 'ASC');
 *
 * // With each user's roles as one text value ("Admin, Teacher"),
 * // paired with CountList() for pagination:
 * $conditions = ['is_active' => 1];
 * $searchCols = ['fname', 'lname'];
 * $keyword    = $_GET['search'] ?? '';
 * $perPage    = 10;
 * $page       = max(1, (int) ($_GET['PageNum'] ?? 1));
 * $offset     = ($page - 1) * $perPage;
 *
 * $extra = [
 *     'roles' => "SELECT " . sqlGroupConcat($pdo, 'r.role') . "
 *                 FROM user_roles ur
 *                 JOIN roles r ON r.role_id = ur.role
 *                 WHERE ur.user_id = users.id"
 * ];
 *
 * $total      = CountList($pdo, 'users', $conditions, $searchCols, $keyword);
 * $totalPages = (int) ceil($total / $perPage);
 * $rows       = ListRecords($pdo, 'users', $conditions, $searchCols, $keyword,
 *                           $perPage, $offset, 'roles', 'ASC', $extra);
 *
 * // In the table: echo htmlspecialchars($row['roles'] ?? '');
 */
function ListRecords(PDO $pdo, string $table, array $conditions, array $searchCols, string $keyword,
                     int $limit, int $offset, string $orderBy = 'id', string $direction = 'ASC',
                     array $extra = []): array
{
    $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
    [$where, $params] = buildListWhere($conditions, $searchCols, $keyword);

    // main table columns, plus any extra computed columns
    $select = safeIdentifier($table) . '.*';
    foreach ($extra as $alias => $sql) {
        $select .= ", ($sql) AS " . safeIdentifier($alias);
    }

    // sort by an extra column (alias) or by a real column of the main table
    $orderCol = array_key_exists($orderBy, $extra)
        ? safeIdentifier($orderBy)
        : safeIdentifier($table) . '.' . safeIdentifier($orderBy);

    $sql = "SELECT $select FROM " . safeIdentifier($table) . $where .
           " ORDER BY $orderCol $direction" .
           " LIMIT " . max(0, $limit) . " OFFSET " . max(0, $offset);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
/**
 * Turn foreign keys into SELECT columns + LEFT JOINs.
 * $fks = ['role_id' => 'roles.name']  means: users.role_id -> roles.id, show roles.name
 * The joined value comes back as $row['role_name'] (fk column minus "_id", plus "_name").
 * Assumes the referenced table's primary key is "id".
 *
 * @return array [selectSql, joinSql, aliasNames]
 */
function buildFkJoins(string $table, array $fks): array
{
    $select  = [safeIdentifier($table) . '.*'];
    $joins   = '';
    $aliases = [];

    foreach ($fks as $fkCol => $target) {
        [$refTable, $refCol] = explode('.', $target);
        $join  = 'j_' . $fkCol;                                  // unique alias per join
        $label = preg_replace('/_id$/', '', $fkCol) . '_name';   // role_id -> role_name

        $select[]  = safeIdentifier($join) . '.' . safeIdentifier($refCol) . ' AS ' . safeIdentifier($label);
        $joins    .= ' LEFT JOIN ' . safeIdentifier($refTable) . ' AS ' . safeIdentifier($join)
                   . ' ON ' . safeIdentifier($join) . '.' . safeIdentifier('id') . ' = '
                   . safeIdentifier($table) . '.' . safeIdentifier($fkCol);
        $aliases[] = $label;
    }
    return [implode(', ', $select), $joins, $aliases];
}
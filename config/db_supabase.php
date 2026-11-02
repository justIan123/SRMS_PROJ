<?php
// // C:\xampp\htdocs\1_SRMS\db_supabase.php
// require_once __DIR__ . "/bootstrap.php";

// define('SUPABASE_URL', $_ENV['SUPABASE_URL']);
// define('SUPABASE_KEY', $_ENV['SUPABASE_KEY']);

// /**
//  * Flexible native cURL wrapper for Supabase PostgREST API
//  * 
//  * @param string $path_or_query Endpoint path and parameters (e.g., "users?select=*,profiles(*)" or "rpc/my_function")
//  * @param string $method HTTP method: 'GET', 'POST', 'PATCH', 'PUT', 'DELETE'
//  * @param array|null $payload Data array for POST/PATCH/PUT requests
//  * @param array $headers Optional additional custom headers (e.g., Prefer headers)
//  * @return mixed Parsed JSON response array or null on failure
//  */
// function supabase_query($path_or_query, $method = 'GET', $payload = null, $headers = []) {
//     // Sanitize path lead slash if provided
//     $endpoint = ltrim($path_or_query, '/');
//     $url = SUPABASE_URL . '/rest/v1/' . $endpoint;

//     $ch = curl_init($url);

//     $default_headers = [
//         'apikey: ' . SUPABASE_KEY,
//         'Authorization: Bearer ' . SUPABASE_KEY,
//         'Content-Type: application/json'
//     ];

//     // Merge default headers with custom user headers
//     $request_headers = array_merge($default_headers, $headers);

//     $method = strtoupper($method);
//     curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

//     if ($payload !== null && in_array($method, ['POST', 'PATCH', 'PUT'])) {
//         curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
//     }

//     curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);

//     $response = curl_exec($ch);
//     $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//     curl_close($ch);

//     // if ($httpCode >= 200 && $httpCode < 300) {
//     //     return json_decode($response, true);
//     // } else {
//     //     error_log("Supabase cURL error [{$httpCode}] ({$method} {$url}): {$response}");
//     //     return null;
//     // }

//     if ($httpCode >= 200 && $httpCode < 300) {
//         return json_decode($response, true);
//     } else {
//         // TEMPORARY DEBUG LINE: Remove this once your app is live
//         echo "Supabase Error [{$httpCode}] URL: {$url} Response: {$response}";
        
//         error_log("Supabase cURL error [{$httpCode}] ({$method} {$url}): {$response}");
//         return null;
//     }
// }


// require_once __DIR__ . "/bootstrap.php";

// $host = $_ENV['SUPABASE_HOST'];
// $db   = $_ENV['SUPABASE_DB'];
// $user = $_ENV['SUPABASE_USER'];
// $pass = $_ENV['SUPABASE_PASS'];
// $port = $_ENV['SUPABASE_PORT'];

// $dsn = "pgsql:host=$host;port=$port;dbname=$db";

// try {
//     $pdo = new PDO($dsn, $user, $pass, [
//         PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
//         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
//     ]);
// } catch (PDOException $e) {
//     die("PostgreSQL connection failed: " . $e->getMessage());
// }


// SUPABASE SUPPORTED BY PDO LIBRARY
// C:\xampp\htdocs\1_SRMS\db_supabase.php
//
// Gives every page a working $pdo again, without the pgsql driver (InfinityFree has none).
//
// How it works: $pdo is a small PDO look-alike. prepare()/execute()/fetch()/fetchAll()/
// fetchColumn()/rowCount()/query()/exec() behave as before, but each statement is sent to
// Supabase over cURL, to the SQL function public.exec_sql() (see supabase_exec_sql.sql,
// run it once in the Supabase SQL editor). Pages and handlers need no changes.
//
// Needs in .env (or the server environment):
//   SUPABASE_URL = https://<project>.supabase.co
//   SUPABASE_KEY = the service_role / secret key (server side only, never in browser code)
//
// Written to run on PHP 7.4 and 8.x.

if (is_file(__DIR__ . "/bootstrap.php")) {
    require_once __DIR__ . "/bootstrap.php";
}

function supabase_env($name) {
    if (isset($_ENV[$name]) && $_ENV[$name] !== '') {
        return $_ENV[$name];
    }
    if (isset($_SERVER[$name]) && $_SERVER[$name] !== '') {
        return $_SERVER[$name];
    }
    $v = getenv($name);
    return ($v !== false && $v !== '') ? $v : '';
}

if (!defined('SUPABASE_URL')) {
    define('SUPABASE_URL', rtrim(trim((string)supabase_env('SUPABASE_URL')), '/'));
}
if (!defined('SUPABASE_KEY')) {
    define('SUPABASE_KEY', trim((string)supabase_env('SUPABASE_KEY')));
}
// supabase_query() prints its errors while this is true (the old "temporary debug line")
if (!defined('SUPABASE_DEBUG')) {
    define('SUPABASE_DEBUG', true);
}

/**
 * One HTTP call to PostgREST. Never throws.
 *
 * @return array ['code' => HTTP status (0 = no answer), 'body' => raw text, 'error' => cURL/config error or '']
 */
function supabase_http($method, $endpoint, $payload = null, $headers = []) {
    static $ch = null;   // one handle per request, so the HTTPS connection is reused

    if (SUPABASE_URL === '' || SUPABASE_KEY === '') {
        return ['code' => 0, 'body' => '', 'error' => 'SUPABASE_URL / SUPABASE_KEY are empty. Check .env and bootstrap.php.'];
    }

    $url = SUPABASE_URL . '/rest/v1/' . ltrim($endpoint, '/');

    if ($ch === null) {
        $ch = curl_init();
    } else {
        curl_reset($ch);
    }

    $request_headers = [
        'apikey: ' . SUPABASE_KEY,
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    // legacy keys are JWTs and go in Authorization too; the newer sb_secret_ keys go in apikey only
    if (strpos(SUPABASE_KEY, 'eyJ') === 0) {
        $request_headers[] = 'Authorization: Bearer ' . SUPABASE_KEY;
    }
    $request_headers = array_merge($request_headers, $headers);

    $method = strtoupper($method);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);

    if ($payload !== null && in_array($method, ['POST', 'PATCH', 'PUT'], true)) {
        $json = is_string($payload) ? $payload : json_encode($payload);
        if ($json === false) {
            return ['code' => 0, 'body' => '', 'error' => 'Could not encode the data as JSON (invalid UTF-8?): ' . json_last_error_msg()];
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    }

    $body  = curl_exec($ch);
    $error = ($body === false) ? curl_error($ch) : '';

    return [
        'code'  => (int)curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'body'  => ($body === false) ? '' : $body,
        'error' => $error,
    ];
}

/**
 * Flexible native cURL wrapper for the Supabase PostgREST API (same as before).
 *
 * @param string     $path_or_query e.g. "users?select=*" or "rpc/my_function"
 * @param string     $method        GET, POST, PATCH, PUT, DELETE
 * @param array|null $payload       data for POST/PATCH/PUT
 * @param array      $headers       extra headers (e.g. Prefer)
 * @return mixed Parsed JSON, or null on failure
 */
function supabase_query($path_or_query, $method = 'GET', $payload = null, $headers = []) {
    $r = supabase_http($method, $path_or_query, $payload, $headers);

    if ($r['error'] === '' && $r['code'] >= 200 && $r['code'] < 300) {
        return $r['body'] === '' ? [] : json_decode($r['body'], true);
    }

    $what = $r['error'] !== '' ? $r['error'] : $r['body'];
    if (SUPABASE_DEBUG) {
        echo "Supabase Error [{$r['code']}] {$method} {$path_or_query} Response: {$what}";
    }
    error_log("Supabase cURL error [{$r['code']}] ({$method} {$path_or_query}): {$what}");
    return null;
}


// ---------------------------------------------------------------------------------------
// $pdo
// ---------------------------------------------------------------------------------------

/** Builds a PDOException the way pdo_pgsql would (message format, errorInfo, SQLSTATE code). */
function supabase_pdo_exception($message, $sqlstate = 'HY000', $detail = '') {
    $labels = [
        '23505' => 'Unique violation',
        '23503' => 'Foreign key violation',
        '23502' => 'Not null violation',
        '23514' => 'Check violation',
        '22P02' => 'Invalid text representation',
        '42601' => 'Syntax error',
        '42P01' => 'Undefined table',
        '42703' => 'Undefined column',
        '42501' => 'Insufficient privilege',
    ];
    $label = isset($labels[$sqlstate]) ? $labels[$sqlstate] : 'General error';

    $text = 'ERROR:  ' . $message . ($detail !== '' ? "\nDETAIL:  " . $detail : '');
    $e = new PDOException("SQLSTATE[$sqlstate]: $label: 7 $text");
    $e->errorInfo = [$sqlstate, 7, $text];

    // PDOException::getCode() is the SQLSTATE string, like with a real PDO
    $p = new ReflectionProperty('Exception', 'code');
    $p->setAccessible(true);
    $p->setValue($e, $sqlstate);

    return $e;
}

/** A value as a SQL literal (same rules as libpq's PQescapeLiteral). Everything is sent as text, the column type decides. */
function supabase_literal($v) {
    if ($v === null) {
        return 'NULL';
    }
    if ($v === true) {
        $v = '1';    // flags are bigint 1/0 in this schema
    } elseif ($v === false) {
        $v = '0';
    } else {
        $v = (string)$v;
    }
    if (strpos($v, "\0") !== false) {
        throw supabase_pdo_exception('A value contains a NUL byte', '22021');
    }
    $v = str_replace("'", "''", $v);
    if (strpos($v, '\\') !== false) {
        return "E'" . str_replace('\\', '\\\\', $v) . "'";
    }
    return "'" . $v . "'";
}

class SupabaseStatement extends PDOStatement {
    private $parts;          // SQL cut into text / placeholder pieces
    private $returnsRows;
    private $bound = [];     // bindValue()/bindParam() values: ['name' => ..] or [position => ..]
    private $refs  = [];     // bindParam() variables, read at execute()
    private $rows  = [];
    private $count = 0;
    private $pos   = 0;
    private $fetchMode;

    public function __construct($sql, $defaultFetchMode) {
        $this->queryString = $sql;
        $this->fetchMode   = $defaultFetchMode;

        // Cut the SQL into pieces. Even indexes are plain SQL, odd indexes are tokens.
        // Strings, "identifiers" and comments are tokens that are left alone, so a ':' or '?'
        // inside them is not a placeholder. '::' (a cast) is left alone too.
        $this->parts = preg_split(
            '~(\'(?:[^\']|\'\')*\'|"(?:[^"]|"")*"|--[^\n]*|/\*.*?\*/|::|:[A-Za-z_][A-Za-z0-9_]*|\?)~s',
            $sql, -1, PREG_SPLIT_DELIM_CAPTURE
        );

        // Does the statement hand rows back? (judged on the plain SQL only, not on text inside strings)
        $plain = '';
        foreach ($this->parts as $i => $p) {
            if ($i % 2 === 0) {
                $plain .= $p;
            }
        }
        $this->returnsRows = (bool)preg_match('/^\s*(select|with|values|table|show)\b/i', $plain)
                          || (bool)preg_match('/\breturning\b/i', $plain);
    }

    // ---- binding -------------------------------------------------------------------

    private function key($param) {
        return is_int($param) ? $param - 1 : ltrim((string)$param, ':');   // positions are 1-based here, 0-based inside
    }

    #[\ReturnTypeWillChange]
    public function bindValue($param, $value, $type = PDO::PARAM_STR) {
        $k = $this->key($param);
        $this->bound[$k] = $value;
        unset($this->refs[$k]);
        return true;
    }

    #[\ReturnTypeWillChange]
    public function bindParam($param, &$var, $type = PDO::PARAM_STR, $maxLength = 0, $driverOptions = null) {
        $k = $this->key($param);
        $this->refs[$k] = &$var;
        return true;
    }

    // ---- running -------------------------------------------------------------------

    #[\ReturnTypeWillChange]
    public function execute($params = null) {
        if ($params !== null) {
            $values = [];
            foreach ($params as $k => $v) {
                $values[is_int($k) ? $k : ltrim((string)$k, ':')] = $v;
            }
        } else {
            $values = $this->bound;
            foreach ($this->refs as $k => $ref) {
                $values[$k] = $ref;
            }
        }

        // put the values in, left to right
        $sql = '';
        $n   = 0;
        foreach ($this->parts as $i => $p) {
            if ($i % 2 === 0) {
                $sql .= $p;
            } elseif ($p === '?') {
                if (!array_key_exists($n, $values)) {
                    throw supabase_pdo_exception('Invalid parameter number: parameter was not defined', 'HY093');
                }
                $sql .= supabase_literal($values[$n++]);
            } elseif ($p[0] === ':' && isset($p[1]) && $p[1] !== ':') {
                $name = substr($p, 1);
                if (!array_key_exists($name, $values)) {
                    throw supabase_pdo_exception('Invalid parameter number: parameter was not defined', 'HY093');
                }
                $sql .= supabase_literal($values[$name]);
            } else {
                $sql .= $p;   // string, identifier, comment, '::'
            }
        }

        $result = supabase_run_sql($sql, $this->returnsRows);
        $this->rows  = $result['rows'];
        $this->count = $result['count'];
        $this->pos   = 0;
        return true;
    }

    // ---- reading -------------------------------------------------------------------

    private function shape($row, $mode) {
        switch ($mode & 0xFFFF) {
            case PDO::FETCH_NUM:
                return array_values($row);
            case PDO::FETCH_BOTH:
                return $row + array_values($row);
            case PDO::FETCH_OBJ:
            case PDO::FETCH_CLASS:
                return (object)$row;
            case PDO::FETCH_COLUMN:
                return reset($row);
            default:   // FETCH_ASSOC
                return $row;
        }
    }

    #[\ReturnTypeWillChange]
    public function fetch($mode = null, $cursorOrientation = PDO::FETCH_ORI_NEXT, $cursorOffset = 0) {
        if ($this->pos >= count($this->rows)) {
            return false;
        }
        $row = $this->rows[$this->pos++];
        return $this->shape($row, $mode === null ? $this->fetchMode : $mode);
    }

    #[\ReturnTypeWillChange]
    public function fetchAll($mode = null, ...$args) {
        $mode = $mode === null ? $this->fetchMode : $mode;
        $base = $mode & 0xFFFF;
        $out  = [];

        while ($this->pos < count($this->rows)) {
            $row = $this->rows[$this->pos++];

            if ($base === PDO::FETCH_COLUMN) {
                $col   = isset($args[0]) ? (int)$args[0] : 0;
                $out[] = array_values($row)[$col] ?? null;
            } elseif ($base === PDO::FETCH_KEY_PAIR) {
                $vals = array_values($row);
                $out[$vals[0]] = isset($vals[1]) ? $vals[1] : null;
            } else {
                $out[] = $this->shape($row, $mode);
            }
        }
        return $out;
    }

    #[\ReturnTypeWillChange]
    public function fetchColumn($column = 0) {
        if ($this->pos >= count($this->rows)) {
            return false;
        }
        $vals = array_values($this->rows[$this->pos++]);
        return array_key_exists($column, $vals) ? $vals[$column] : false;
    }

    #[\ReturnTypeWillChange]
    public function fetchObject($class = 'stdClass', $constructorArgs = []) {
        if ($this->pos >= count($this->rows)) {
            return false;
        }
        $row = $this->rows[$this->pos++];
        $obj = new $class(...$constructorArgs);
        foreach ($row as $k => $v) {
            $obj->$k = $v;
        }
        return $obj;
    }

    // (PHP 8 declares this return type for real, not as a tentative one, so it has to be written out)
    public function getIterator(): Iterator {
        return new ArrayIterator($this->fetchAll());
    }

    #[\ReturnTypeWillChange]
    public function setFetchMode($mode, ...$args) {
        $this->fetchMode = $mode;
        return true;
    }

    #[\ReturnTypeWillChange]
    public function rowCount() {
        return $this->count;
    }

    #[\ReturnTypeWillChange]
    public function columnCount() {
        return $this->rows ? count($this->rows[0]) : 0;
    }

    #[\ReturnTypeWillChange]
    public function closeCursor() {
        $this->rows = [];
        $this->pos  = 0;
        return true;
    }

    #[\ReturnTypeWillChange]
    public function nextRowset() {
        return false;
    }

    #[\ReturnTypeWillChange]
    public function errorCode() {
        return '00000';
    }

    #[\ReturnTypeWillChange]
    public function errorInfo() {
        return ['00000', null, null];
    }
}

/** Sends one finished SQL statement to Supabase. Returns ['rows' => [...], 'count' => n] or throws PDOException. */
function supabase_run_sql($sql, $returnsRows) {
    $r = supabase_http('POST', 'rpc/exec_sql', ['query' => $sql, 'returns_rows' => (bool)$returnsRows]);

    if ($r['error'] !== '') {
        throw supabase_pdo_exception('Could not reach Supabase: ' . $r['error'], '08006');
    }

    $data = json_decode($r['body'], true);

    if ($r['code'] < 200 || $r['code'] >= 300) {
        $msg    = is_array($data) && isset($data['message']) ? $data['message'] : trim($r['body']);
        $code   = is_array($data) && isset($data['code']) ? (string)$data['code'] : '';
        $detail = is_array($data) && isset($data['details']) ? (string)$data['details'] : '';

        if ($code === 'PGRST202' || $code === '42883') {
            $msg = 'The function exec_sql does not exist in Supabase. Run supabase_exec_sql.sql in the SQL editor. (' . $msg . ')';
        } elseif ($r['code'] === 401 || $code === '42501' || $r['code'] === 403) {
            $msg = 'Supabase refused the key. SUPABASE_KEY must be the service_role / secret key. (' . $msg . ')';
        }

        // real SQL errors carry a 5-character SQLSTATE; PostgREST/gateway errors do not
        $sqlstate = preg_match('/^[0-9A-Z]{5}$/', $code) && strpos($code, 'PGRST') !== 0 ? $code : 'HY000';
        throw supabase_pdo_exception($msg === '' ? 'HTTP ' . $r['code'] : $msg, $sqlstate, $detail);
    }

    if (!is_array($data) || !isset($data['rows']) || !is_array($data['rows'])) {
        throw supabase_pdo_exception('Unexpected answer from Supabase: ' . substr($r['body'], 0, 200), 'HY000');
    }

    // Postgres timestamps come back as "2026-11-02T20:13:00"; give them the "2026-11-02 20:13:00" form PDO gave
    foreach ($data['rows'] as &$row) {
        foreach ($row as &$v) {
            if (is_string($v) && isset($v[10]) && $v[10] === 'T'
                && preg_match('/^\d{4}-\d\d-\d\d T\d\d:\d\d:\d\d/x', $v)) {
                $v[10] = ' ';
            }
        }
        unset($v);
    }
    unset($row);

    return ['rows' => $data['rows'], 'count' => (int)($data['count'] ?? count($data['rows']))];
}

/**
 * The $pdo object. Extends PDO only so that type hints like function f(PDO $pdo) still accept it;
 * the PDO parent is never used (there is no driver), every method is replaced.
 *
 * Transactions: each statement is its own request to Supabase, so beginTransaction() / commit() /
 * rollBack() keep track of the state (inTransaction() works) but cannot undo earlier statements.
 */
class SupabasePdo extends PDO {
    private $attributes = [];
    private $inTransaction = false;

    public function __construct() {
        // intentionally not calling parent::__construct()
        $this->attributes[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;
        $this->attributes[PDO::ATTR_ERRMODE]            = PDO::ERRMODE_EXCEPTION;
    }

    #[\ReturnTypeWillChange]
    public function prepare($query, $options = []) {
        return new SupabaseStatement($query, $this->attributes[PDO::ATTR_DEFAULT_FETCH_MODE]);
    }

    #[\ReturnTypeWillChange]
    public function query($query, $fetchMode = null, ...$fetchModeArgs) {
        $stmt = $this->prepare($query);
        $stmt->execute();
        if ($fetchMode !== null) {
            $stmt->setFetchMode($fetchMode);
        }
        return $stmt;
    }

    #[\ReturnTypeWillChange]
    public function exec($statement) {
        $stmt = $this->prepare($statement);
        $stmt->execute();
        return $stmt->rowCount();
    }

    #[\ReturnTypeWillChange]
    public function quote($string, $type = PDO::PARAM_STR) {
        return supabase_literal($string);
    }

    #[\ReturnTypeWillChange]
    public function lastInsertId($name = null) {
        // every request may use a different database connection, so lastval() / currval() can't be trusted
        throw supabase_pdo_exception('lastInsertId() does not work over Supabase HTTP. End the INSERT with RETURNING id and read it with fetchColumn().', 'IM001');
    }

    #[\ReturnTypeWillChange]
    public function beginTransaction() {
        if ($this->inTransaction) {
            throw new PDOException('There is already an active transaction');
        }
        $this->inTransaction = true;
        return true;
    }

    #[\ReturnTypeWillChange]
    public function commit() {
        if (!$this->inTransaction) {
            throw new PDOException('There is no active transaction');
        }
        $this->inTransaction = false;
        return true;
    }

    #[\ReturnTypeWillChange]
    public function rollBack() {
        if (!$this->inTransaction) {
            throw new PDOException('There is no active transaction');
        }
        $this->inTransaction = false;
        return true;
    }

    #[\ReturnTypeWillChange]
    public function inTransaction() {
        return $this->inTransaction;
    }

    #[\ReturnTypeWillChange]
    public function setAttribute($attribute, $value) {
        $this->attributes[$attribute] = $value;
        return true;
    }

    #[\ReturnTypeWillChange]
    public function getAttribute($attribute) {
        if ($attribute === PDO::ATTR_DRIVER_NAME) {
            return 'pgsql';
        }
        return isset($this->attributes[$attribute]) ? $this->attributes[$attribute] : null;
    }

    #[\ReturnTypeWillChange]
    public function errorCode() {
        return '00000';
    }

    #[\ReturnTypeWillChange]
    public function errorInfo() {
        return ['00000', null, null];
    }
}

$pdo = new SupabasePdo();
$GLOBALS['pdo'] = $pdo;   // still $pdo even if this file is first included from inside a function

?>
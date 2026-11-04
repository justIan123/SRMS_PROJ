<?php
// db_check.php - opens in the browser and shows whether this server can reach Supabase.
// Put it next to db_supabase.php (or in a folder beside config/) and open it on InfinityFree,
// phpdesktop and HopWeb. Shows no keys. DELETE IT when you are done: it tells outsiders
// about your server.

header('Content-Type: text/plain; charset=utf-8');

$file = null;
foreach ([__DIR__ . '/db_supabase.php', __DIR__ . '/config/db_supabase.php', __DIR__ . '/../config/db_supabase.php'] as $f) {
    if (is_file($f)) {
        $file = $f;
        break;
    }
}
if (!$file) {
    exit("db_supabase.php not found. Put db_check.php next to it.\n");
}
require_once $file;

$line = function ($label, $value) {
    echo str_pad($label, 20) . $value . "\n";
};

echo "== This server ==\n";
$line('PHP', PHP_VERSION . ' (' . PHP_SAPI . ', ' . PHP_OS_FAMILY . ')');
if (!function_exists('curl_version')) {
    exit("cURL extension is not loaded. Nothing can reach Supabase from here.\n");
}
$cv = curl_version();
$line('cURL', $cv['version'] . ', TLS: ' . ($cv['ssl_version'] ?? '?'));
$line('php.ini curl.cainfo', ini_get('curl.cainfo') ?: '(not set)');
$line('php.ini openssl.cafile', ini_get('openssl.cafile') ?: '(not set)');
$line('open_basedir', ini_get('open_basedir') ?: '(not set)');

echo "\n== Settings ==\n";
$line('db_supabase.php', $file);
$line('SUPABASE_URL', SUPABASE_URL !== '' ? parse_url(SUPABASE_URL, PHP_URL_SCHEME) . '://' . parse_url(SUPABASE_URL, PHP_URL_HOST) : '(EMPTY: check .env / bootstrap.php)');
$k = SUPABASE_KEY;
$line('SUPABASE_KEY', $k === '' ? '(EMPTY: check .env / bootstrap.php)' : (strpos($k, 'eyJ') === 0 ? 'set (legacy JWT key)' : 'set (' . (strpos($k, 'sb_') === 0 ? 'new sb_ key' : 'unknown format') . ')') . ', ' . strlen($k) . ' characters');

echo "\n== Certificate bundles that will be tried, in order ==\n";
if (function_exists('supabase_ca_attempts')) {
    foreach (supabase_ca_attempts() as $i => $a) {
        echo '  ' . ($i + 1) . '. ' . supabase_ca_describe($a) . "\n";
    }
} else {
    echo "  (this db_supabase.php is an older version without the certificate fallback)\n";
}

echo "\n== Test: select 1 through exec_sql ==\n";
if (!isset($pdo)) {
    exit("\$pdo does not exist: this db_supabase.php is the plain REST version, so there is no SQL test.\n");
}
$t0 = microtime(true);
try {
    $row = $pdo->query('SELECT 1 AS ok')->fetch();
    $ms  = round((microtime(true) - $t0) * 1000);
    $line('Result', ($row && (int)$row['ok'] === 1 ? 'OK' : 'unexpected answer: ' . json_encode($row)) . " ({$ms} ms)");
} catch (PDOException $e) {
    $line('Result', 'FAILED');
    echo str_replace("\n", "\n" . str_repeat(' ', 20), $e->getMessage()) . "\n";
}
if (!empty($GLOBALS['supabase_ca_chosen'])) {
    $line('Certificates used', supabase_ca_describe($GLOBALS['supabase_ca_chosen']));
}

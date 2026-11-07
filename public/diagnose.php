<?php
// Temporary server diagnostics. Delete this file when finished.
$KEY = 'add14338eb97be7a6fa3f115';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$given = isset($_GET['key']) ? (string) $_GET['key'] : '';
$allowed = function_exists('hash_equals') ? hash_equals($KEY, $given) : $KEY === $given;
if (!$allowed) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
ob_start();
$baseLevel = ob_get_level();

$problems = 0;
$live = false;
$path = isset($_GET['path']) && strpos($_GET['path'], '/api/') === 0 ? $_GET['path'] : '/api/menu';

function say($t = '') { echo $t, "\n"; }
function head($t) { say(); say('== ' . $t . ' =='); }
function ok($t) { say('  ok    ' . $t); }
function warn($t) { say('  warn  ' . $t); }
function note($t) { say('        ' . $t); }
function bad($t, $fix = '')
{
    global $problems;
    $problems++;
    say('  FAIL  ' . $t);
    if ($fix !== '') say('        -> ' . $fix);
}

function is_list_array($a) { return count($a) === 0 || array_keys($a) === range(0, count($a) - 1); }

function flatten($arr, $prefix = '')
{
    $rows = array();
    foreach ($arr as $k => $v) {
        $name = $prefix === '' ? (string) $k : $prefix . '.' . $k;
        if (is_array($v) && !is_list_array($v)) $rows = array_merge($rows, flatten($v, $name));
        else $rows[$name] = $v;
    }
    return $rows;
}

function show($name, $v)
{
    if (preg_match('/pass|secret|key|hash|token/i', $name)) return ($v === '' || $v === null) ? '(empty)' : '(set, ' . strlen((string) $v) . ' chars)';
    if (is_array($v)) return json_encode($v);
    if (is_bool($v)) return $v ? 'true' : 'false';
    return '"' . $v . '"';
}

function lint($file)
{
    if (!defined('TOKEN_PARSE')) return null;
    try {
        token_get_all(file_get_contents($file), TOKEN_PARSE);
    } catch (Throwable $e) {
        return $e->getMessage() . ' on line ' . $e->getLine();
    } catch (Exception $e) {
        return $e->getMessage() . ' on line ' . $e->getLine();
    }
    return null;
}

function php_files($dir)
{
    $found = array();
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $dir . '/' . $f;
        if (is_dir($p)) $found = array_merge($found, php_files($p));
        elseif (substr($f, -4) === '.php') $found[] = $p;
    }
    return $found;
}

function tail($file, $bytes = 6000)
{
    $size = filesize($file);
    $text = (string) file_get_contents($file, false, null, max(0, $size - $bytes));
    $lines = explode("\n", trim($text));
    return array_slice($lines, -25);
}

function perms($p) { return substr(sprintf('%o', fileperms($p)), -4); }

function finish()
{
    global $problems, $live, $path, $baseLevel;
    $err = error_get_last();
    $app = '';
    while (ob_get_level() > $baseLevel) $app = ob_get_clean() . $app;
    if ($live) {
        $status = http_response_code();
        head('Live request: GET ' . $path);
        say('  The app answered with HTTP ' . ($status ? $status : 200));
        say('  Response body (first 2000 characters):');
        say();
        say(substr($app, 0, 2000) === '' ? '  (empty body: PHP stopped before printing anything)' : substr($app, 0, 2000));
        if ($err && in_array($err['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR), true)) {
            $problems++;
            say();
            say('  FAIL  PHP stopped with a fatal error:');
            say('        ' . $err['message']);
            say('        in ' . $err['file'] . ' on line ' . $err['line']);
        }
    }
    head('Summary');
    say($problems === 0 ? '  No problems found by these checks.' : '  ' . $problems . ' problem(s) found. Fix the FAIL lines above, top one first.');
    say('  Delete public/diagnose.php from the server when you are done.');
    if (!headers_sent()) {
        header_remove('Content-Type');
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(200);
    }
}
register_shutdown_function('finish');

say('McRelish server diagnostics  ' . gmdate('Y-m-d H:i:s') . ' UTC');

head('1. Server and PHP');
say('  PHP version      ' . PHP_VERSION . ' (' . PHP_SAPI . ')');
say('  Operating system ' . PHP_OS);
say('  Document root    ' . (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '(unknown)'));
say('  This file        ' . __FILE__);
say('  memory_limit     ' . ini_get('memory_limit'));
say('  error_log        ' . (ini_get('error_log') ? ini_get('error_log') : '(not set)'));
say('  disable_functions ' . (ini_get('disable_functions') ? ini_get('disable_functions') : '(none)'));
if (version_compare(PHP_VERSION, '8.1.0', '>=')) ok('PHP 8.1 or newer');
else bad('PHP ' . PHP_VERSION . ' is too old (8.1 or newer needed)', 'In cPanel open "Select PHP Version" or "MultiPHP Manager" for api.mcrelish.com and choose PHP 8.1, 8.2 or 8.3.');
foreach (array('pdo_mysql', 'curl', 'mbstring', 'fileinfo', 'openssl', 'json') as $ext) {
    if (extension_loaded($ext)) ok('extension ' . $ext);
    else bad('extension ' . $ext . ' is missing', 'In cPanel "Select PHP Version" tick ' . $ext . ' and save.');
}

head('2. Files and folders');
$root = null;
foreach (array(dirname(__DIR__), __DIR__, dirname(dirname(__DIR__))) as $c) {
    if (is_file($c . '/src/bootstrap.php')) { $root = $c; break; }
}
if ($root === null) {
    bad('Could not find src/bootstrap.php near this file', 'Upload the whole backend folder (src, config, lib, public) so that "src" sits next to the folder holding this file.');
    exit;
}
ok('Backend folder found: ' . $root);
foreach (array('src/bootstrap.php', 'public/index.php', 'public/.htaccess', 'config/config.php', 'lib/PHPMailer', 'database/schema.sql') as $rel) {
    if (file_exists($root . '/' . $rel)) ok($rel . ' exists');
    else bad($rel . ' is missing', $rel === 'config/config.php' ? 'Copy config/config.example.php to config/config.php on the server and fill in the real values.' : 'Upload it again.');
}
foreach (array('Controllers', 'Core', 'Repositories', 'Services') as $dirName) {
    $names = is_dir($root . '/src') ? scandir($root . '/src') : array();
    if (in_array($dirName, $names, true)) ok('src/' . $dirName . ' (exact capitals)');
    else bad('src/' . $dirName . ' is missing or has the wrong capital letters', 'Linux is case-sensitive. Rename the folder to exactly "' . $dirName . '".');
}
foreach (array($root . '/public/index.php', $root . '/public', $root . '/config/config.php') as $p) {
    if (!file_exists($p)) continue;
    $mode = fileperms($p);
    if (($mode & 0022) !== 0) warn(substr($p, strlen($root)) . ' is writable by others (' . perms($p) . '); some hosts then answer 500. Use 755 for folders, 644 for files.');
    else ok(substr($p, strlen($root)) . ' permissions ' . perms($p));
}
if (is_file($root . '/public/.htaccess')) {
    say();
    say('  public/.htaccess contains:');
    foreach (explode("\n", trim(file_get_contents($root . '/public/.htaccess'))) as $l) say('    ' . $l);
}
foreach (array('storage', 'public/uploads', 'public/uploads/menu', 'public/uploads/promo') as $rel) {
    $p = $root . '/' . $rel;
    if (is_dir($p) && is_writable($p)) ok($rel . ' is writable');
    else bad($rel . ' is missing or not writable', 'Create the folder and set its permissions to 755 (or 775).');
}

head('3. PHP syntax of every app file (on this server\'s PHP version)');
if (!defined('TOKEN_PARSE')) {
    warn('Syntax check needs PHP 7 or newer; upgrade PHP first.');
} else {
    $files = array_merge(php_files($root . '/src'), array($root . '/public/index.php'));
    if (is_file($root . '/config/config.php')) $files[] = $root . '/config/config.php';
    $broken = 0;
    foreach ($files as $f) {
        $msg = lint($f);
        if ($msg !== null) { $broken++; bad(substr($f, strlen($root) + 1) . ': ' . $msg, 'This file is damaged or was uploaded incompletely. Upload it again in binary mode.'); }
    }
    if ($broken === 0) ok(count($files) . ' files parse cleanly');
}

head('4. config/config.php');
$cfg = null;
$configFile = $root . '/config/config.php';
if (is_file($configFile)) {
    try {
        $cfg = include $configFile;
    } catch (Throwable $e) {
        bad('config.php could not be loaded: ' . $e->getMessage() . ' (line ' . $e->getLine() . ')', 'Fix the syntax error on that line.');
    } catch (Exception $e) {
        bad('config.php could not be loaded: ' . $e->getMessage(), 'Fix the syntax error.');
    }
    if ($cfg !== null && !is_array($cfg)) {
        bad('config.php must start with "return [" and give back a list of settings', 'Compare it with config.example.php.');
        $cfg = null;
    }
}
if (is_array($cfg)) {
    foreach (flatten($cfg) as $name => $v) say('  ' . str_pad($name, 28) . ' ' . show($name, $v));
    say();
    $env = isset($cfg['app']['env']) ? $cfg['app']['env'] : '';
    if ($env === 'production') ok('app.env is production');
    else warn('app.env is "' . $env . '"; use "production" on the live server.');
    if (isset($cfg['db']['host']) && $cfg['db']['host'] !== trim($cfg['db']['host'])) bad('db.host has spaces around it', 'Remove the spaces so it reads exactly "localhost".');
    if (empty($cfg['db']['name']) || empty($cfg['db']['user'])) bad('db.name or db.user is empty', 'cPanel names look like "cpaneluser_dbname" and "cpaneluser_dbuser".');
    if (empty($cfg['app']['allowed_origins'])) bad('app.allowed_origins is empty', 'List your website address, e.g. https://mcrelish.com');
    $flw = isset($cfg['flutterwave']) ? $cfg['flutterwave'] : array();
    if (empty($flw['secret_key']) && empty($flw['simulate_payments'])) warn('No Flutterwave secret key, so checkout will say payment is not set up.');
    if (!empty($flw['simulate_payments']) && $env === 'production') warn('simulate_payments is on while app.env is production.');
}

head('5. Database');
$pdo = null;
if (!is_array($cfg) || !extension_loaded('pdo_mysql')) {
    warn('Skipped: needs a loadable config.php and the pdo_mysql extension.');
} else {
    $db = $cfg['db'];
    $host = trim((string) $db['host']);
    $hosts = array($host);
    if ($host === 'localhost') $hosts[] = '127.0.0.1';
    $opts = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);
    $lastErr = null;
    foreach ($hosts as $h) {
        $dsn = 'mysql:host=' . $h . ';port=' . (int) (isset($db['port']) ? $db['port'] : 3306) . ';dbname=' . $db['name'] . ';charset=' . (isset($db['charset']) ? $db['charset'] : 'utf8mb4');
        try {
            $pdo = new PDO($dsn, $db['user'], $db['pass'], $opts);
            ok('Connected to "' . $db['name'] . '" as "' . $db['user'] . '" via host ' . $h);
            if ($h !== $host) warn('Only "' . $h . '" worked. Set db.host to "' . $h . '" in config.php.');
            break;
        } catch (PDOException $e) {
            $lastErr = $e;
            note('Host ' . $h . ' failed: ' . $e->getMessage());
        }
    }
    if (!$pdo) {
        $code = $lastErr ? (int) $lastErr->getCode() : 0;
        $hint = 'Check db.host, db.port, db.user and db.pass in config.php.';
        if ($code === 1045) $hint = 'Wrong user or password, or the user is not added to this database. In cPanel > MySQL Databases, add the user to the database with ALL PRIVILEGES.';
        if ($code === 1049) $hint = 'The database name does not exist. cPanel adds a prefix, e.g. "cpaneluser_mcrelish". Copy the exact name from cPanel > MySQL Databases.';
        if ($code === 2002) $hint = 'MySQL is not reachable on that host/port. On shared hosting use "localhost".';
        bad('Could not connect to the database', $hint);
    } else {
        $row = $pdo->query('SELECT VERSION()')->fetch(PDO::FETCH_NUM);
        say('  MySQL/MariaDB version ' . $row[0]);
        $have = array();
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM) as $t) $have[] = $t[0];
        $schemaFile = $root . '/database/schema.sql';
        $expected = array();
        if (is_file($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            if (preg_match_all('/CREATE TABLE IF NOT EXISTS (\w+) \((.*?)\n\)\s*ENGINE/s', $sql, $m, PREG_SET_ORDER)) {
                foreach ($m as $t) {
                    $cols = array();
                    foreach (explode("\n", $t[2]) as $line) {
                        if (preg_match('/^\s+(\w+)\s+[A-Z]/', $line, $c) && !in_array(strtoupper($c[1]), array('PRIMARY', 'UNIQUE', 'KEY', 'INDEX', 'CONSTRAINT', 'FOREIGN'), true)) $cols[] = $c[1];
                    }
                    $expected[$t[1]] = $cols;
                }
            }
        }
        if (!$expected) {
            warn('database/schema.sql not readable; checking table names only.');
            foreach (array('users', 'sessions', 'password_resets', 'login_attempts', 'user_roles', 'menu_sections', 'menu_items', 'menu_prices', 'delivery_zones', 'store_settings', 'hero_promo', 'orders', 'audit_log') as $t) $expected[$t] = array();
        }
        $missing = array_diff(array_keys($expected), $have);
        if ($missing) bad('Tables missing: ' . implode(', ', $missing), 'Import database/schema.sql (then seed.sql for the menu) in phpMyAdmin, or run "php bin/install.php" over SSH.');
        else ok('All ' . count($expected) . ' tables exist');
        foreach ($expected as $table => $cols) {
            if (!in_array($table, $have, true) || !$cols) continue;
            $now = array();
            foreach ($pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_NUM) as $c) $now[] = $c[0];
            $gone = array_diff($cols, $now);
            if ($gone) bad('Table ' . $table . ' is out of date, missing columns: ' . implode(', ', $gone), 'This database holds an older version. Use a fresh empty database and import schema.sql.');
        }
        if (!$missing) {
            foreach (array('menu_items' => 'menu items', 'user_roles' => 'role assignments', 'users' => 'accounts', 'orders' => 'orders') as $t => $label) {
                $n = (int) $pdo->query('SELECT COUNT(*) FROM `' . $t . '`')->fetchColumn();
                say('  ' . str_pad($label, 20) . $n);
            }
            if ((int) $pdo->query('SELECT COUNT(*) FROM menu_items')->fetchColumn() === 0) warn('The menu is empty: import database/seed.sql.');
            if ((int) $pdo->query("SELECT COUNT(*) FROM user_roles WHERE role = 'admin'")->fetchColumn() === 0) warn('No admin account yet: sign up on the site, then give it the admin role in user_roles (phpMyAdmin).');
        }
    }
}

head('6. Recent PHP error log lines');
$logs = array();
if (ini_get('error_log')) $logs[] = ini_get('error_log');
foreach (array('/error_log', '/public/error_log', '/src/error_log', '/storage/php-error.log', '/storage/error.log') as $rel) $logs[] = $root . $rel;
$logs[] = __DIR__ . '/error_log';
$shown = 0;
foreach (array_unique($logs) as $log) {
    if (!@is_file($log) || !@is_readable($log)) continue;
    $shown++;
    say('  ' . $log . ' (' . filesize($log) . ' bytes, last lines):');
    foreach (tail($log) as $l) say('    ' . $l);
    say();
}
if ($shown === 0) note('No readable error log found. In cPanel look at Metrics > Errors for the same messages.');

if ($root === null || !is_array($cfg)) {
    head('7. Live request');
    say('  Skipped: the config must load first.');
    exit;
}

$live = true;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = $path;
ob_start();
require $root . '/public/index.php';

<?php
/**
 * INTEGRITY AUDIT — missing files, duplicates, undefined symbols, broken references.
 *   php tests/audit.php
 */
$ROOT = dirname(__DIR__);
$P = 0; $F = 0;
function ck($name, $cond, $extra = '') {
    global $P, $F;
    if ($cond) { $P++; } else { $F++; echo "FAIL: $name  $extra\n"; }
}
function php_files($ROOT) {
    $out = array();
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT));
    $skip = array('/storage/', '/uploads/', '/.git/');   /* runtime data, not source */
    foreach ($it as $f) {
        if (!$f->isFile() || substr($f->getPathname(), -4) !== '.php') { continue; }
        $rp = str_replace($ROOT, '', $f->getPathname());
        foreach ($skip as $sd) { if (strpos($rp, $sd) === 0) { continue 2; } }
        $out[] = $f->getPathname();
    }
    sort($out); return $out;
}
$files = php_files($ROOT);
$rel = function ($p) use ($ROOT) { return ltrim(str_replace($ROOT, '', $p), '/'); };

echo "Auditing " . count($files) . " PHP files\n\n";

/* ---------- 1. syntax ---------- */
$bad = array();
foreach ($files as $f) {
    exec('php -l ' . escapeshellarg($f) . ' 2>&1', $o, $rc);
    if ($rc !== 0) { $bad[] = $rel($f) . ': ' . implode(' ', array_slice($o, 0, 2)); }
    $o = array();
}
ck('all PHP files parse', !$bad, implode(' | ', array_slice($bad, 0, 3)));

/* ---------- 2. duplicate function / class declarations ---------- */
$fns = array(); $cls = array();
foreach ($files as $f) {
    if (strpos($f, '/tests/') !== false) { continue; }   /* test helpers may share names */
    $src = file_get_contents($f);
    if (preg_match_all('/^\s*function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/m', $src, $m)) {
        foreach ($m[1] as $n) { $fns[$n][] = $rel($f); }
    }
    if (preg_match_all('/^\s*(?:abstract\s+|final\s+)?class\s+([a-zA-Z_][a-zA-Z0-9_]*)/m', $src, $m)) {
        foreach ($m[1] as $n) { $cls[$n][] = $rel($f); }
    }
}
$dupFn = array(); foreach ($fns as $n => $w) { if (count($w) > 1) { $dupFn[$n] = $w; } }
$dupCls = array(); foreach ($cls as $n => $w) { if (count($w) > 1) { $dupCls[$n] = $w; } }
ksort($dupFn); ksort($dupCls);
$report = array();
foreach ($dupFn as $n => $w) { $report[] = $n . '(' . implode(',', $w) . ')'; }
ck('no duplicate function declarations', !$dupFn, implode(' ', array_slice($report, 0, 3)));
$report = array(); foreach ($dupCls as $n => $w) { $report[] = $n . '(' . implode(',', $w) . ')'; }
ck('no duplicate class declarations', !$dupCls, implode(' ', array_slice($report, 0, 3)));

/* ---------- 3. duplicate files by content ---------- */
$hashes = array();
foreach ($files as $f) { $h = md5(file_get_contents($f)); $hashes[$h][] = $rel($f); }
$dupFiles = array_filter($hashes, function ($v) { return count($v) > 1; });
$report = array(); foreach ($dupFiles as $h => $w) { sort($w); $report[] = implode(' == ', $w); }
ck('no byte-identical duplicate PHP files', !$dupFiles, implode(' | ', array_slice($report, 0, 3)));

/* ---------- 3b. duplicate non-code files (assets, templates, manifests) ---------- */
$skipDirs = array('/storage/', '/uploads/', '/.git/', '/node_modules/');
$allHashes = array();
$it2 = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT));
foreach ($it2 as $f) {
    if (!$f->isFile()) continue;
    $rp = $rel($f->getPathname());
    foreach ($skipDirs as $sd) { if (strpos('/' . $rp, $sd) !== false) { continue 2; } }
    if (filesize($f->getPathname()) === 0) continue;
    $allHashes[md5_file($f->getPathname())][] = $rp;
}
$dupAll = array_filter($allHashes, function ($v) { return count($v) > 1; });
$rep = array(); foreach ($dupAll as $h => $w) { sort($w); $rep[] = implode(' == ', $w); }
ck('no duplicate files of any type', !$dupAll, implode(' | ', array_slice($rep, 0, 3)));

/* ---------- 4. referenced-but-missing files ---------- */
$missing = array();
foreach ($files as $f) {
    if (strpos($f, '/tests/audit.php') !== false) { continue; }
    $src = file_get_contents($f);
    /* require / include with a literal path built from __DIR__ or SF_ROOT */
    if (preg_match_all('/(?:require|include)(?:_once)?\s+((__DIR__|SF_ROOT)\s*\.\s*\'([^\']+)\')/', $src, $m, PREG_SET_ORDER)) {
        foreach ($m as $mm) {
            $const = $mm[2]; $tail = $mm[3];
            $base = ($const === 'SF_ROOT') ? $ROOT : dirname($f);
            $path = preg_replace('#/+#', '/', $base . '/' . ltrim($tail, '/'));
            if (!is_file($path)) { $missing[] = $rel($f) . ' -> ' . $mm[0]; }
        }
    }
    /* templates: sf_view('name', ...) and $this->view('name') */
    if (preg_match_all('/sf_view\(\s*\'([a-z0-9_]+)\'/', $src, $m)) {
        foreach ($m[1] as $t) { if (!is_file($ROOT . '/templates/' . $t . '.php')) { $missing[] = $rel($f) . " -> template '$t'"; } }
    }
    /* assets: sf_asset('css/x.css') */
    if (preg_match_all('/sf_asset\(\s*\'([^\']+)\'/', $src, $m)) {
        foreach ($m[1] as $a) { if (!is_file($ROOT . '/assets/' . ltrim($a, '/'))) { $missing[] = $rel($f) . " -> asset '$a'"; } }
    }
}
$missing = array_unique($missing);
ck('no missing includes, templates or assets', !$missing, implode(' | ', array_slice($missing, 0, 4)));

/* ---------- 5. addon packaging ---------- */
$dirs = glob($ROOT . '/plugins/*', GLOB_ONLYDIR);
$dirs = array_filter($dirs, function ($d) { return is_dir($d); });
$noJson = array(); $noPhp = array(); $badClass = array(); $badKey = array();
$routes = array(); $stages = array();
foreach ($dirs as $d) {
    $key = basename($d);
    $json = $d . '/plugin.json'; $php = $d . '/plugin.php';
    if (!is_file($json)) { $noJson[] = $key; continue; }
    if (!is_file($php)) { $noPhp[] = $key; continue; }
    $meta = json_decode(file_get_contents($json), true);
    if (!is_array($meta)) { $badKey[] = $key . '(unparsable json)'; continue; }
    if (empty($meta['key']) || $meta['key'] !== $key) { $badKey[] = $key . ' (manifest says ' . (isset($meta['key']) ? $meta['key'] : '?') . ')'; }
    $want = 'SFPlugin_' . str_replace('-', '_', $key);
    if (strpos(file_get_contents($php), 'class ' . $want) === false) { $badClass[] = $key . ' (expected class ' . $want . ')'; }
    if (!empty($meta['routes'])) { foreach ($meta['routes'] as $r) { $rt = is_array($r) ? $r['route'] : $r; $routes[$rt][] = $key; } }
    if (!empty($meta['stage']['key'])) { $stages[$meta['stage']['key']][] = $key; }
}
ck('every addon folder has plugin.json', !$noJson, implode(',', $noJson));
ck('every addon folder has plugin.php', !$noPhp, implode(',', $noPhp));
ck('every manifest key matches its folder', !$badKey, implode(',', $badKey));
ck('every addon declares the expected class', !$badClass, implode(' | ', $badClass));
$dupRoutes = array_filter($routes, function ($v) { return count($v) > 1; });
$rep = array(); foreach ($dupRoutes as $r => $w) { $rep[] = $r . ':' . implode('/', $w); }
ck('no two addons claim the same route', !$dupRoutes, implode(',', $rep));
$dupStages = array_filter($stages, function ($v) { return count($v) > 1; });
$rep = array(); foreach ($dupStages as $s => $w) { $rep[] = $s . ':' . implode('/', $w); }
ck('no two addons claim the same pipeline stage', !$dupStages, implode(',', $rep));

/* addon routes must not collide with core routes */
$router = file_get_contents($ROOT . '/core/router.php');
preg_match_all('/case \'([a-z]+)\':\s*return sf_view/', $router, $core);
$coreRoutes = array_unique($core[1]);
$collide = array();
foreach ($routes as $rt => $owners) { if (in_array($rt, $coreRoutes, true)) { $collide[] = $rt; } }
ck('no addon route shadows a core route', !$collide, implode(',', $collide));

/* installer addon list vs disk */
$installer = file_get_contents($ROOT . '/install.php');
preg_match('/\$order\s*=\s*array\((.*?)\);/s', $installer, $mm);
$listed = array();
if (!empty($mm[1])) { preg_match_all('/\'([a-z0-9\-]+)\'/', $mm[1], $names); $listed = $names[1]; }
$onDisk = array_map('basename', array_values($dirs));
$notInstalled = array_diff($onDisk, $listed);
$notOnDisk = array_diff($listed, $onDisk);
ck('every addon on disk is installed by the installer', !$notInstalled, implode(',', $notInstalled));
ck('installer does not reference a missing addon', !$notOnDisk, implode(',', $notOnDisk));

/* ---------- 6. schema references ---------- */
if (!defined('SF_ROOT')) { define('SF_ROOT', $ROOT); }
require_once $ROOT . '/core/schema.php';
$tables = array_keys(sf_schema());
$src = '';
foreach ($files as $f) { $src .= file_get_contents($f); }
/* collect only string literals that actually look like SQL */
$sqlText = '';
if (preg_match_all('/"(?:\s*(?:SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|SHOW)[^"]*)"/', $src, $lit)) { $sqlText .= implode(' ', $lit[0]); }
if (preg_match_all("/'(?:\s*(?:SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|SHOW)[^']*)'/", $src, $lit)) { $sqlText .= ' ' . implode(' ', $lit[0]); }
preg_match_all('/\b(?:FROM|JOIN|INTO|UPDATE)\s+`?([a-z_][a-z0-9_]*)`?/i', $sqlText, $m);
$used = array_unique($m[1]);
$sqlWords = array('array', 'select', 'values', 'set', 'where', 'count', 'sum', 'ifnull', 'distinct', 'order', 'group', 'limit');
/* every column name in the schema is also a legal token (UPDATE t SET col=.. , INSERT INTO t (cols)) */
$columns = array();
foreach (sf_schema() as $ddl) { if (preg_match_all('/`([a-z_][a-z0-9_]*)`\s+[A-Za-z]+/', $ddl, $c)) { foreach ($c[1] as $cn) { $columns[strtolower($cn)] = true; } } }
/* SQL aliases:  "... ) alias"  and  "... AS alias" */
$aliases = array();
if (preg_match_all('/\)\s*([a-z_][a-z0-9_]*)\b/i', $sqlText, $al)) { foreach ($al[1] as $a) { $aliases[strtolower($a)] = true; } }
if (preg_match_all('/\bAS\s+([a-z_][a-z0-9_]*)\b/i', $sqlText, $al)) { foreach ($al[1] as $a) { $aliases[strtolower($a)] = true; } }
$systemDb = array('information_schema', 'mysql', 'performance_schema');
$unknown = array();
foreach ($used as $t) {
    $tl = strtolower($t);
    if (in_array($tl, $sqlWords, true) || in_array($tl, $systemDb, true)) { continue; }
    if (isset($columns[$tl]) || isset($aliases[$tl])) { continue; }
    if (!in_array($tl, $tables, true)) { $unknown[] = $tl; }
}
ck('every table referenced in SQL exists in the schema', !$unknown, implode(',', array_slice($unknown, 0, 6)));

/* ---------- 7. feature flags used vs defined ---------- */
if (!defined('SF_ROOT')) { define('SF_ROOT', $ROOT); }
require_once $ROOT . '/core/schema.php';
$flags = array();
foreach (sf_features() as $frow) { $flags[$frow[0]] = true; }
preg_match_all('/feature_enabled\(\s*\'([a-z0-9_]+)\'/', $src, $m);
$usedFlags = array_unique($m[1]);
$undef = array(); foreach ($usedFlags as $k) { if (!isset($flags[$k])) { $undef[] = $k; } }
ck('every feature_enabled() key is defined in sf_features()', !$undef, implode(',', $undef));
preg_match_all('/orchestrator_run\(\s*\'([a-z0-9_]+)\'/', $src, $m);
$usedCaps = array_unique($m[1]);
ck('capabilities used by addons', count($usedCaps) > 0, implode(',', $usedCaps));

/* ---------- 8. stray / leftover files ---------- */
$stray = array();
foreach (array('install_debug.php', 'config.php', 'debug.php', 'test.php', 'info.php', 'phpinfo.php') as $s) {
    if (is_file($ROOT . '/' . $s) && $s !== 'config.php') { $stray[] = $s; }
}
ck('no stray debug scripts in the tree', !$stray, implode(',', $stray));
$empty = array();
foreach ($files as $f) { if (filesize($f) < 60) { $empty[] = $rel($f) . '(' . filesize($f) . 'B)'; } }
ck('no near-empty PHP files', !$empty, implode(',', array_slice($empty, 0, 4)));

echo "\n$P passed, $F failed\n";
exit($F ? 1 : 0);

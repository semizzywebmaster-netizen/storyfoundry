<?php
define('SF_ROOT', __DIR__);
$root = '/home/user/storyfoundry-cpanel';
define('SF_ROOT_R', $root);
$pass = 0; $fail = 0;
function t($name, $cond, $extra='') { global $pass, $fail; if ($cond) { $pass++; } else { $fail++; echo "FAIL: $name $extra\n"; } }

/* --- 1. plugin.json validity + contract --- */
$dirs = glob($root . '/plugins/*', GLOB_ONLYDIR);
t('plugin dirs found', count($dirs) >= 31, count($dirs));
$stages = array(); $keys = array();
foreach ($dirs as $d) {
    $key = basename($d); $keys[] = $key;
    $jf = $d . '/plugin.json'; $pf = $d . '/plugin.php';
    t("$key has plugin.json", is_file($jf));
    t("$key has plugin.php", is_file($pf));
    $j = json_decode(@file_get_contents($jf), true);
    t("$key json parses", is_array($j), json_last_error_msg());
    if (!is_array($j)) continue;
    t("$key json.key matches dir", isset($j['key']) && $j['key'] === $key);
    $cls = 'SFPlugin_' . str_replace(array('-', '.'), '_', $key);
    t("$key declares class $cls", strpos(file_get_contents($pf), 'class ' . $cls . ' extends PluginBase') !== false);
    if (!empty($j['stage'])) { $stages[$j['stage']['key']] = $key; t("$key stage has label+icon+order", !empty($j['stage']['label']) && !empty($j['stage']['icon']) && isset($j['stage']['order'])); }
}
t('stage keys unique', count($stages) === count(array_unique(array_keys($stages))));
t('all pipeline stages present', !array_diff(array('idea','story','characters','scenes','shots','images','voice','audio','video','timeline','subtitles','thumbnail','social','export'), array_keys($stages)), implode(',', array_keys($stages)));

/* --- 2. requires resolve --- */
foreach ($dirs as $d) {
    $j = json_decode(@file_get_contents($d . '/plugin.json'), true);
    foreach ((array)($j['requires'] ?? array()) as $req) { t(basename($d) . " requires $req exists", in_array($req, $keys, true)); }
}

/* --- 3. installer list covers everything --- */
$installer = file_get_contents($root . '/install.php');
foreach ($keys as $k) { if (in_array($k, array('openai','stability','elevenlabs','replicate','runway'), true)) continue; t("installer installs $k", strpos($installer, $k) !== false); }

/* --- 4. core helpers referenced by addons exist --- */
$core = '';
foreach (glob($root . '/core/*.php') as $f) { $core .= file_get_contents($f); }
$core .= file_get_contents($root . '/core/util.php');
$needed = array('sf_queue','orchestrator_run','providers_call','providers_register','stage_data','stage_save','project_ctx','project_asset','project_log_activity','ui_status','ui_empty','ui_kpi','ui_select','ui_form_open','ui_time_ago','sf_bytes','sf_store_bytes','sf_http','media_ffmpeg','media_status','media_render_video','media_mix_audio','media_burn_subtitles','mockai_frame_svg','culture_context','cultures','storage_signed_url','credits_grant','credits_reserve','credits_release','wallet_spend','wallet_deposit','jobs_create','jobs_retry','jobs_cancel','jobs_run_due','jobs_stats','jobs_for_user','notify','audit','setting','setting_set','feature_cost','feature_enabled','sf_response_raw','sf_url','e','csrf_check','flash','auth_id','auth_user','auth_is_admin','require_login','sf_int','sf_get','sf_post','sf_token');
foreach ($needed as $fn) { t("core defines $fn()", strpos($core, 'function ' . $fn . '(') !== false); }

/* --- 5. schema tables referenced by addons exist --- */
$schema = file_get_contents($root . '/core/schema.php');
foreach (array('sessions','exports','series','ad_events','marketplace_items','marketplace_orders','agency_clients','agency_members','social_accounts','publishing','project_activity','assets','jobs','reservations','credit_tx','transactions','coupons','plans','subscriptions','providers','usage_log','feature_flags','notifications','audit','logs','shares') as $tb) {
    t("schema has $tb", strpos($schema, "\$t['$tb']") !== false);
}

/* --- 6. mockai outputs are real files/formats --- */
require_once $root . '/core/plugins.php';
require_once $root . '/core/mockai.php';
require_once $root . '/core/culture.php';
$svg = mockai_frame_svg('test-seed', array('style' => 'cinematic', 'w' => 640, 'h' => 360, 'subject' => 'scene', 'text' => 'Hello'));
t('frame svg is xml', substr($svg, 0, 5) === '<svg ' && substr(trim($svg), -6) === '</svg>');
t('frame svg deterministic', $svg === mockai_frame_svg('test-seed', array('style' => 'cinematic', 'w' => 640, 'h' => 360, 'subject' => 'scene', 'text' => 'Hello')));
t('frame svg differs by seed', $svg !== mockai_frame_svg('other-seed', array('style' => 'cinematic', 'w' => 640, 'h' => 360)));
$c = culture_get('Yoruba');
t('culture has names/places', !empty($c['names']) && !empty($c['places']));
t('20+ cultures', count(cultures()) >= 20, count(cultures()));
$story = mockai_text('story', array('title' => 'The Salt Road', 'genre' => 'Drama', 'names' => array('Ada'), 'places' => array('Lagos')), 3);
t('mockai story has chapters', !empty($story['chapters']) && !empty($story['words']), isset($story['words']) ? $story['words'] : 'none');
$ideas = mockai_text('ideas', array('title' => 'X', 'genre' => 'Drama', 'names' => array('Ada'), 'places' => array('Lagos')), 4);
t('mockai ideas is array of 4', is_array($ideas) && count($ideas) === 4, is_array($ideas) ? count($ideas) : 'not array');
$chars = mockai_text('characters', array('title' => 'X', 'genre' => 'Drama', 'names' => array('Ada'), 'places' => array('Lagos')), 4);
t('mockai characters have name+voice', !empty($chars[0]['name']) && !empty($chars[0]['voice']));
$shots = mockai_text('shots', array('title' => 'X', 'names' => array('Ada'), 'places' => array('Lagos')), 4);
t('mockai shots have type+lens', !empty($shots[0]['type']) && !empty($shots[0]['lens']));
$rw = mockai_rewrite('Ada walked to the market. It was hot.', 'Cinematic', array());
t('mockai rewrite returns text', is_string($rw) && strlen($rw) > 20);


/* 32. no hard dependency on PHP extensions shared hosts (cPanel) may lack */
$allPhp = array();
$rit = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($rit as $fi) { if ($fi->isFile() && substr($fi->getPathname(), -4) === '.php') { $allPhp[] = $fi->getPathname(); } }
$ext = array('mb_substr', 'mb_strlen', 'mb_strtolower', 'mb_convert_encoding', 'iconv(', 'intl_', 'imap_');
$bad = array();
foreach ($allPhp as $f) {
    $src = file_get_contents($f);
    foreach ($ext as $fn) {
        if (strpos($src, $fn) !== false && strpos($src, 'function_exists') === false) { $bad[] = basename($f) . ':' . $fn; }
    }
}
t('no mbstring/iconv/intl hard dependency', !$bad, implode(', ', array_slice($bad, 0, 4)));
t('file cache layer present (no Redis needed)',
    strpos(file_get_contents($root . '/core/util.php'), 'function sf_cache_get') !== false);
t('theme layer present (dark/light UI)',
    strpos(file_get_contents($root . '/core/util.php'), 'function sf_theme') !== false);
t('addon page() contract present',
    strpos(file_get_contents($root . '/core/plugins.php'), 'public function page(') !== false);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);

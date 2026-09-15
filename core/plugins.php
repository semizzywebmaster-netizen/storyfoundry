<?php
if (!defined('SF_ROOT')) { http_response_code(403); exit('Direct access denied'); }
/**
 * ADDON / PLUGIN SYSTEM
 * ------------------------------------------------------------------
 * Drop a folder in /plugins/<key>/ containing:
 *   plugin.json   { key, name, version, author, requires[], stage{key,label,icon,order},
 *                   features[numbers], capabilities[], routes[], settings{} }
 *   plugin.php    class SFPlugin_<key_underscored> extends PluginBase { ... }
 * Then: Admin → Addons → Install → Enable. Nothing else to wire.
 */
abstract class PluginBase {
    public $key = '';
    public $meta = array();

    public function __construct($key, $meta) { $this->key = $key; $this->meta = $meta; }
    /** Called on every request for enabled plugins. Register hooks/providers here. */
    public function boot() {}
    /** One-time setup (tables, defaults). */
    public function install() { return true; }
    /** Cleanup on uninstall. */
    public function uninstall() { return true; }
    /** Render the pipeline stage panel inside a project (if this addon declares a stage). */
    public function stage($project, $stageData) { return '<div class="empty">No stage view.</div>'; }
    /**
     * Full page for this addon: index.php?r=studio/<key>  (multi-page UI, feature 1/83).
     * Default implementation = header + project picker + this addon's stage panel.
     * Addons may override page($ctx) to render something custom.
     */
    public function page($ctx) {
        $meta = $this->meta;
        $icon = isset($meta['stage']['icon']) ? $meta['stage']['icon'] : (isset($meta['icon']) ? $meta['icon'] : '\xF0\x9F\xA7\xA9');
        $desc = isset($meta['description']) ? $meta['description'] : '';
        $h = '<div class="row" style="align-items:center;gap:12px;flex-wrap:wrap">'
           . '<span style="font-size:28px">' . e($icon) . '</span>'
           . '<div><h1 style="margin:0;font-size:22px">' . e(isset($meta['name']) ? $meta['name'] : $this->key) . '</h1>'
           . ($desc ? '<div class="muted" style="font-size:13px">' . e($desc) . '</div>' : '')
           . '</div><span class="spacer"></span>'
           . '<span class="chip chip-nc">addon v' . e(isset($meta['version']) ? $meta['version'] : '1.0.0') . '</span></div>';
        $stageKey = isset($meta['stage']['key']) ? $meta['stage']['key'] : '';
        if (!$stageKey) {
            return $h . ui_empty($icon, 'This addon adds platform capability rather than a studio panel.', $this->page_links());
        }
        $projects = isset($ctx['projects']) ? $ctx['projects'] : array();
        if (!$projects) {
            return $h . ui_empty('\xF0\x9F\x93\x81', 'Create a project to use this studio.',
                '<a class="btn pri" href="' . e(sf_url('index.php?r=projects&a=new')) . '">\xEF\xBC\x8B New project</a>');
        }
        $h .= '<form method="get" action="' . e(sf_url('index.php')) . '" class="row" style="gap:8px;align-items:center;margin:14px 0;flex-wrap:wrap">'
            . '<input type="hidden" name="r" value="studio/' . e($this->key) . '">'
            . '<label class="muted" style="font-size:13px">Project</label>'
            . '<select name="pid" class="inp" onchange="this.form.submit()">';
        foreach ($projects as $pr) {
            $sel = (!empty($ctx['project']) && (int) $ctx['project']['id'] === (int) $pr['id']) ? ' selected' : '';
            $h .= '<option value="' . (int) $pr['id'] . '"' . $sel . '>' . e($pr['title']) . '</option>';
        }
        $h .= '</select><noscript><button class="btn sm">Open</button></noscript></form>';
        if (!empty($ctx['project'])) {
            $pid = (int) $ctx['project']['id'];
            $h .= '<div class="muted" style="font-size:12px;margin-bottom:8px">'
                . '<a href="' . e(sf_url('index.php?r=project/' . $pid . '/' . $stageKey)) . '">Open inside the full production pipeline \xE2\x86\x92</a></div>';
            $h .= $this->stage($ctx['project'], stage_data($pid, $stageKey, null));
        }
        return $h;
    }
    /** Links shown for addons that expose no studio panel (they are platform addons). */
    public function page_links() {
        $meta = $this->meta; $out = array();
        if (!empty($meta['routes'])) {
            foreach ($meta['routes'] as $rt) {
                $r = is_array($rt) ? $rt['route'] : $rt;
                if (substr($r, -5) === '.json' || substr($r, -3) === '.js') continue;
                $out[] = '<a class="btn sm" href="' . e(sf_url('index.php?r=' . $r)) . '">Open ' . e(ucwords(str_replace('-', ' ', $this->key))) . '</a>';
            }
        }
        if (function_exists('auth_is_admin') && auth_is_admin()) {
            $out[] = '<a class="btn sm gho" href="' . e(sf_url('index.php?r=admin&p=' . $this->key)) . '">Configure</a>';
        }
        return implode(' ', $out);
    }
    /** Handle AJAX: index.php?r=api&a=<key>.<action> */
    public function api($action, $input) { return array('ok' => false, 'error' => 'Unknown action: ' . $action); }
    /** Admin page: index.php?r=admin&p=<key> */
    public function admin($input) { return '<div class="empty">No admin page.</div>'; }
    /** Menu items: array of array('route','label','icon','group','perm') */
    public function menu() { return array(); }
    /** Dashboard cards: array of HTML strings */
    public function dashboard() { return array(); }
    /** Settings fields for Admin → Addons → Configure */
    public function settings_fields() { return array(); }
    /**
     * Background job dispatch. An addon enqueues with sf_queue($feature, 'key.method', $input)
     * and implements job_<method>($input, $job), returning
     * array('ok'=>true,'data'=>...,'provider'=>...,'status'=>'REAL|MOCK').
     */
    public function job($method, $input, $job = null) {
        $m = 'job_' . $method;
        if (method_exists($this, $m)) { return $this->$m($input, $job); }
        return array('ok' => false, 'error' => 'Unknown job method: ' . $method);
    }
    /** Called after a queued job owned by this addon completes. Override to persist results. */
    public function job_done($method, $job, $res) { return null; }
    /* helpers */
    public function config($k = null, $default = null) {
        $cfg = isset($this->meta['config']) && is_array($this->meta['config']) ? $this->meta['config'] : array();
        if ($k === null) return $cfg;
        return isset($cfg[$k]) ? $cfg[$k] : $default;
    }
    public function view($template, $vars = array()) {
        $file = dirname(dirname(__FILE__)) . '/plugins/' . $this->key . '/views/' . $template . '.php';
        if (!is_file($file)) return '<div class="empty">Missing view: ' . e($template) . '</div>';
        extract($vars);
        ob_start(); include $file; return ob_get_clean();
    }
}

/* ------------------------------------------------------------------ hooks */
$GLOBALS['SF_HOOKS'] = array();
function add_action($tag, $cb, $prio = 10) { $GLOBALS['SF_HOOKS']['a'][$tag][$prio][] = $cb; }
function add_filter($tag, $cb, $prio = 10) { $GLOBALS['SF_HOOKS']['f'][$tag][$prio][] = $cb; }
function do_action($tag) {
    $args = array_slice(func_get_args(), 1);
    $h = isset($GLOBALS['SF_HOOKS']['a'][$tag]) ? $GLOBALS['SF_HOOKS']['a'][$tag] : array();
    ksort($h);
    foreach ($h as $cbs) { foreach ($cbs as $cb) { if (is_callable($cb)) { call_user_func_array($cb, $args); } } }
}
function apply_filters($tag, $value) {
    $args = array_slice(func_get_args(), 1);
    $h = isset($GLOBALS['SF_HOOKS']['f'][$tag]) ? $GLOBALS['SF_HOOKS']['f'][$tag] : array();
    ksort($h);
    foreach ($h as $cbs) { foreach ($cbs as $cb) { if (is_callable($cb)) { $args[0] = call_user_func_array($cb, $args); } } }
    return $args[0];
}

/* -------------------------------------------------------------- discovery */
function plugins_dir() { return SF_ROOT . '/plugins'; }
function plugins_discover() {
    $out = array();
    $dir = plugins_dir();
    if (!is_dir($dir)) return $out;
    foreach (glob($dir . '/*', GLOB_ONLYDIR) as $d) {
        $key = basename($d);
        $json = $d . '/plugin.json';
        if (!is_file($json)) continue;
        $meta = json_decode(file_get_contents($json), true);
        if (!is_array($meta) || empty($meta['key'])) continue;
        $meta['key'] = $key;
        $meta['path'] = $d;
        $out[$key] = $meta;
    }
    return $out;
}
function plugins_db_rows() {
    if (isset($GLOBALS['SF_PLUGIN_ROWS']) && is_array($GLOBALS['SF_PLUGIN_ROWS'])) return $GLOBALS['SF_PLUGIN_ROWS'];
    $rows = array();
    try { foreach (db_all('SELECT * FROM plugins') as $r) { $rows[$r['pkey']] = $r; } } catch (Exception $e) {}
    $GLOBALS['SF_PLUGIN_ROWS'] = $rows;
    return $rows;
}
function plugins_reset_cache() {
    $GLOBALS['SF_PLUGIN_ROWS'] = null;
    if (function_exists('sf_cache_forget')) { sf_cache_forget('plugins_meta'); }
}
function plugins_meta($key) {
    static $all = null;
    if ($all === null) {   /* 37 manifests on disk — cached for 5 minutes (feature 76) */
        $all = function_exists('sf_cache_get') ? sf_cache_get('plugins_meta', null) : null;
        if (!is_array($all)) { $all = plugins_discover(); if (function_exists('sf_cache_set')) { sf_cache_set('plugins_meta', $all, 300); } }
    }
    return isset($all[$key]) ? $all[$key] : null;
}
function plugins_is_enabled($key) {
    $rows = plugins_db_rows();
    return isset($rows[$key]) && !empty($rows[$key]['enabled']);
}
function plugins_is_installed($key) { $rows = plugins_db_rows(); return isset($rows[$key]); }
function plugin_class_name($key) { return 'SFPlugin_' . str_replace(array('-', '.'), '_', $key); }
function plugin_instance($key) {
    static $inst = array();
    if (isset($inst[$key])) return $inst[$key];
    $meta = plugins_meta($key);
    if (!$meta) return null;
    $file = $meta['path'] . '/plugin.php';
    if (!is_file($file)) return null;
    require_once $file;
    $cls = plugin_class_name($key);
    if (!class_exists($cls)) return null;
    $cfg = array();
    $rows = plugins_db_rows();
    if (isset($rows[$key]) && $rows[$key]['config']) { $j = json_decode($rows[$key]['config'], true); if (is_array($j)) $cfg = $j; }
    $meta['config'] = $cfg;
    $inst[$key] = new $cls($key, $meta);
    return $inst[$key];
}
function plugins_bootstrap() {
    if (!empty($GLOBALS['SF_PLUGINS_BOOTED'])) return;
    $GLOBALS['SF_PLUGINS_BOOTED'] = true;
    if (!db()) return;
    $rows = plugins_db_rows();
    foreach ($rows as $key => $row) {
        if (empty($row['enabled'])) continue;
        $meta = plugins_meta($key);
        if (!$meta) continue;
        $p = plugin_instance($key);
        if ($p) { try { $p->boot(); } catch (Exception $e) { sf_log('error', 'plugin', $key . ' boot: ' . $e->getMessage()); } }
    }
    do_action('plugins.booted');
}
/** Enable/disable/install/uninstall */
function plugin_install($key) {
    $meta = plugins_meta($key);
    if (!$meta) return array('ok' => false, 'error' => 'Addon not found');
    $ok = true; $err = '';
    foreach (isset($meta['requires']) ? $meta['requires'] : array() as $req) {
        if (!plugins_is_enabled($req)) { $ok = false; $err = 'Requires addon: ' . $req; }
    }
    if (!$ok) return array('ok' => false, 'error' => $err);
    try {
        db_exec('INSERT IGNORE INTO plugins (pkey,name,version,enabled,config,installed_at) VALUES (?,?,?,?,?,?)',
            array($key, $meta['name'], isset($meta['version']) ? $meta['version'] : '1.0.0', 0, '{}', db_now()));
    } catch (Exception $e) {}
    $p = plugin_instance($key);
    if ($p) { try { $p->install(); } catch (Exception $e) { sf_log('error', 'plugin', $key . ' install: ' . $e->getMessage()); } }
    db_exec('UPDATE plugins SET enabled=1 WHERE pkey=?', array($key));
    plugins_reset_cache();
    audit('plugin.install', $key, 'info');
    sf_log('info', 'plugin', 'installed ' . $key);
    return array('ok' => true);
}
function plugin_uninstall($key) {
    $p = plugin_instance($key);
    if ($p) { try { $p->uninstall(); } catch (Exception $e) {} }
    db_exec('DELETE FROM plugins WHERE pkey=?', array($key));
    plugins_reset_cache();
    audit('plugin.uninstall', $key, 'warn');
    return array('ok' => true);
}
function plugin_set_enabled($key, $on) {
    db_exec('UPDATE plugins SET enabled=? WHERE pkey=?', array($on ? 1 : 0, $key));
    plugins_reset_cache();
    audit('plugin.' . ($on ? 'enable' : 'disable'), $key, 'info');
    return array('ok' => true);
}
function plugin_set_config($key, $config) {
    db_exec('UPDATE plugins SET config=? WHERE pkey=?', array(json_encode($config), $key));
    return array('ok' => true);
}
/** Pipeline stages declared by enabled addons (feature: the 19-stage chain). */
function plugins_stages() {
    $rows = plugins_db_rows();
    $stages = array();
    foreach ($rows as $key => $row) {
        if (empty($row['enabled'])) continue;
        $meta = plugins_meta($key);
        if (!$meta || empty($meta['stage'])) continue;
        $s = $meta['stage'];
        $stages[] = array(
            'key' => isset($s['key']) ? $s['key'] : $key, 'label' => isset($s['label']) ? $s['label'] : $meta['name'],
            'icon' => isset($s['icon']) ? $s['icon'] : '•', 'order' => isset($s['order']) ? (int) $s['order'] : 99,
            'plugin' => $key,
        );
    }
    usort($stages, function ($a, $b) { return $a['order'] - $b['order']; });
    return apply_filters('plugins.stages', $stages);
}
function plugins_menu($group = 'main') {
    $out = array();
    $rows = plugins_db_rows();
    foreach ($rows as $key => $row) {
        if (empty($row['enabled'])) continue;
        $p = plugin_instance($key);
        if (!$p) continue;
        try { $m = $p->menu(); } catch (Exception $e) { $m = array(); }
        foreach ((array) $m as $item) {
            if (!isset($item['group'])) $item['group'] = 'main';
            if ($item['group'] === $group) { $item['plugin'] = $key; $out[] = $item; }
        }
    }
    return $out;
}
function plugins_api($key, $action, $input) {
    if (!plugins_is_enabled($key)) return array('ok' => false, 'error' => 'Addon disabled');
    $p = plugin_instance($key);
    if (!$p) return array('ok' => false, 'error' => 'Addon missing');
    try { return $p->api($action, $input); }
    catch (Exception $e) { sf_log('error', 'plugin', $key . '.' . $action . ': ' . $e->getMessage()); return array('ok' => false, 'error' => $e->getMessage()); }
}
/** Project stage payload helpers: each stage stores JSON in project_data. */
function stage_data($project_id, $stage, $default = null) {
    $row = db_one('SELECT data FROM project_data WHERE project_id=? AND stage_key=?', array((int) $project_id, $stage));
    if (!$row) return $default;
    $j = json_decode($row['data'], true);
    return $j === null ? $default : $j;
}
function stage_save($project_id, $stage, $data) {
    db_exec('INSERT INTO project_data (project_id,stage_key,data,updated_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE data=VALUES(data), updated_at=VALUES(updated_at)',
        array((int) $project_id, $stage, json_encode($data), db_now()));
    return true;
}

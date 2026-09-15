<?php
if (!defined('SF_ROOT')) { http_response_code(403); exit('Direct access denied'); }
/** Views, layout and UI components. No external CSS/JS/fonts — works behind any host. */
function sf_view($template, $vars = array(), $title = '') {
    return array('type' => 'view', 'template' => $template, 'vars' => $vars, 'title' => $title);
}
function sf_response_json($data, $code = 200) { return array('type' => 'json', 'data' => $data, 'code' => $code); }
function sf_response_redirect($to) { return array('type' => 'redirect', 'to' => $to); }
function sf_response_download($path, $name = null) { return array('type' => 'download', 'path' => $path, 'name' => $name); }
function sf_response_raw($body, $contentType = 'text/plain') { return array('type' => 'raw', 'body' => $body, 'ct' => $contentType); }

function sf_render_response($out) {
    if (!$out) { $out = sf_view('404', array(), 'Not found'); }
    if (($out['type'] ?? 'view') === 'view' && ($out['template'] ?? '') === '404') {
        http_response_code(404);   /* real 404 status for crawlers and monitoring */
    }
    switch ($out['type']) {
        case 'json': sf_json($out['data'], isset($out['code']) ? $out['code'] : 200); break;
        case 'redirect': sf_redirect($out['to']); break;
        case 'download': sf_download($out['path'], isset($out['name']) ? $out['name'] : null); break;
        case 'raw': header('Content-Type: ' . $out['ct']); echo $out['body']; break;
        default:
            $vars = isset($out['vars']) ? $out['vars'] : array();
            $vars['title'] = isset($out['title']) ? $out['title'] : 'STORYFOUNDRY';
            $vars['view'] = $out['template'];
            sf_layout($vars);
    }
}
function sf_layout($vars) {
    extract($vars);
    $user = auth_user();
    $flash = flash_take();
    $file = SF_ROOT . '/templates/' . $view . '.php';
    if (!is_file($file)) { $view = '404'; $file = SF_ROOT . '/templates/404.php'; }
    ob_start();
    include $file;
    $body = ob_get_clean();
    include SF_ROOT . '/templates/layout.php';
}
function sf_asset($rel) { return sf_url('assets/' . ltrim($rel, '/')); }

/* ------------------------------------------------------------ components */
function ui_status($s) {
    $map = array('REAL' => 'ok', 'MOCK' => 'mock', 'NOT CONFIGURED' => 'nc', 'DISABLED' => 'off',
        'healthy' => 'ok', 'degraded' => 'warn', 'down' => 'err', 'unknown' => 'nc',
        'completed' => 'ok', 'processing' => 'info', 'queued' => 'nc', 'failed' => 'err', 'cancelled' => 'off');
    $c = isset($map[$s]) ? $map[$s] : 'nc';
    return '<span class="chip chip-' . $c . '"><i class="dot"></i>' . e($s) . '</span>';
}
function ui_kpi($value, $label, $sub = '') {
    return '<div class="kpi"><div class="v">' . e($value) . '</div><div class="k">' . e($label) . '</div>'
        . ($sub ? '<div class="d">' . e($sub) . '</div>' : '') . '</div>';
}
function ui_empty($icon, $text, $html = '') {
    return '<div class="empty"><div class="ico">' . e($icon) . '</div>' . e($text) . '<br>' . $html . '</div>';
}
function ui_card($title, $body, $right = '') {
    return '<div class="card"><div class="row" style="justify-content:space-between;align-items:flex-start">'
        . '<h3>' . $title . '</h3>' . ($right ? '<div>' . $right . '</div>' : '') . '</div>' . $body . '</div>';
}
function ui_form_open($action = '', $method = 'post', $extra = '') {
    return '<form method="' . e($method) . '" action="' . e($action) . '" ' . $extra . '>' . csrf_field();
}
function ui_select($name, $options, $selected = null, $attrs = '') {
    $h = '<select name="' . e($name) . '" ' . $attrs . '>';
    foreach ($options as $v => $l) { $h .= '<option value="' . e($v) . '"' . ((string) $selected === (string) $v ? ' selected' : '') . '>' . e($l) . '</option>'; }
    return $h . '</select>';
}
function ui_time_ago($ts) { return e(time_ago($ts)); }

/** Nav helpers for the layout. */
function sf_nav_active($route) {
    $cur = isset($_GET['r']) ? strtolower(trim($_GET['r'], '/')) : 'home';
    $cur = explode('/', $cur);
    return ($cur[0] === $route) ? 'on' : '';
}
function sf_nav_group($group) {
    static $cache = null;
    if ($cache === null) { $cache = array(); foreach (plugins_menu() as $it) { $cache[$it['group']][] = $it; } }
    return isset($cache[$group]) ? $cache[$group] : array();
}

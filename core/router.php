<?php
if (!defined('SF_ROOT')) { http_response_code(403); exit('Direct access denied'); }
/** Tiny router. Core routes first, then routes registered by addons. */
function sf_route($route) {
    $parts = explode('/', trim($route, '/'));
    $base = $parts[0];
    $arg = isset($parts[1]) ? $parts[1] : null;
    /* accept ?r=projects&a=new as well as ?r=projects/new (links in the UI use both forms) */
    if ($arg === null && $base !== 'api' && isset($_GET['a']) && is_string($_GET['a']) && $_GET['a'] !== '') { $arg = $_GET['a']; }

    /* ---- API: index.php?r=api&a=<plugin>.<action> ---- */
    if ($base === 'api') {
        require_login();
        /* CSRF is mandatory for state-changing API calls (minting credits, writing data) */
        if (!csrf_check(false)) {
            return sf_response_json(array('ok' => false, 'error' => 'CSRF token invalid or missing. Reload the page and retry.'), 419);
        }
        $a = isset($_GET['a']) ? $_GET['a'] : ($arg ? $arg : '');
        $bits = explode('.', $a, 2);
        if (count($bits) !== 2) sf_json(array('ok' => false, 'error' => 'Bad api target'), 400);
        $in = sf_json_in();
        if (!$in) { $in = $_POST; }
        $res = plugins_api($bits[0], $bits[1], $in);
        return sf_response_json($res);
    }

    /* ---- auth POSTs ---- */
    if ($base === 'login' && $_POST) {
        csrf_check();
        $r = auth_login(sf_post('email'), sf_post('password'));
        if ($r['ok']) { flash('Welcome back, ' . $r['user']['name'] . '!', 'ok'); sf_redirect(sf_url('index.php?r=dashboard')); }
        flash($r['error'], 'err');
        sf_redirect(sf_url('index.php?r=login'));
    }
    if ($base === 'register' && $_POST) {
        csrf_check();
        $r = auth_register(sf_post('name'), sf_post('email'), sf_post('password'));
        if ($r['ok']) { flash('Account created. ' . (int) setting('credits_new_user', 150) . ' free credits added.', 'ok'); sf_redirect(sf_url('index.php?r=dashboard')); }
        flash($r['error'], 'err');
        sf_redirect(sf_url('index.php?r=register'));
    }
    if ($base === 'logout') { auth_logout(); sf_redirect(sf_url('index.php?r=home')); }

    /* ---- theme switch (feature 1: dark/light) ---- */
    if ($base === 'theme' && $_POST) {
        csrf_check();
        sf_theme_set(sf_post('v'));
        /* redirect back only to a same-origin URL; never echo a caller-supplied path */
        $ref = isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : '';
        $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
        $same = ($ref !== '' && $host !== '' && preg_match('#^https?://' . preg_quote($host, '#') . '/#i', $ref) === 1);
        sf_redirect($same ? $ref : sf_url('index.php?r=dashboard'));
    }
    if ($base === 'reset') {
        if ($_POST && sf_post('token')) {
            csrf_check();
            $r = auth_reset_do(sf_post('token'), sf_post('password'));
            flash($r['ok'] ? 'Password updated. Sign in.' : $r['error'], $r['ok'] ? 'ok' : 'err');
            if ($r['ok']) sf_redirect(sf_url('index.php?r=login'));
        } elseif ($_POST) {
            csrf_check();
            $r = auth_reset_request(sf_post('email'));
            flash($r['note'], 'ok');
        }
        return sf_view('reset', array('token' => sf_get('token')), 'Reset password');
    }

    /* ---- public ---- */
    if ($base === 'home') { return sf_view('home', array(), 'STORYFOUNDRY — From Idea to Finished Story'); }
    if ($base === 'login' || $base === 'register') {
        if (auth_check()) sf_redirect(sf_url('index.php?r=dashboard'));
        return sf_view($base, array(), ucfirst($base));
    }
    if ($base === 'pricing') { return sf_view('pricing', array(), 'Pricing'); }
    if ($base === 'page') { return sf_view('page', array('p' => $arg), 'STORYFOUNDRY'); }

    /* ---- authenticated ---- */
    if (in_array($base, array('dashboard', 'projects', 'project', 'assets', 'jobs', 'billing', 'account', 'marketplace', 'agency', 'clients', 'publishing', 'notifications', 'share'), true)) {
        require_login();
    }
    /* ---- approval workflow: Draft → Review → Changes → Approval → Final (feature 69) ---- */
    if ($base === 'project' && $_POST && (sf_post('approval_state') || sf_post('resolve_id'))) {
        require_login();
        csrf_check();
        $pid = sf_int($arg);
        $p = db_one('SELECT * FROM projects WHERE id=? AND deleted_at IS NULL', array($pid));
        if (!$p) { http_response_code(404); echo 'Not found'; exit; }
        if ($p['user_id'] != auth_id() && !auth_is_admin()) { http_response_code(403); echo 'Forbidden'; exit; }

        if (sf_post('resolve_id')) {
            $cid = sf_int(sf_post('resolve_id'));
            db_exec('UPDATE comments SET resolved=1 WHERE id=? AND project_id=?', array($cid, $pid));
            project_log_activity($pid, 'resolved a change request', '✅');
            flash('Change request marked resolved.', 'ok');
            sf_redirect(sf_url('index.php?r=project/' . $pid . '/review'));
        }

        $states = array('Draft', 'Review', 'Changes', 'Approval', 'Final');
        $st = (string) sf_post('approval_state');
        if (!in_array($st, $states, true)) { flash('Unknown approval state.', 'err'); sf_redirect(sf_url('index.php?r=project/' . $pid . '/review')); }
        db_exec('UPDATE projects SET approval=?, updated_at=? WHERE id=?', array($st, db_now(), $pid));
        $note = trim((string) sf_post('note'));
        if ($note !== '') {
            $u = auth_user();
            db_exec('INSERT INTO comments (project_id,user_id,who,body,kind,resolved,created_at) VALUES (?,?,?,?,?,?,?)',
                array($pid, auth_id(), $u ? $u['name'] : 'System', sf_sub($note, 0, 2000),
                    sf_post('note_kind') ? 'change_request' : 'comment', 0, db_now()));
        }
        project_log_activity($pid, 'moved approval to ' . $st, '✅');
        audit('project.approval', 'project#' . $pid . ' -> ' . $st, 'info');
        flash('Approval state set to ' . $st . '.', 'ok');
        sf_redirect(sf_url('index.php?r=project/' . $pid . '/review'));
    }

    switch ($base) {
        case 'dashboard':  return sf_view('dashboard', array(), 'Dashboard');
        case 'projects':   return sf_view('projects', array('action' => $arg), 'Projects');
        case 'project':    return sf_view('project', array('id' => sf_int($arg), 'stage' => isset($parts[2]) ? $parts[2] : null), 'Project');
        case 'assets':     return sf_view('assets', array(), 'Asset Library');
        case 'jobs':       return sf_view('jobs', array(), 'Jobs & Queue');
        case 'billing':    return sf_view('billing', array('tab' => $arg), 'Billing & Credits');
        case 'account':    return sf_view('account', array('tab' => $arg), 'Settings');
        case 'notifications': return sf_view('notifications', array(), 'Notifications');
        case 'share':      return sf_view('share', array('token' => $arg), 'Shared project');
    }

    /* ---- per-addon studio pages: index.php?r=studio  /  ?r=studio/<addon>&pid=N ---- */
    if ($base === 'studio') {
        require_login();
        return sf_view('studio', array('tool' => $arg ? (string) $arg : '', 'pid' => sf_int(sf_get('pid'))), 'Studios');
    }

    /* ---- admin ---- */
    if ($base === 'admin') {
        require_admin();
        $p = sf_get('p', $arg);
        return sf_view('admin', array('p' => $p), 'Admin');
    }

    /* ---- signed file delivery (feature 38) ---- */
    if ($base === 'file') {
        $rel = (string) sf_get('p');
        if (!storage_verify_signed($rel, sf_get('e'), sf_get('s'))) { http_response_code(403); echo 'Link expired'; exit; }
        /* containment: only files that live under uploads/ may ever be served */
        $full = sf_safe_path($rel, array(SF_ROOT . '/uploads', SF_ROOT . '/storage/private'));
        if (!$full) { http_response_code(404); echo 'Not found'; exit; }
        $mime = function_exists('mime_content_type') ? mime_content_type($full) : 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . rawurlencode(basename($full)) . '"');
        readfile($full); exit;
    }

    /* ---- procedural frame endpoint (used by image/video/thumbnail addons) ---- */
    if ($base === 'frame') {
        $seed = (string) sf_get('seed', 'frame');
        $style = (string) sf_get('style', 'cinematic');
        $subject = (string) sf_get('subject', 'scene');
        $w = sf_int(sf_get('w'), 1280); $h = sf_int(sf_get('h'), 720);
        $svg = mockai_frame_svg($seed, array('style' => $style, 'subject' => $subject, 'w' => $w, 'h' => $h,
            'time' => sf_get('time', ''), 'text' => sf_get('text', '')));
        header('Content-Type: image/svg+xml');
        header('Cache-Control: public, max-age=86400');
        echo $svg; exit;
    }

    /* ---- addon-registered routes ---- */
    $pluginRoute = sf_plugin_route($route);
    if ($pluginRoute) return $pluginRoute;

    return sf_view('404', array('route' => $route), 'Not found');
}
function sf_plugin_route($route) {
    $rows = plugins_db_rows();
    foreach ($rows as $key => $row) {
        if (empty($row['enabled'])) continue;
        $meta = plugins_meta($key);
        if (!$meta || empty($meta['routes'])) continue;
        foreach ($meta['routes'] as $rt) {
            $r = is_array($rt) ? $rt['route'] : $rt;
            if ($r === $route || rtrim($r, '/*') === $route) {
                $p = plugin_instance($key);
                if ($p && method_exists($p, 'route')) {
                    return $p->route($route, $_GET, $_POST);
                }
            }
        }
    }
    return null;
}

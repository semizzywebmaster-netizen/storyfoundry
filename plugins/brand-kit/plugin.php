<?php
/**
 * BRAND KIT — spec feature 70
 * Logos, colours, fonts, watermark, intro/outro, default style, templates.
 * Brand assets are stored under uploads/brand/ and reused across projects.
 * Watermarking is REAL when the GD extension exists, otherwise it reports
 * NOT CONFIGURED — it never pretends to have stamped an image.
 */
class SFPlugin_brand_kit extends PluginBase {

    /* ---------------------------------------------------------- data */
    private function kits($kind = 'kit') {
        return db_all('SELECT * FROM brand_kits WHERE user_id=? AND kind=? ORDER BY is_default DESC, id DESC LIMIT 50',
            array(auth_id(), $kind));
    }
    private function kit($id) {
        $k = db_one('SELECT * FROM brand_kits WHERE id=? AND user_id=?', array((int) $id, auth_id()));
        return $k ? $k : null;
    }
    public static function colors($k) {
        $d = array('primary' => '#c77700', 'secondary' => '#1b1f27', 'accent' => '#6ea8fe', 'bg' => '#0e1116', 'text' => '#f2f4f8');
        $c = json_decode((string) (is_array($k) && isset($k['colors']) ? $k['colors'] : ''), true);
        if (is_array($c)) { foreach ($d as $key => $v) { if (isset($c[$key]) && preg_match('/^#[0-9a-fA-F]{6}$/', $c[$key])) { $d[$key] = $c[$key]; } } }
        return $d;
    }
    public static function fonts($k) {
        $d = array('heading' => 'Display', 'body' => 'System');
        $f = json_decode((string) (is_array($k) && isset($k['fonts']) ? $k['fonts'] : ''), true);
        if (is_array($f)) { foreach ($d as $key => $v) { if (!empty($f[$key])) { $d[$key] = (string) $f[$key]; } } }
        return $d;
    }
    /** Kit applied to a project (stored in project_data stage 'brandkit'). */
    public static function for_project($pid) {
        $d = stage_data((int) $pid, 'brandkit', array());
        if (!is_array($d) || empty($d['kit_id'])) return null;
        $k = db_one('SELECT * FROM brand_kits WHERE id=?', array((int) $d['kit_id']));
        return $k ? $k : null;
    }

    /* --------------------------------------------------------- route */
    public function route($route, $get = array(), $post = array()) {
        if ($route !== 'brandkit') return null;
        $uid = auth_id();
        $editId = (int) (isset($get['id']) ? $get['id'] : 0);
        $kit = $editId ? $this->kit($editId) : null;
        $plan = auth_user() ? (string) auth_user()['plan'] : 'free';
        $allowed = feature_enabled('brandkit') && feature_allows_plan('brandkit', $plan);

        $h = '<h1>Brand Kit</h1>'
           . '<p class="muted" style="margin:0 0 14px">Logos, colours, fonts, watermark, intro/outro and style presets — reusable on every project.</p>';

        if (!$allowed) {
            $h .= '<div class="card"><b style="font-size:13.5px">Brand Kit needs the Pro plan</b>'
                . '<div class="dim" style="font-size:12px;margin-top:6px">Your plan: <b>' . e(ucfirst($plan)) . '</b>. '
                . 'Upgrade in <a href="' . e(sf_url('index.php?r=billing/plans')) . '">Billing → Plans</a> to unlock brand kits.</div></div>';
            return $this->wrap($h);
        }

        /* editor */
        $h .= '<div class="grid" style="grid-template-columns:1fr 340px;align-items:start">';
        $h .= '<div class="card"><h3>' . ($kit ? 'Edit “' . e($kit['name']) . '”' : 'New brand kit') . '</h3>'
            . ui_form_open(sf_url('index.php?r=brandkit' . ($kit ? '?id=' . (int) $kit['id'] : '')), 'post', 'enctype="multipart/form-data"')
            . '<input type="hidden" name="act" value="save">'
            . ($kit ? '<input type="hidden" name="id" value="' . (int) $kit['id'] . '">' : '')
            . '<label class="fl">Kit name<input name="name" required value="' . e($kit ? $kit['name'] : 'My brand') . '"></label>'
            . '<div class="row" style="gap:10px;margin-top:10px;flex-wrap:wrap">'
            . $this->colorField('primary', 'Primary', $kit) . $this->colorField('secondary', 'Secondary', $kit)
            . $this->colorField('accent', 'Accent', $kit) . $this->colorField('bg', 'Background', $kit)
            . $this->colorField('text', 'Text', $kit)
            . '</div>'
            . '<div class="row" style="gap:10px;margin-top:10px;flex-wrap:wrap">'
            . '<label class="fl" style="flex:1;min-width:150px">Heading font' . ui_select('font_heading', $this->fontOptions(), self::fonts($kit)['heading']) . '</label>'
            . '<label class="fl" style="flex:1;min-width:150px">Body font' . ui_select('font_body', $this->fontOptions(), self::fonts($kit)['body']) . '</label>'
            . '<label class="fl" style="flex:1;min-width:150px">Default style' . ui_select('style', array('cinematic' => 'Cinematic', 'documentary' => 'Documentary', 'anime' => 'Anime', 'pixar' => 'Pixar 3D', 'realistic' => 'Realistic', 'comic' => 'Comic'), $kit ? $kit['style'] : 'cinematic') . '</label>'
            . '</div>'
            . '<div class="row" style="gap:10px;margin-top:10px;flex-wrap:wrap">'
            . '<label class="fl" style="flex:1;min-width:150px">Watermark position' . ui_select('wm_pos', array('bottom-right' => 'Bottom right', 'bottom-left' => 'Bottom left', 'top-right' => 'Top right', 'top-left' => 'Top left', 'center' => 'Centre'), $kit ? $kit['wm_pos'] : 'bottom-right') . '</label>'
            . '<label class="fl" style="flex:1;min-width:150px">Watermark opacity (%)<input type="number" name="wm_opacity" min="5" max="100" value="' . (int) ($kit ? $kit['wm_opacity'] : 60) . '"></label>'
            . '</div>'
            . '<div class="row" style="gap:10px;margin-top:10px;flex-wrap:wrap">'
            . '<label class="fl" style="flex:1;min-width:150px">Logo (png/jpg/webp)<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label>'
            . '<label class="fl" style="flex:1;min-width:150px">Watermark (png with alpha)<input type="file" name="watermark" accept="image/png,image/webp"></label>'
            . '</div>'
            . '<div class="row" style="gap:10px;margin-top:10px;flex-wrap:wrap">'
            . '<label class="fl" style="flex:1;min-width:150px">Intro clip (mp4/webm)<input type="file" name="intro" accept="video/mp4,video/webm"></label>'
            . '<label class="fl" style="flex:1;min-width:150px">Outro clip (mp4/webm)<input type="file" name="outro" accept="video/mp4,video/webm"></label>'
            . '</div>'
            . '<div class="row" style="gap:8px;margin-top:14px">'
            . '<button class="btn pri">Save kit</button> '
            . '<a class="btn gho" href="' . e(sf_url('index.php?r=brandkit')) . '">Cancel</a>'
            . ($kit ? ' <button class="btn gho" name="act" value="template">Save as template</button>' : '')
            . '</div></form></div>';

        /* list */
        $h .= '<div><div class="card"><h3>Your kits</h3>';
        $kits = $this->kits('kit');
        if (!$kits) {
            $h .= ui_empty('🎨', 'No brand kit yet.', 'Create one — it applies to thumbnails, exports and social packs.');
        } else {
            foreach ($kits as $k) {
                $c = self::colors($k);
                $h .= '<div class="card" style="margin:0 0 10px;box-shadow:none">'
                    . '<div class="row" style="justify-content:space-between"><b style="font-size:13px">' . e($k['name']) . '</b>'
                    . ($k['is_default'] ? '<span class="chip chip-ok">default</span>' : '') . '</div>'
                    . '<div class="row" style="gap:5px;margin:8px 0">';
                foreach ($c as $cn => $cv) {
                    $h .= '<span title="' . e($cn) . '" style="width:22px;height:22px;border-radius:6px;background:' . e($cv) . ';border:1px solid #2a3038"></span>';
                }
                $h .= '</div>'
                    . '<div class="dim" style="font-size:11.5px">' . e(ucfirst($k['style'])) . ' · ' . e(self::fonts($k)['heading']) . ' · ' . e(ui_time_ago($k['created_at'])) . '</div>'
                    . '<div class="row" style="gap:5px;margin-top:9px">'
                    . '<a class="btn xs" href="' . e(sf_url('index.php?r=brandkit?id=' . (int) $k['id'])) . '">Edit</a>'
                    . ($k['is_default'] ? '' : '<button class="btn xs gho" data-api="brand-kit.default" data-payload=\'{"id":' . (int) $k['id'] . '}\' data-reload="1">Set default</button>')
                    . '<button class="btn xs gho" data-api="brand-kit.dup" data-payload=\'{"id":' . (int) $k['id'] . '}\' data-reload="1">Duplicate</button>'
                    . '<button class="btn xs gho dgr" data-api="brand-kit.del" data-payload=\'{"id":' . (int) $k['id'] . '}\' data-reload="1">Delete</button>'
                    . '</div></div>';
            }
        }
        $h .= '</div>';

        /* templates */
        $h .= '<div class="card"><h3>Templates</h3>';
        $tpl = $this->kits('template');
        if (!$tpl) {
            $h .= '<div class="dim" style="font-size:12px">Save any kit as a template to reuse its look.</div>';
        } else {
            foreach ($tpl as $t) {
                $c = self::colors($t);
                $h .= '<div class="row" style="justify-content:space-between;gap:6px;padding:6px 0;border-bottom:1px solid #20252d">'
                    . '<span class="row" style="gap:6px"><span style="width:16px;height:16px;border-radius:5px;background:' . e($c['primary']) . ';display:inline-block"></span>'
                    . '<b style="font-size:12.5px">' . e($t['name']) . '</b></span>'
                    . '<button class="btn xs" data-api="brand-kit.use_template" data-payload=\'{"id":' . (int) $t['id'] . '}\' data-reload="1">Load</button></div>';
            }
        }
        $h .= '</div>';

        /* apply to a project + watermark tool */
        $h .= '<div class="card"><h3>Apply to a project</h3>';
        $projs = db_all('SELECT id,title FROM projects WHERE user_id=? AND deleted_at IS NULL ORDER BY updated_at DESC LIMIT 50', array($uid));
        if (!$projs) {
            $h .= '<div class="dim" style="font-size:12px">Create a project first.</div>';
        } else {
            foreach ($projs as $p) {
                $cur = self::for_project((int) $p['id']);
                $h .= '<div class="row" style="justify-content:space-between;gap:6px;padding:6px 0;border-bottom:1px solid #20252d">'
                    . '<span style="font-size:12.5px">' . e($p['title']) . '</span>'
                    . '<span class="row" style="gap:5px">'
                    . ($cur ? '<span class="chip">' . e($cur['name']) . '</span>' : '')
                    . '<button class="btn xs gho" data-api="brand-kit.apply" data-payload=\'{"project_id":' . (int) $p['id'] . '}\' data-reload="1">Apply default</button>'
                    . '</span></div>';
            }
        }
        $h .= '</div>';

        $h .= '<div class="card"><h3>Watermark an image</h3>'
            . '<div class="dim" style="font-size:11.5px;margin-bottom:8px">Uses your default kit’s watermark. '
            . (function_exists('imagecreatefromstring') ? '<span class="chip chip-ok">GD available — REAL</span>' : '<span class="chip">GD missing — NOT CONFIGURED</span>') . '</div>';
        $imgs = db_all("SELECT id,name,path FROM assets WHERE user_id=? AND kind='image' ORDER BY id DESC LIMIT 20", array($uid));
        if (!$imgs) {
            $h .= '<div class="dim" style="font-size:12px">No images yet.</div>';
        } else {
            foreach ($imgs as $im) {
                $h .= '<div class="row" style="justify-content:space-between;gap:6px;padding:5px 0;border-bottom:1px solid #20252d">'
                    . '<span style="font-size:12px">' . e($im['name']) . '</span>'
                    . '<button class="btn xs" data-api="brand-kit.watermark" data-payload=\'{"id":' . (int) $im['id'] . '}\' data-reload="1">Stamp</button></div>';
            }
        }
        $h .= '</div></div></div>';
        return $this->wrap($h);
    }

    private function wrap($h) {
        return sf_view('plugin', array('html' => $h), 'Brand Kit');
    }
    private function colorField($key, $label, $kit) {
        $c = self::colors($kit);
        return '<label class="fl" style="flex:0 0 108px">' . e($label)
            . '<input type="color" name="c_' . e($key) . '" value="' . e($c[$key]) . '" style="height:34px;padding:2px"></label>';
    }
    private function fontOptions() {
        return array('Display' => 'Display (bold)', 'Serif' => 'Serif', 'System' => 'System UI',
            'Mono' => 'Monospace', 'Handwritten' => 'Handwritten', 'Condensed' => 'Condensed');
    }

    /* ----------------------------------------------------------- api */
    public function api($action, $in) {
        $uid = auth_id();
        $plan = auth_user() ? (string) auth_user()['plan'] : 'free';
        if (!feature_enabled('brandkit') || !feature_allows_plan('brandkit', $plan)) {
            return array('ok' => false, 'error' => 'Brand Kit requires the Pro plan or higher');
        }
        $id = (int) (isset($in['id']) ? $in['id'] : 0);

        if ($action === 'save')            { return $this->save($in, $uid); }
        if ($action === 'template')        { return $this->saveAsTemplate($in, $uid); }
        if ($action === 'use_template')    { return $this->loadTemplate($id, $uid); }
        if ($action === 'default')         { return $this->setDefault($id, $uid); }
        if ($action === 'dup')             { return $this->duplicate($id, $uid); }
        if ($action === 'del')             { return $this->delete($id, $uid); }
        if ($action === 'apply')           { return $this->apply((int) (isset($in['project_id']) ? $in['project_id'] : 0), $uid); }
        if ($action === 'watermark')       { return $this->watermark($id, $uid); }
        return array('ok' => false, 'error' => 'Unknown action');
    }

    private function save($in, $uid) {
        $id = (int) (isset($in['id']) ? $in['id'] : 0);
        $kit = $id ? $this->kit($id) : null;
        $max = (int) $this->config('max_kits', 12);
        if (!$kit && (int) db_val('SELECT COUNT(*) FROM brand_kits WHERE user_id=? AND kind=?', array($uid, 'kit'), 0) >= $max) {
            return array('ok' => false, 'error' => 'You can keep up to ' . $max . ' brand kits');
        }
        $colors = array();
        foreach (array('primary', 'secondary', 'accent', 'bg', 'text') as $cn) {
            $v = isset($in['c_' . $cn]) ? (string) $in['c_' . $cn] : '';
            $colors[$cn] = preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : self::colors(null)[$cn];
        }
        $fonts = array(
            'heading' => in_array((string) (isset($in['font_heading']) ? $in['font_heading'] : ''), array_keys($this->fontOptions()), true) ? (string) $in['font_heading'] : 'Display',
            'body'    => in_array((string) (isset($in['font_body']) ? $in['font_body'] : ''), array_keys($this->fontOptions()), true) ? (string) $in['font_body'] : 'System',
        );
        $style = preg_replace('/[^a-z]/', '', strtolower((string) (isset($in['style']) ? $in['style'] : 'cinematic')));
        if ($style === '') { $style = 'cinematic'; }
        $wmPos = in_array((string) (isset($in['wm_pos']) ? $in['wm_pos'] : ''), array('bottom-right', 'bottom-left', 'top-right', 'top-left', 'center'), true) ? (string) $in['wm_pos'] : 'bottom-right';
        $wmOp = (int) (isset($in['wm_opacity']) ? $in['wm_opacity'] : 60);
        $wmOp = max(5, min(100, $wmOp));
        $name = trim((string) (isset($in['name']) ? $in['name'] : ''));
        if ($name === '') { $name = 'My brand'; }

        $logo = $kit ? $kit['logo'] : null; $wm = $kit ? $kit['watermark'] : null;
        $intro = $kit ? $kit['intro'] : null; $outro = $kit ? $kit['outro'] : null;
        foreach (array('logo' => array('brand', array('jpg', 'jpeg', 'png', 'webp')),
                       'watermark' => array('brand', array('png', 'webp')),
                       'intro' => array('brand/video', array('mp4', 'webm')),
                       'outro' => array('brand/video', array('mp4', 'webm'))) as $field => $spec) {
            if (empty($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) { continue; }
            $up = sf_upload($field, $spec[0], $spec[1]);
            if (empty($up['ok'])) { return array('ok' => false, 'error' => ucfirst($field) . ': ' . $up['error']); }
            $$field = $up['path'];
        }

        $now = db_now();
        if ($kit) {
            db_exec('UPDATE brand_kits SET name=?,logo=?,watermark=?,intro=?,outro=?,colors=?,fonts=?,style=?,wm_pos=?,wm_opacity=?,updated_at=? WHERE id=? AND user_id=?',
                array($name, $logo, $wm, $intro, $outro, json_encode($colors), json_encode($fonts), $style, $wmPos, $wmOp, $now, (int) $kit['id'], $uid));
        } else {
            db_exec('INSERT INTO brand_kits (user_id,name,logo,watermark,intro,outro,colors,fonts,style,wm_pos,wm_opacity,kind,is_default,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                array($uid, $name, $logo, $wm, $intro, $outro, json_encode($colors), json_encode($fonts), $style, $wmPos, $wmOp,
                    'kit', (int) db_val('SELECT COUNT(*) FROM brand_kits WHERE user_id=? AND kind=?', array($uid, 'kit'), 0) === 0 ? 1 : 0, $now, $now));
        }
        audit('brandkit.save', $name, 'info');
        return array('ok' => true, 'message' => 'Brand kit saved', 'reload' => true);
    }

    private function saveAsTemplate($in, $uid) {
        $id = (int) (isset($in['id']) ? $in['id'] : 0);
        $k = $this->kit($id);
        if (!$k) return array('ok' => false, 'error' => 'Kit not found');
        $now = db_now();
        db_exec('INSERT INTO brand_kits (user_id,name,logo,watermark,intro,outro,colors,fonts,style,wm_pos,wm_opacity,kind,is_default,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0,?,?)',
            array($uid, $k['name'] . ' template', $k['logo'], $k['watermark'], $k['intro'], $k['outro'], $k['colors'], $k['fonts'], $k['style'], $k['wm_pos'], (int) $k['wm_opacity'], 'template', $now, $now));
        return array('ok' => true, 'message' => 'Template saved', 'reload' => true);
    }
    private function loadTemplate($id, $uid) {
        $t = db_one('SELECT * FROM brand_kits WHERE id=? AND user_id=? AND kind=?', array($id, $uid, 'template'));
        if (!$t) return array('ok' => false, 'error' => 'Template not found');
        $now = db_now();
        db_exec('INSERT INTO brand_kits (user_id,name,logo,watermark,intro,outro,colors,fonts,style,wm_pos,wm_opacity,kind,is_default,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0,?,?)',
            array($uid, preg_replace('/ template$/', '', (string) $t['name']), $t['logo'], $t['watermark'], $t['intro'], $t['outro'], $t['colors'], $t['fonts'], $t['style'], $t['wm_pos'], (int) $t['wm_opacity'], 'kit', $now, $now));
        return array('ok' => true, 'message' => 'Template loaded as a new kit', 'reload' => true);
    }
    private function setDefault($id, $uid) {
        $k = $id ? $this->kit($id) : db_one('SELECT * FROM brand_kits WHERE user_id=? AND kind=? ORDER BY id DESC LIMIT 1', array($uid, 'kit'));
        if (!$k) return array('ok' => false, 'error' => 'Kit not found');
        db_exec('UPDATE brand_kits SET is_default=0 WHERE user_id=? AND kind=?', array($uid, 'kit'));
        db_exec('UPDATE brand_kits SET is_default=1 WHERE id=? AND user_id=?', array((int) $k['id'], $uid));
        return array('ok' => true, 'message' => 'Default kit updated', 'reload' => true);
    }
    private function duplicate($id, $uid) {
        $k = $this->kit($id);
        if (!$k) return array('ok' => false, 'error' => 'Kit not found');
        $now = db_now();
        db_exec('INSERT INTO brand_kits (user_id,name,logo,watermark,intro,outro,colors,fonts,style,wm_pos,wm_opacity,kind,is_default,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0,?,?)',
            array($uid, $k['name'] . ' copy', $k['logo'], $k['watermark'], $k['intro'], $k['outro'], $k['colors'], $k['fonts'], $k['style'], $k['wm_pos'], (int) $k['wm_opacity'], 'kit', $now, $now));
        return array('ok' => true, 'message' => 'Kit duplicated', 'reload' => true);
    }
    private function delete($id, $uid) {
        $k = $this->kit($id);
        if (!$k) return array('ok' => false, 'error' => 'Kit not found');
        db_exec('DELETE FROM brand_kits WHERE id=? AND user_id=?', array((int) $k['id'], $uid));
        if ($k['is_default']) {
            $next = db_one('SELECT id FROM brand_kits WHERE user_id=? AND kind=? ORDER BY id DESC LIMIT 1', array($uid, 'kit'));
            if ($next) { db_exec('UPDATE brand_kits SET is_default=1 WHERE id=?', array((int) $next['id'])); }
        }
        return array('ok' => true, 'message' => 'Kit deleted', 'reload' => true);
    }
    private function apply($pid, $uid) {
        $p = db_one('SELECT * FROM projects WHERE id=? AND user_id=?', array($pid, $uid));
        if (!$p) return array('ok' => false, 'error' => 'Project not found');
        $k = db_one('SELECT * FROM brand_kits WHERE user_id=? AND kind=? AND is_default=1 LIMIT 1', array($uid, 'kit'));
        if (!$k) { $k = db_one('SELECT * FROM brand_kits WHERE user_id=? AND kind=? ORDER BY id DESC LIMIT 1', array($uid, 'kit')); }
        if (!$k) return array('ok' => false, 'error' => 'Create and save a brand kit first');
        stage_save($pid, 'brandkit', array('kit_id' => (int) $k['id'], 'name' => $k['name'], 'style' => $k['style'], 't' => db_now()));
        project_touch($pid);
        return array('ok' => true, 'message' => 'Brand “' . $k['name'] . '” applied to ' . $p['title'], 'reload' => true);
    }

    /** Stamp an image asset with the default kit's watermark. REAL only when GD exists. */
    private function watermark($assetId, $uid) {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagecopymerge')) {
            return array('ok' => false, 'error' => 'Watermarking needs the PHP GD extension — NOT CONFIGURED on this server. Enable GD in cPanel → Select PHP Version → Extensions.');
        }
        $a = db_one('SELECT * FROM assets WHERE id=? AND user_id=?', array($assetId, $uid));
        if (!$a) return array('ok' => false, 'error' => 'Image not found');
        $k = db_one('SELECT * FROM brand_kits WHERE user_id=? AND kind=? AND is_default=1 LIMIT 1', array($uid, 'kit'));
        if (!$k) { $k = db_one('SELECT * FROM brand_kits WHERE user_id=? AND kind=? ORDER BY id DESC LIMIT 1', array($uid, 'kit')); }
        if (!$k || empty($k['watermark'])) return array('ok' => false, 'error' => 'Add a watermark PNG to your brand kit first');

        $src = sf_safe_path($a['path'], array(SF_ROOT . '/uploads'));
        $wmf = sf_safe_path($k['watermark'], array(SF_ROOT . '/uploads'));
        if (!$src || !$wmf) return array('ok' => false, 'error' => 'File not readable');

        $dst = @imagecreatefromstring((string) @file_get_contents($src));
        $wm  = @imagecreatefromstring((string) @file_get_contents($wmf));
        if (!$dst || !$wm) {
            if ($dst) { imagedestroy($dst); }
            return array('ok' => false, 'error' => 'Could not decode image (GD supports jpg/png/webp/gif)');
        }
        $dw = imagesx($dst); $dh = imagesy($dst);
        $targetW = (int) max(48, min((int) ($dw * 0.22), 480));
        $ratio = imagesy($wm) > 0 ? imagesx($wm) / imagesy($wm) : 1;
        $ww = (int) $targetW; $wh = (int) max(1, round($targetW / ($ratio ? $ratio : 1)));
        $small = imagecreatetruecolor($ww, $wh);
        imagealphablending($small, false); imagesavealpha($small, true);
        imagecopyresampled($small, $wm, 0, 0, 0, 0, $ww, $wh, imagesx($wm), imagesy($wm));
        $pad = (int) max(8, round($dw * 0.02));
        $pos = (string) $k['wm_pos'];
        $dx = strpos($pos, 'right') !== false ? $dw - $ww - $pad : (strpos($pos, 'left') !== false ? $pad : (int) (($dw - $ww) / 2));
        $dy = strpos($pos, 'bottom') !== false ? $dh - $wh - $pad : (strpos($pos, 'top') !== false ? $pad : (int) (($dh - $wh) / 2));
        imagecopymerge($dst, $small, (int) max(0, $dx), (int) max(0, $dy), 0, 0, $ww, $wh, (int) max(5, min(100, (int) $k['wm_opacity'])));

        $dir = SF_ROOT . '/uploads/brand';
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        $out = 'uploads/brand/wm-' . sf_token(10) . '.png';
        if (!@imagepng($dst, SF_ROOT . '/' . $out)) {
            imagedestroy($dst); imagedestroy($wm); imagedestroy($small);
            return array('ok' => false, 'error' => 'Could not write the watermarked image');
        }
        imagedestroy($dst); imagedestroy($wm); imagedestroy($small);
        project_asset((int) $a['project_id'], 'image', 'Watermarked — ' . $a['name'], $out, array('brand' => $k['name'], 'source' => (int) $a['id']));
        return array('ok' => true, 'message' => 'Watermark applied (REAL · GD)', 'status' => 'REAL', 'reload' => true, 'path' => $out);
    }

    /* ------------------------------------------------------- extras */
    public function menu() {
        return array(array('route' => 'brandkit', 'label' => 'Brand Kit', 'icon' => '🎨', 'group' => 'main', 'perm' => 'user'));
    }
    public function dashboard() {
        $k = db_one('SELECT * FROM brand_kits WHERE user_id=? AND kind=? AND is_default=1 LIMIT 1', array(auth_id(), 'kit'));
        if (!$k) { return array(); }
        $c = self::colors($k);
        $h = '<div class="card"><h3>Brand Kit</h3><div class="row" style="gap:8px;align-items:center">'
           . '<b style="font-size:13px">' . e($k['name']) . '</b>';
        foreach ($c as $cv) { $h .= '<span style="width:18px;height:18px;border-radius:5px;background:' . e($cv) . ';border:1px solid #2a3038"></span>'; }
        $h .= '</div><a class="btn sm gho" style="margin-top:10px" href="' . e(sf_url('index.php?r=brandkit')) . '">Open Brand Kit</a></div>';
        return array($h);
    }
    public function admin($input) {
        $n = (int) db_val('SELECT COUNT(*) FROM brand_kits', array(), 0);
        $def = (int) db_val("SELECT COUNT(*) FROM brand_kits WHERE kind='kit' AND is_default=1", array(), 0);
        $tpl = (int) db_val("SELECT COUNT(*) FROM brand_kits WHERE kind='template'", array(), 0);
        return '<div class="card"><h3>Brand Kit</h3><div class="row" style="gap:18px">'
            . ui_kpi((string) $n, 'Brand kits') . ui_kpi((string) $def, 'Defaults set') . ui_kpi((string) $tpl, 'Templates')
            . '</div><div class="dim" style="font-size:11.5px;margin-top:10px">Watermarking status: '
            . (function_exists('imagecreatefromstring') ? '<span class="chip chip-ok">REAL · GD available</span>' : '<span class="chip">NOT CONFIGURED · GD missing</span>')
            . '</div></div>';
    }
}

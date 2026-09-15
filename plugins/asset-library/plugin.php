<?php
class SFPlugin_asset_library extends PluginBase {
    public function stage($project, $data) {
        $rows = db_all('SELECT * FROM assets WHERE project_id=? ORDER BY id DESC LIMIT 300', array((int) $project['id']));
        return $this->grid($rows, 'Project assets');
    }
    public function dashboard() {
        $rows = db_all('SELECT * FROM assets WHERE user_id=? AND fav=1 ORDER BY id DESC LIMIT 6', array(auth_id()));
        if (!$rows) { return array(); }
        return array('<div class="card"><h3>★ Favourite assets</h3>' . $this->grid($rows, '') . '</div>');
    }
    private function grid($rows, $title) {
        if (!$rows) return ui_empty('🗂️', 'No assets yet. Generate something and it lands here automatically.');
        $h = '<div class="assetgrid">';
        foreach ($rows as $a) {
            $isImg = $a['kind'] === 'image';
            $h .= '<div class="acard">'
                . '<div class="thumb">' . ($isImg ? '<img src="' . e(sf_url($a['path'])) . '" alt="">' : '<span style="font-size:26px">' . $this->icon($a['kind']) . '</span>') . '</div>'
                . '<div class="meta"><b>' . e($a['name']) . '</b><span class="dim" style="font-size:10.5px">' . e($a['kind']) . ' · ' . e(ui_time_ago($a['created_at'])) . '</span></div>'
                . '<div class="row" style="gap:4px;padding:0 8px 8px">'
                . '<a class="btn xs" href="' . e(storage_signed_url($a['path'], 3600)) . '">Get</a>'
                . '<button class="btn xs gho" data-api="asset-library.fav" data-payload=\'{"id":' . (int) $a['id'] . '}\' data-reload="1">' . ($a['fav'] ? '★' : '☆') . '</button>'
                . '<button class="btn xs gho dgr" data-api="asset-library.del" data-payload=\'{"id":' . (int) $a['id'] . '}\' data-reload="1">🗑</button>'
                . '</div></div>';
        }
        return $h . '</div>';
    }
    private function icon($k) {
        $m = array('image' => '🖼️', 'audio' => '🎵', 'video' => '🎬', 'other' => '📄');
        return isset($m[$k]) ? $m[$k] : '📄';
    }
    public function api($action, $in) {
        $id = (int) (isset($in['id']) ? $in['id'] : 0);
        $a = db_one('SELECT * FROM assets WHERE id=?', array($id));
        if (!$a || $a['user_id'] != auth_id()) return array('ok' => false, 'error' => 'Asset not found');
        if ($action === 'fav') { db_exec('UPDATE assets SET fav=? WHERE id=?', array($a['fav'] ? 0 : 1, $id)); return array('ok' => true, 'reload' => true); }
        if ($action === 'del') { db_exec('DELETE FROM assets WHERE id=?', array($id)); return array('ok' => true, 'message' => 'Asset removed', 'reload' => true); }
        return array('ok' => false, 'error' => 'Unknown action');
    }
}

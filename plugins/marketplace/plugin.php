<?php
class SFPlugin_marketplace extends PluginBase {
    public function route($route, $get, $post) {
        require_login();
        if ($_POST && sf_post('act') === 'list_item') {
            csrf_check();
            db_exec('INSERT INTO marketplace_items (seller_id,title,cat,description,price,status,created_at) VALUES (?,?,?,?,?,?,?)',
                array(auth_id(), sf_post('title'), sf_post('cat'), sf_post('description'), (float) sf_post('price'), 'active', db_now()));
            flash('Item listed', 'ok'); sf_redirect(sf_url('index.php?r=marketplace'));
        }
        if (isset($get['buy'])) {
            $it = db_one('SELECT * FROM marketplace_items WHERE id=? AND status=?', array((int) $get['buy'], 'active'));
            if (!$it) { flash('Item not available', 'err'); }
            else {
                $fee = (float) $it['price'] * ((float) setting('marketplace_commission', 15)) / 100;
                $r = wallet_spend(auth_id(), (float) $it['price'], 'marketplace ' . $it['title'], 'MK-' . strtoupper(sf_token(5)));
                if (!$r['ok']) { flash('Purchase failed: ' . $r['error'], 'err'); }
                else {
                    db_exec('INSERT INTO marketplace_orders (item_id,buyer_id,amount,commission,status,created_at) VALUES (?,?,?,?,?,?)',
                        array((int) $it['id'], auth_id(), (float) $it['price'], $fee, 'paid', db_now()));
                    /* seller proceeds are credited to their in-platform wallet (no withdrawal path) */
                    wallet_deposit((int) $it['seller_id'], (float) $it['price'] - $fee, 'marketplace sale: ' . $it['title'], 'MK-SALE-' . (int) $it['id']);
                    audit('marketplace.purchase', 'item#' . $it['id'], 'info');
                    flash('Purchased — ' . e($it['title']), 'ok');
                }
            }
            sf_redirect(sf_url('index.php?r=marketplace'));
        }
        $items = db_all('SELECT * FROM marketplace_items WHERE status=? ORDER BY id DESC LIMIT 60', array('active'));
        $h = '<h1>Marketplace</h1><div class="grid" style="grid-template-columns:1fr 300px;align-items:start">';
        $h .= '<div>' . ($items ? '<div class="grid g3">' : ui_empty('🛒', 'No items listed yet.'));
        foreach ($items as $it) {
            $seller = db_one('SELECT name FROM users WHERE id=?', array((int) $it['seller_id']));
            $h .= '<div class="card" style="margin:0"><div class="row" style="justify-content:space-between"><b style="font-size:14px">' . e($it['title']) . '</b>'
                . '<span class="chip">' . e($it['cat']) . '</span></div>'
                . '<div class="muted" style="font-size:12.5px;margin:8px 0">' . e($it['description']) . '</div>'
                . '<div class="row" style="justify-content:space-between;align-items:center">'
                . '<b class="mono">' . e(sf_money($it['price'])) . '</b>'
                . '<a class="btn xs pri" href="' . e(sf_url('index.php?r=marketplace&buy=' . (int) $it['id'])) . '">Buy with wallet</a></div>'
                . '<div class="dim" style="font-size:11px;margin-top:7px">by ' . e($seller ? $seller['name'] : 'unknown') . ' · platform fee ' . e(setting('marketplace_commission', 15)) . '%</div></div>';
        }
        $h .= ($items ? '</div>' : '') . '</div>';
        $h .= '<div class="card"><h3>Sell your work</h3>'
            . ui_form_open(sf_url('index.php?r=marketplace')) . '<input type="hidden" name="act" value="list_item">'
            . '<label class="fl">Title<input name="title" required></label>'
            . '<label class="fl" style="margin-top:9px">Category' . ui_select('cat', array('template' => 'Template', 'script' => 'Script', 'preset' => 'Preset', 'character' => 'Character', 'plugin' => 'Plugin')) . '</label>'
            . '<label class="fl" style="margin-top:9px">Description<textarea name="description"></textarea></label>'
            . '<label class="fl" style="margin-top:9px">Price<input name="price" type="number" step="0.01" value="5000"></label>'
            . '<button class="btn pri blk" style="margin-top:11px">List item</button></form>'
            . '<div class="dim" style="font-size:11.5px;margin-top:10px">Purchases are settled in wallet credit — spendable inside STORYFOUNDRY only, no withdrawals.</div></div></div>';
        return sf_view('plugin', array('html' => $h), 'Marketplace');
    }
    public function menu() { return array(array('route' => 'marketplace', 'label' => 'Marketplace', 'icon' => '🛒', 'group' => 'main', 'perm' => 'user')); }
}

<?php
class SFPlugin_culture_pack extends PluginBase {
    public function admin($input) {
        $h = '<div class="card"><h3>Culture contexts (' . count(cultures()) . ')</h3>'
            . '<div class="banner info"><span>🌍</span><div>Culture supplies setting, texture, names, greetings, food and flora. '
            . 'It never assigns personality, morality, ability or behaviour to a group, and it never generates a real person’s likeness or voice.</div></div>'
            . '<div class="grid g3" style="margin-top:12px">';
        foreach (cultures() as $k => $c) {
            $h .= '<div class="card" style="margin:0"><div class="row" style="justify-content:space-between"><b>' . e($k) . '</b><span class="chip">' . e(isset($c['region']) ? $c['region'] : '') . '</span></div>'
                . '<div class="dim" style="font-size:11.5px;margin-top:7px"><b>Names:</b> ' . e(implode(', ', array_slice((array) $c['names'], 0, 4))) . '</div>'
                . '<div class="dim" style="font-size:11.5px;margin-top:4px"><b>Places:</b> ' . e(implode(', ', array_slice((array) $c['places'], 0, 3))) . '</div>'
                . '<div class="dim" style="font-size:11.5px;margin-top:4px"><b>Greeting:</b> ' . e(isset($c['greet']) ? $c['greet'] : (isset($c['greeting']) ? $c['greeting'] : '')) . '</div>'
                . '<div class="dim" style="font-size:11.5px;margin-top:4px"><b>Texture:</b> ' . e($c['texture']) . '</div></div>';
        }
        $h .= '</div></div>';
        return $h;
    }
    public function dashboard() {
        $u = auth_user();
        $c = culture_get($u ? $u['culture'] : 'African');
        return array('<div class="card"><h3>🌍 ' . e($u ? $u['culture'] : 'African') . ' context</h3>'
            . '<div class="kv"><span>Region</span><b>' . e(isset($c['region']) ? $c['region'] : '') . '</b></div>'
            . '<div class="kv"><span>Greeting</span><b>' . e($c['greeting']) . '</b></div>'
            . '<div class="kv"><span>Texture</span><b style="font-size:12px">' . e($c['texture']) . '</b></div>'
            . '<div class="dim" style="font-size:11.5px;margin-top:8px">Applied automatically to new projects you create.</div></div>');
    }
    public function menu() { return array(array('route' => 'culture-pack', 'label' => 'Culture pack', 'icon' => '🌍', 'group' => 'admin', 'perm' => 'admin')); }
}

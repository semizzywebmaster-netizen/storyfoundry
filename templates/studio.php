<?php
/**
 * STUDIOS — one real page per addon (multi-page UI).
 *   index.php?r=studio            → directory of every installed studio
 *   index.php?r=studio/<addon>    → that addon's own page
 *   index.php?r=studio/<addon>&pid=12 → that addon's page for a chosen project
 */
$tool = isset($tool) ? preg_replace('/[^a-z0-9\-]/', '', (string) $tool) : '';
$pid  = isset($pid) ? (int) $pid : 0;

/* ---------------------------------------------------------------- listing */
$rows = plugins_db_rows();
$studios = array();
$platform = array();
foreach ($rows as $key => $row) {
    if (empty($row['enabled'])) { continue; }
    $meta = plugins_meta($key);
    if (!$meta) { continue; }
    $item = array(
        'key'   => $key,
        'name'  => isset($meta['name']) ? $meta['name'] : $key,
        'desc'  => isset($meta['description']) ? $meta['description'] : '',
        'icon'  => isset($meta['stage']['icon']) ? $meta['stage']['icon'] : '🧩',
        'stage' => isset($meta['stage']['key']) ? $meta['stage']['key'] : '',
        'label' => isset($meta['stage']['label']) ? $meta['stage']['label'] : '',
        'order' => isset($meta['stage']['order']) ? (int) $meta['stage']['order'] : 999,
        'routes' => isset($meta['routes']) ? $meta['routes'] : array(),
    );
    if ($item['stage']) { $studios[] = $item; } else { $platform[] = $item; }
}
usort($studios, function ($a, $b) { return $a['order'] - $b['order']; });
usort($platform, function ($a, $b) { return strcmp($a['name'], $b['name']); });

if (!$tool) {
    $projects = db_all('SELECT id,title FROM projects WHERE user_id=? AND deleted_at IS NULL ORDER BY id DESC LIMIT 1', array(auth_id()));
    $pid0 = $projects ? (int) $projects[0]['id'] : 0;
    ?>
    <div class="row" style="align-items:flex-end;gap:12px;margin-bottom:14px;flex-wrap:wrap">
      <div><h1 style="margin:0">Studios</h1>
      <p class="muted" style="margin:0;font-size:13px">Every production studio is its own page. Open one, pick a project, generate.</p></div>
      <span class="spacer"></span>
      <span class="chip chip-nc"><?php echo count($studios); ?> studios · <?php echo count($platform); ?> platform addons</span>
    </div>

    <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr))">
      <?php foreach ($studios as $s): ?>
        <a class="card" style="display:block;color:inherit;text-decoration:none"
           href="<?php echo e(sf_url('index.php?r=studio/' . $s['key'] . ($pid0 ? '&pid=' . $pid0 : ''))); ?>">
          <div class="row" style="gap:10px;align-items:center">
            <span style="font-size:24px"><?php echo e($s['icon']); ?></span>
            <div><b style="font-size:15px"><?php echo e($s['name']); ?></b>
            <div class="muted" style="font-size:12px"><?php echo e($s['label']); ?></div></div>
          </div>
          <p class="muted" style="font-size:13px;margin:8px 0 0"><?php echo e($s['desc']); ?></p>
        </a>
      <?php endforeach; ?>
    </div>

    <h2 style="margin:26px 0 10px;font-size:16px">Platform addons</h2>
    <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(240px,1fr))">
      <?php foreach ($platform as $s):
        $href = sf_url('index.php?r=studio/' . $s['key']);
        foreach ($s['routes'] as $rt) { $r = is_array($rt) ? $rt['route'] : $rt; if (substr($r, -5) !== '.json' && substr($r, -3) !== '.js') { $href = sf_url('index.php?r=' . $r); break; } }
      ?>
        <a class="card" style="display:block;color:inherit;text-decoration:none" href="<?php echo e($href); ?>">
          <div class="row" style="gap:10px;align-items:center">
            <span style="font-size:20px"><?php echo e($s['icon']); ?></span>
            <b style="font-size:14px"><?php echo e($s['name']); ?></b>
          </div>
          <p class="muted" style="font-size:12px;margin:8px 0 0"><?php echo e($s['desc']); ?></p>
        </a>
      <?php endforeach; ?>
    </div>
    <?php
    return;
}

/* ------------------------------------------------------- single addon page */
$found = null;
foreach (array_merge($studios, $platform) as $it) { if ($it['key'] === $tool) { $found = $it; } }
if (!$found) {
    http_response_code(404);
    echo ui_empty('🔍', 'Studio not found, not installed or disabled.',
        '<a class="btn sm" href="' . e(sf_url('index.php?r=studio')) . '">All studios</a>');
    return;
}

$projects = db_all('SELECT id,title FROM projects WHERE user_id=? AND deleted_at IS NULL ORDER BY id DESC', array(auth_id()));
$project = null;
if ($pid) {
    foreach ($projects as $pr) { if ((int) $pr['id'] === $pid) { $project = $pr; } }
    /* admins may open any project; ownership is still enforced below */
    if (!$project && auth_is_admin()) {
        $project = db_one('SELECT id,title FROM projects WHERE id=? AND deleted_at IS NULL', array($pid));
    }
}
if (!$project && $projects) { $project = $projects[0]; }
if ($project) {
    /* full row, and a hard ownership check — never trust the posted pid */
    $row = db_one('SELECT * FROM projects WHERE id=? AND deleted_at IS NULL', array((int) $project['id']));
    if (!$row || ($row['user_id'] != auth_id() && !auth_is_admin())) { http_response_code(403); echo 'Forbidden'; return; }
    $project = $row;
}

$inst = plugin_instance($tool);
$html = $inst
    ? $inst->page(array('projects' => $projects, 'project' => $project, 'pid' => $project ? (int) $project['id'] : 0))
    : ui_empty('⚠️', 'Addon failed to load: ' . e($tool));
?>
<div class="muted" style="font-size:12px;margin-bottom:8px">
  <a href="<?php echo e(sf_url('index.php?r=studio')); ?>">← All studios</a>
</div>
<div class="card"><?php echo $html; ?></div>

<?php
/**
 * Read-only health check. Visit /verify.php after install.
 * Shows no secrets — only presence/absence and status labels.
 * Delete this file once you are live.
 */
define('SF_ROOT', __DIR__);
require_once SF_ROOT . '/core/bootstrap.php';   /* hardening + headers */
session_start();
$installed = sf_installed();
if ($installed) { sf_boot(); }

function row($k, $v, $ok = null) {
    $cls = $ok === null ? '' : ($ok ? 'ok' : 'bad');
    echo '<tr><td>' . htmlspecialchars($k) . '</td><td class="' . $cls . '">' . $v . '</td></tr>';
}
function yn($b) { return $b ? '<span class="ok">OK</span>' : '<span class="bad">MISSING</span>'; }
$exts = array('pdo_mysql' => 'MySQL driver', 'mbstring' => 'Strings', 'curl' => 'Outbound HTTP (AI APIs)',
    'json' => 'JSON', 'gd' => 'Image work', 'zip' => 'ZIP exports', 'openssl' => 'Signing/encryption');
?>
<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>STORYFOUNDRY — health check</title>
<style>
 body{font:15px/1.6 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#0b0c0e;color:#e8eaee;margin:0}
 .wrap{max-width:820px;margin:40px auto;padding:0 20px}
 .card{background:#14171c;border:1px solid #242a33;border-radius:14px;padding:20px;margin-bottom:16px}
 h1{font-size:22px;margin:0 0 4px}h2{font-size:16px;margin:0 0 10px}
 table{width:100%;border-collapse:collapse;font-size:13.5px}
 td{padding:7px 8px;border-bottom:1px solid #1e242c}td:last-child{text-align:right}
 .ok{color:#33c98a;font-weight:700}.bad{color:#f2545b;font-weight:700}
 code{background:#191d24;padding:2px 6px;border-radius:5px;font-size:12.5px}
 .muted{color:#9aa4b2;font-size:13px}
</style>
<div class="wrap">
  <div class="card"><h1>STORYFOUNDRY health check</h1>
    <p class="muted">Read-only. Nothing is written, no credentials are shown.</p></div>

  <div class="card"><h2>Server</h2><table>
    <?php row('PHP version', PHP_VERSION . (version_compare(PHP_VERSION, '7.4', '>=') ? ' <span class="ok">OK</span>' : ' <span class="bad">7.4+ required</span>'));
    foreach ($exts as $e => $label) { row($label . ' <span class="muted">(' . $e . ')</span>', yn(extension_loaded($e))); }
    row('Sessions', yn(session_status() === PHP_SESSION_ACTIVE));
    row('Disk free', function_exists('disk_free_space') ? round(disk_free_space(SF_ROOT) / 1048576) . ' MB' : 'unknown');
    ?></table></div>

  <div class="card"><h2>Installation</h2><table>
    <?php row('config.php', yn($installed));
    if (!$installed) { row('Next step', '<a href="install.php" style="color:#ffb020">Run the installer →</a>'); }
    if ($installed) {
        row('Database', db() ? '<span class="ok">connected</span>' : '<span class="bad">not connected</span>');
        $t = 0; try { $t = (int) db_val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()", array(), 0); } catch (Exception $e) {}
        row('Tables', $t . ' (expected 38)');
        $pl = 0; $en = 0; try { $pl = (int) db_val('SELECT COUNT(*) FROM plugins', array(), 0); $en = (int) db_val('SELECT COUNT(*) FROM plugins WHERE enabled=1', array(), 0); } catch (Exception $e) {}
        row('Addons', $en . ' enabled / ' . $pl . ' installed');
        $q = array(); try { $q = jobs_stats(); } catch (Exception $e) {}
        row('Queue', $q ? ($q['queued'] . ' queued · ' . $q['completed'] . ' completed · ' . $q['failed'] . ' failed') : 'unavailable');
        row('cron token', sf_config('app.cron_token') ? 'set (<span class="muted">hidden</span>)' : '<span class="bad">missing</span>');
    } ?></table></div>

  <?php if ($installed): ?>
  <div class="card"><h2>AI providers</h2><table>
    <?php foreach (providers_effective() as $p) {
        $cls = ($p['status'] === 'REAL') ? 'ok' : (($p['status'] === 'MOCK') ? '' : 'bad');
        echo '<tr><td>' . htmlspecialchars($p['name']) . ' <span class="muted">(' . htmlspecialchars($p['capability']) . ')</span></td>'
           . '<td class="' . $cls . '">' . htmlspecialchars($p['status']) . '</td></tr>';
    } ?></table>
    <p class="muted" style="margin-top:10px">MOCK = local deterministic engine, always badged in the UI. NOT CONFIGURED = adapter present, keys missing.</p></div>

  <div class="card"><h2>Media pipeline</h2><table>
    <?php $ms = media_status();
    row('FFmpeg', $ms['ffmpeg'] ? '<span class="ok">' . htmlspecialchars($ms['version'] ?: 'found') . '</span>' : '<span class="bad">NOT CONFIGURED</span>');
    row('FFmpeg path', $ms['ffmpeg_path'] ? '<code>' . htmlspecialchars($ms['ffmpeg_path']) . '</code>' : '<span class="muted">not found</span>');
    row('GD', yn($ms['gd']));
    row('Video rendering', $ms['ffmpeg'] ? '<span class="ok">REAL renders possible</span>' : '<span class="bad">renders will fail — no fake output</span>');
    ?></table></div>
  <?php endif; ?>

  <div class="card"><h2>Writable paths</h2><table>
    <?php foreach (array('uploads', 'uploads/assets', 'storage', 'storage/cache', 'storage/logs') as $d) {
        $p = SF_ROOT . '/' . $d;
        row('<code>' . $d . '</code>', is_dir($p) ? (is_writable($p) ? '<span class="ok">writable</span>' : '<span class="bad">not writable</span>') : '<span class="bad">missing</span>');
    } ?></table></div>

  <div class="card"><h2>Next</h2>
    <p class="muted">Add API keys in <b>Admin → AI Providers</b>. Set costs and limits in <b>Admin → Feature governance</b>.
    Install the cron command printed by the installer so queued jobs are processed.
    When you are done, delete <code>verify.php</code> and <code>install.php</code>.</p></div>
</div>

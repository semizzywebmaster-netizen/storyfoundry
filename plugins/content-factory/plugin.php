<?php
class SFPlugin_content_factory extends PluginBase {
    public function stage($project, $data) {
        $data = is_array($data) ? $data : array();
        $jobs = isset($data['batches']) ? $data['batches'] : array();
        $h = '<div class="grid" style="grid-template-columns:1fr 300px;align-items:start">';
        $h .= '<div class="card"><h3>Batch jobs</h3>';
        if (!$jobs) {
            $h .= ui_empty('🏭', 'No batches yet.', ui_generate_btn('content-factory', 'batch', 'Queue a batch of 5', 'batch', array('project_id' => (int) $project['id'])));
        } else {
            $h .= '<div class="tbl" style="border:0"><table><thead><tr><th>Batch</th><th>Items</th><th>Status</th><th>Queued</th><th></th></tr></thead><tbody>';
            foreach ($jobs as $b) {
                $h .= '<tr><td><b>' . e($b['label']) . '</b></td><td class="mono">' . (int) $b['items'] . '</td>'
                    . '<td><span class="chip ' . ($b['status'] === 'queued' ? 'chip-warn' : 'chip-ok') . '">' . e($b['status']) . '</span></td>'
                    . '<td class="dim" style="font-size:11.5px">' . e(ui_time_ago($b['t'])) . '</td>'
                    . '<td><button class="btn xs gho" data-api="content-factory.retry" data-payload=\'{"project_id":' . (int) $project['id'] . ',"batch":' . json_encode($b['id']) . '}\' data-reload="1">Retry failed</button></td></tr>';
            }
            $h .= '</tbody></table></div>';
        }
        $h .= '</div><div class="col">';
        $h .= '<div class="card"><h3>Factory controls</h3>'
            . '<label class="fl">Batch size<input type="number" id="cfN" value="5" min="1" max="40"></label>'
            . '<label class="fl" style="margin-top:10px">Content type' . ui_select('cfType', array('Shorts' => 'Short form clips', 'Series episodes' => 'Series episodes', 'Social posts' => 'Social posts', 'Concepts' => 'Story concepts')) . '</label>'
            . '<button class="btn pri blk" style="margin-top:12px" data-api="content-factory.batch" data-payload=\'{"project_id":' . (int) $project['id'] . '}\' data-form="cfBox" data-reload="1">Queue batch</button>'
            . '<div id="cfBox" style="display:none"><input type="hidden" name="project_id" value="' . (int) $project['id'] . '"></div>'
            . '</div>';
        $h .= '<div class="card"><h3>Series</h3>';
        $series = db_all('SELECT * FROM series WHERE project_id=? ORDER BY id DESC LIMIT 10', array((int) $project['id']));
        if (!$series) { $h .= '<div class="dim" style="font-size:12px">No series yet. A series groups episodes that share characters, setting and style.</div>'; }
        foreach ($series as $s) {
            $h .= '<div class="kv"><span>' . e($s['title']) . '</span><span class="chip">' . (int) $s['episodes'] . ' eps</span></div>';
        }
        $h .= '<button class="btn sm blk gho" style="margin-top:10px" data-api="content-factory.series" data-payload=\'{"project_id":' . (int) $project['id'] . '}\' data-reload="1">Create series</button></div>';
        $h .= '</div></div>';
        return $h;
    }
    public function api($action, $in) {
        $pid = (int) (isset($in['project_id']) ? $in['project_id'] : 0);
        $p = db_one('SELECT * FROM projects WHERE id=?', array($pid));
        if (!$p) return array('ok' => false, 'error' => 'Project not found');
        $data = stage_data($pid, 'factory', array());
        if ($action === 'batch') {
            $n = max(1, min(40, (int) (isset($in['n']) ? $in['n'] : 5)));
            $type = isset($in['type']) ? $in['type'] : 'Concepts';
            $ids = array();
            for ($i = 0; $i < $n; $i++) {
                $j = sf_queue('batch', 'content-factory.item', array('project_id' => $pid, 'type' => $type, 'i' => $i), array(
                    'project_id' => $pid, 'label' => ucfirst($type) . ' item ' . ($i + 1) . '/' . $n));
                if (!empty($j['ok'])) { $ids[] = (int) $j['job_id']; }
            }
            if (!$ids) return array('ok' => false, 'error' => 'Could not queue the batch');
            if (!isset($data['batches'])) { $data['batches'] = array(); }
            $data['batches'][] = array('id' => 'b' . $ids[0], 'label' => ucfirst($type) . ' batch (' . count($ids) . ')', 'items' => count($ids), 'status' => 'queued', 't' => db_now(), 'jobs' => $ids);
            stage_save($pid, 'factory', $data);
            project_log_activity($pid, 'queued a batch of ' . count($ids), '🏭');
            return array('ok' => true, 'message' => count($ids) . ' items queued', 'reload' => true);
        }
        if ($action === 'retry') {
            foreach (jobs_for_user(auth_id(), 200) as $j) {
                if ($j['status'] === 'failed' && $j['project_id'] == $pid) { jobs_retry((int) $j['id']); }
            }
            return array('ok' => true, 'message' => 'Failed jobs re-queued', 'reload' => true);
        }
        if ($action === 'series') {
            $id = db_insert('INSERT INTO series (user_id,project_id,title,episodes,created_at) VALUES (?,?,?,?,?)',
                array(auth_id(), $pid, $p['title'] . ' — Series', 0, db_now()));
            return array('ok' => true, 'message' => 'Series created', 'reload' => true);
        }
        return array('ok' => false, 'error' => 'Unknown action');
    }
    public function job_item($input, $job) {
        $pid = (int) $input['project_id'];
        $p = db_one('SELECT * FROM projects WHERE id=?', array($pid));
        $ctx = project_ctx($p);
        $type = isset($input['type']) ? $input['type'] : 'Concepts';
        if ($type === 'Concepts') { $kind = 'ideas'; } elseif ($type === 'Social posts') { $kind = 'paragraph'; } else { $kind = 'paragraph'; }
        $res = providers_call('text', array('kind' => $kind, 'context' => $ctx, 'n' => 1), array());
        if (empty($res['ok'])) return array('ok' => false, 'error' => 'Batch item failed');
        return array('ok' => true, 'data' => $res['data'], 'provider' => $res['provider'], 'status' => $res['status']);
    }
    public function job_done($method, $job, $res) {
        $payload = json_decode($job['payload'], true);
        if (!is_array($payload)) return null;
        $pid = (int) $job['project_id'];
        $data = stage_data($pid, 'factory', array());
        if (!isset($data['items'])) { $data['items'] = array(); }
        $data['items'][] = array('t' => db_now(), 'label' => $job['label'], 'provider' => $res['provider'], 'status' => $res['status']);
        if (count($data['items']) > 200) { $data['items'] = array_slice($data['items'], -200); }
        stage_save($pid, 'factory', $data);
        return null;
    }
}

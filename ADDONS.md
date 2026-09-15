# Building a STORYFOUNDRY addon

Addons are the architecture. Core provides auth, credits, providers, queue, storage, schema and the shell;
**features live in `plugins/`**. Nothing in core knows that any specific addon exists.

---

## 1. The folder

```
plugins/my-thing/
├── plugin.json          required — manifest
├── plugin.php           required — class SFPlugin_my_thing extends PluginBase
├── views/               optional — templates loaded with $this->view('name', $vars)
└── assets/              optional
```

Drop the folder in, then **Admin → Addons → Install**. That is the whole deployment story.

## 2. plugin.json

```json
{
  "key": "my-thing",
  "name": "My Thing",
  "version": "1.0.0",
  "author": "You",
  "description": "One line shown on the Addons page.",
  "features": [31],
  "capabilities": ["image"],
  "requires": ["image-studio"],
  "stage": { "key": "mything", "label": "My Thing", "icon": "✨", "order": 18 },
  "routes": ["mything"],
  "settings": { "some_key": "default" }
}
```

| Field | Effect |
|---|---|
| `stage` | Adds a stage to the project pipeline rail; you implement `stage($project, $data)` |
| `routes` | Registers top-level routes: `index.php?r=mything` → `route($route, $get, $post)` |
| `requires` | Install refuses until the listed addons are enabled (dependency order) |
| `features` | Spec feature numbers, shown on the Addons page (see FEATURE-MAP.md) |
| `capabilities` | Which AI capability this addon touches (`text`, `image`, `voice`, `music`, `video`) |
| `settings` | Default config, readable with `$this->config('some_key')` and editable from the DB row |

## 3. The class

```php
<?php
class SFPlugin_my_thing extends PluginBase {

    /** Runs on every request while enabled — register hooks and providers here. */
    public function boot() {
        providers_register(array(
            'pkey' => 'my.image', 'name' => 'My Image API', 'capability' => 'image',
            'status' => 'NOT CONFIGURED', 'priority' => 20,
            'models' => array('my-v1'),
            'config_fields' => array('api_key' => 'API key'),
            'call' => function ($input, $cfg) { return $this->call_api($input, $cfg); },
        ));
        add_action('job.completed', array($this, 'on_done'));
    }

    public function install()   { return true; }   // one-time setup
    public function uninstall() { return true; }   // cleanup

    /** Pipeline stage panel (only if "stage" is declared). */
    public function stage($project, $data) { return '<div class="card">…</div>'; }

    /** AJAX: index.php?r=api&a=my-thing.do → returns an array (JSON). */
    public function api($action, $input) {
        if ($action === 'do') {
            $pid = (int) $input['project_id'];
            $r = orchestrator_run('thumb', array(/* … */), array(
                'project_id' => $pid, 'label' => 'My thing', 'inline' => true));
            if (empty($r['ok'])) return array('ok' => false, 'error' => 'Failed');
            stage_save($pid, 'mything', $r['data']);
            return array('ok' => true, 'message' => 'Done', 'reload' => true);
        }
        return array('ok' => false, 'error' => 'Unknown action');
    }

    /** Admin page: index.php?r=admin&p=my-thing */
    public function admin($input) { return '<div class="card">…</div>'; }

    /** Sidebar entries: group "main" (app) or "admin". */
    public function menu() {
        return array(array('route' => 'mything', 'label' => 'My Thing', 'icon' => '✨', 'group' => 'main', 'perm' => 'user'));
    }

    /** Cards on the Creator Dashboard. */
    public function dashboard() { return array('<div class="card">…</div>'); }

    /** Top-level route (only if "routes" is declared). */
    public function route($route, $get, $post) { require_login(); return sf_response_raw('<h1>My Thing</h1>'); }

    /** Background jobs queued with sf_queue('feature', 'my-thing.work', $input). */
    public function job_work($input, $job) {
        return array('ok' => true, 'data' => array('path' => 'uploads/…'), 'provider' => 'my.image', 'status' => 'REAL');
    }
    public function job_done($method, $job, $res) { /* persist the result */ }
}
```

### Optional: your own page (multi-page)

If your addon declares a `stage`, it automatically appears at **`index.php?r=studio/<key>`** — the
default `PluginBase::page($ctx)` prints a header, a project picker and your `stage()` panel.
Override it for something bespoke:

```php
public function page($ctx) {
    // $ctx = array('projects' => [...], 'project' => array|null, 'pid' => int)
    return '<div class="card">My own page</div>';
}
```

## 4. The contracts you must honour

| Rule | Why |
|---|---|
| Call `orchestrator_run()` / `sf_queue()` — never call a provider directly for billable work | Enforces feature flags, plan gating, daily limits, credit reserve/consume/release, usage logging, notifications |
| Return `array('ok' => true\|false, …)` from `api()` | The JS layer reads `ok`, `message`, `reload`, `download`, `copy`, `goto` |
| Include `status` (`REAL`\|`MOCK`) in provider results | The UI badges it; production must never silently fall back to MOCK |
| Persist per-stage output with `stage_save($projectId, $stageKey, $data)` | Keeps stages independent and removable |
| Use `require_login()` / `require_admin()` in `route()` and `admin()` | Role-based access is enforced by you, not by core |
| Never echo a secret | Keys are masked in Admin and never sent to the browser |
| Queue anything slow | `sf_queue()` reserves credits, then cron/web drains it |

## 5. Helpers you get for free

**Data** — `db_one/db_all/db_val/db_exec/db_insert/db_begin/db_commit/db_rollback`, `db_now()`
**Projects** — `project_ctx($project)`, `project_asset(...)`, `project_log_activity(...)`, `project_touch(...)`, `stage_data()/stage_save()`
**AI** — `orchestrator_run()`, `sf_queue()`, `providers_call()`, `providers_register()`
**Money** — `credits_check/reserve/consume/release/grant`, `wallet_deposit/spend`, `feature_cost()`, `usage_log()`
**Media** — `media_ffmpeg()`, `media_status()`, `media_render_video()`, `media_burn_subtitles()`, `media_mix_audio()`, `mockai_frame_svg()`
**HTML** — `ui_status/ui_kpi/ui_empty/ui_card/ui_form_open/ui_select/ui_generate_btn`, `e()`, `sf_money()`, `ui_time_ago()`
**Files** — `storage_signed_url()`, `sf_store_bytes()`, `sf_upload()`, `sf_http()`
**People** — `auth_id/auth_user/auth_is/auth_at_least/auth_can`, `notify()`, `audit()`, `setting()`

## 6. Worked example — add a stage in 3 minutes

```bash
mkdir -p plugins/brand-kit
```

`plugins/brand-kit/plugin.json`:
```json
{ "key":"brand-kit","name":"Brand Kit","version":"1.0.0","author":"You",
  "description":"Logo, colours and fonts applied to exports. (spec feature 70)",
  "features":[70],"capabilities":[],"requires":["export-suite"],
  "stage":{"key":"brand","label":"Brand","icon":"🎨","order":18},"routes":[],"settings":{} }
```

`plugins/brand-kit/plugin.php`:
```php
<?php
class SFPlugin_brand_kit extends PluginBase {
    public function stage($project, $data) {
        $data = is_array($data) ? $data : array();
        $h  = '<div class="card"><h3>Brand kit</h3>'
            . '<label class="fl">Primary colour<input name="primary" value="' . e(isset($data['primary']) ? $data['primary'] : '#ffb020') . '"></label>'
            . '<button class="btn pri" style="margin-top:10px" data-api="brand-kit.save" '
            . 'data-payload=\'{"project_id":' . (int) $project['id'] . '}\' data-reload="1">Save brand</button></div>';
        return $h;
    }
    public function api($action, $in) {
        $pid = (int) $in['project_id'];
        if ($action === 'save') {
            stage_save($pid, 'brand', array('primary' => isset($in['primary']) ? $in['primary'] : '#ffb020'));
            return array('ok' => true, 'message' => 'Brand saved', 'reload' => true);
        }
        return array('ok' => false, 'error' => 'Unknown action');
    }
}
```

Then **Admin → Addons → Install** on "Brand Kit". The **Brand** stage now appears in every project rail. Uninstall it and it disappears. Core was never touched.

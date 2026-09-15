#!/usr/bin/env python3
"""Exercises every remaining POST/API action: forms, admin actions, plugin actions."""
import time
RUN = str(int(time.time()))[-6:]
import json, re, sys, urllib.request, urllib.parse, http.cookiejar, subprocess

BASE = 'http://127.0.0.1:8899/index.php'
DB = ['mariadb', '-u', 'sf', '-psfpass', '-h', '127.0.0.1', 'storyfoundry', '-N', '-e']
P = F = 0
def chk(n, c, e=''):
    global P, F
    if c: P += 1
    else:
        F += 1; print('FAIL:', n, e)
def q(sql):
    r = subprocess.run(DB + [sql], capture_output=True, text=True)
    return (r.stdout or '').strip()

cj = http.cookiejar.CookieJar(); op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
def get(route):
    try:
        r = op.open(BASE + '?r=' + route, timeout=90); return r.getcode(), r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e: return e.code, e.read().decode('utf-8', 'replace')
def csrf():
    c, b = get('dashboard'); m = re.search(r'<meta name="csrf" content="([^"]+)">', b); return m.group(1) if m else ''
def post(route, fields, ajax=False):
    tok = csrf()
    data = urllib.parse.urlencode(dict(fields, csrf=tok)).encode()
    req = urllib.request.Request(BASE + '?r=' + route, data=data)
    if ajax: req.add_header('X-Requested-With', 'XMLHttpRequest')
    try:
        r = op.open(req, timeout=120); return r.getcode(), r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e: return e.code, e.read().decode('utf-8', 'replace')
def api(action, payload=None):
    tok = csrf()
    req = urllib.request.Request(BASE + '?r=api&a=' + action, data=json.dumps(payload or {}).encode(),
        headers={'Content-Type': 'application/json', 'X-CSRF': tok, 'X-Requested-With': 'XMLHttpRequest'})
    try:
        r = op.open(req, timeout=180); return json.loads(r.read().decode('utf-8', 'replace'))
    except urllib.error.HTTPError as e:
        try: return json.loads(e.read().decode('utf-8', 'replace'))
        except Exception: return {'ok': False, 'error': 'HTTP %s' % e.code}
    except Exception as e: return {'ok': False, 'error': str(e)}

# login as admin
c, b = get('login'); tok = re.search(r'name="csrf"[^>]*value="([^"]+)"', b).group(1)
post('login', {'csrf': tok, 'email': 'admin@storyfoundry.test', 'password': 'AdminPass123'})
c, b = get('dashboard')
chk('admin logged in', 'Welcome back' in b, c)

# deterministic start on a re-used install: reset today's feature counters and top up the
# wallet so a previous run's usage (daily limits) can never masquerade as a code failure
subprocess.run(DB + ["DELETE FROM usage_log"], capture_output=True)
subprocess.run(DB + ["UPDATE users SET credits=50000, reserved=0 WHERE email='admin@storyfoundry.test'"], capture_output=True)

# make sure we have a project with data to work on
pid = q("SELECT id FROM projects ORDER BY id DESC LIMIT 1")
pid = int(pid or 0)
if not pid:
    post('projects&a=new', {'title': 'Actions Test', 'genre': 'Drama', 'tone': 'Warm and hopeful',
        'culture': 'Yoruba', 'audience': 'General (PG)', 'theme': 'Family', 'setting': 'Lagos',
        'length': 'Medium (3-5 min)', 'style': 'cinematic'})
    pid = int(q("SELECT id FROM projects ORDER BY id DESC LIMIT 1") or 0)
chk('project available', pid > 0, pid)

# pipeline prerequisites
for a, pl in [('idea-engine.generate', {}), ('story-studio.generate', {}), ('character-studio.generate', {}),
              ('scene-engine.generate', {}), ('ai-director.generate', {}), ('image-studio.generate', {'style': 'cinematic', 'ratio': '16:9'}),
              ('subtitle-studio.generate', {}), ('thumbnail-studio.generate', {}), ('audio-studio.music', {'mood': 'Cinematic'}),
              ('timeline-editor.autocut', {})]:
    r = api(a, dict({'project_id': pid}, **pl))
    chk('prereq ' + a, r.get('ok'), r.get('error', ''))

# ---------- stage-level api actions ----------
char = json.loads(q("SELECT data FROM project_data WHERE project_id=%d AND stage_key='characters'" % pid) or '[]')
cname = char[0]['name'] if char else 'Ada'
checks = [
    ('character-studio.lock', {'project_id': pid, 'name': cname}, 'lock character'),
    ('character-studio.relationships', {'project_id': pid}, 'relationships'),
    ('story-studio.rewrite', {'project_id': pid, 'style': 'Cinematic', 'text': 'Ada walked home.'}, 'rewrite'),
    ('story-studio.version', {'project_id': pid}, 'save version'),
    ('story-studio.export', {'project_id': pid}, 'export manuscript'),
    ('image-studio.variations', {'project_id': pid, 'n': 1}, 'image variations'),
    ('image-studio.save', {'project_id': pid, 'n': 1, 'style': 'cinematic'}, 'save frame'),
    ('voice-studio.assign', {'project_id': pid, 'name': cname, 'voice': 'Adaeze', 'emotion': 'Warm'}, 'assign voice'),
    ('voice-studio.render', {'project_id': pid, 'name': cname}, 'render voice'),
    ('audio-studio.sfx', {'project_id': pid, 'kind': 'Rain on zinc roof'}, 'sfx'),
    ('audio-studio.mix', {'project_id': pid, 'key': 'music', 'value': '30'}, 'mix change'),
    ('audio-studio.master', {'project_id': pid}, 'master mix (expects ffmpeg error if absent)'),
    ('subtitle-studio.edit', {'project_id': pid, 'i': 0, 'text': 'Edited caption'}, 'edit caption'),
    ('subtitle-studio.style', {'project_id': pid, 'style': 'TikTok'}, 'caption style'),
    ('subtitle-studio.translate', {'project_id': pid}, 'translate'),
    ('subtitle-studio.srt', {'project_id': pid}, 'export srt'),
    ('subtitle-studio.vtt', {'project_id': pid}, 'export vtt'),
    ('subtitle-studio.burn', {'project_id': pid}, 'burn subtitles'),
    ('thumbnail-studio.pick', {'project_id': pid, 'i': 0}, 'pick thumbnail'),
    ('thumbnail-studio.ab', {'project_id': pid, 'i': 1}, 'ab test'),
    ('social-suite.generate', {'project_id': pid}, 'social pack'),
    ('social-suite.clips', {'project_id': pid}, 'auto clips'),
    ('social-suite.copy', {'project_id': pid, 'plat': 'TikTok'}, 'copy caption'),
    ('social-suite.format', {'project_id': pid, 'plat': 'TikTok'}, 'format clip'),
    ('timeline-editor.move', {'project_id': pid, 'n': 1, 'dir': 1}, 'move clip'),
    ('timeline-editor.drop', {'project_id': pid, 'n': 2}, 'drop clip'),
    ('timeline-editor.transitions', {'project_id': pid}, 'transitions'),
    ('timeline-editor.overlay', {'project_id': pid}, 'overlay'),
    ('scene-engine.shots', {'project_id': pid, 'n': 1}, 'goto shots'),
    ('ai-director.storyboard', {'project_id': pid}, 'storyboard'),
    ('ai-director.csv', {'project_id': pid}, 'shot list csv'),
    ('idea-engine.use', {'project_id': pid, 'title': 'Salt and Son'}, 'use concept'),
    ('content-factory.series', {'project_id': pid}, 'create series'),
    ('content-factory.retry', {'project_id': pid, 'batch': 'b1'}, 'retry batch'),
    ('export-suite.make', {'project_id': pid, 'kind': 'frames'}, 'export frames'),
    ('export-suite.make', {'project_id': pid, 'kind': 'audio'}, 'export audio'),
    ('export-suite.make', {'project_id': pid, 'kind': 'video'}, 'export video'),
    ('audit-security.sessions', {}, 'revoke sessions'),
    ('billing-suite.daily', {}, 'daily bonus'),
    ('ads-suite.reward', {}, 'rewarded ad'),
    ('paystack.init', {'amount': 5000, 'credits': 500}, 'paystack init'),
    ('flutterwave.init', {'amount': 5000, 'credits': 500}, 'flutterwave init'),
    ('video-engine.all', {'project_id': pid, 'motion': 40}, 'queue clips'),
]
soft = {'audio-studio.master', 'subtitle-studio.burn', 'export-suite.make', 'ads-suite.reward',
        'paystack.init', 'flutterwave.init', 'billing-suite.daily'}
for action, payload, label in checks:
    r = api(action, payload)
    if action in soft:
        chk('api ' + action + ' (' + label + ')',
            r.get('ok') or ('NOT CONFIGURED' in str(r.get('error', '')))
            or ('FFmpeg' in str(r.get('error', ''))) or ('DISABLED' in str(r.get('error', '')))
            or ('already claimed' in str(r.get('error', '')).lower())
            or ('No rendered video' in str(r.get('error', ''))),
            r.get('error', ''))
    else:
        chk('api ' + action + ' (' + label + ')', r.get('ok'), r.get('error', ''))

# ---------- html forms ----------
tok = csrf()
MP_TITLE = 'Lagos Noir Pack ' + RUN
code, _ = post('marketplace', {'act': 'list_item', 'title': MP_TITLE, 'cat': 'preset',
                               'description': 'Colour presets', 'price': '5000'})
chk('marketplace list item', code in (200, 302), code)
chk('marketplace item row', int(q("SELECT COUNT(*) FROM marketplace_items WHERE title='%s'" % MP_TITLE) or 0) >= 1,
    q("SELECT COUNT(*) FROM marketplace_items WHERE title='%s'" % MP_TITLE))
item = q("SELECT id FROM marketplace_items WHERE title='Lagos Noir Pack' LIMIT 1")
code, _ = post('marketplace', {'act': 'buy_item'})  # wrong act should be a no-op, not a crash
chk('marketplace unknown act safe', code in (200, 302), code)
code, b = get('marketplace&buy=' + item)
chk('marketplace buy flow', code in (200, 302), code)
tok = csrf()
code, _ = post('publishing', {'act': 'connect', 'platform': 'TikTok', 'handle': '@storyfoundry'})
chk('publishing connect', q("SELECT COUNT(*) FROM social_accounts") != '0', code)
code, _ = post('publishing', {'act': 'schedule', 'project_id': pid, 'platform': 'YouTube',
                              'caption': 'Launch day', 'scheduled_at': '2026-12-01T10:00'})
chk('publishing schedule', q("SELECT COUNT(*) FROM publishing") != '0', code)
code, _ = post('clients', {'act': 'new_client', 'name': 'Acme Films ' + RUN, 'contact': 'hi@acme.test'})
chk('agency add client', int(q("SELECT COUNT(*) FROM agency_clients WHERE name='Acme Films %s'" % RUN) or 0) >= 1, code)

# admin forms
ANN_TITLE = 'Test announcement ' + RUN
code, _ = post('admin&p=overview', {'act': 'announce', 'type': 'Feature', 'title': ANN_TITLE,
                                    'body': 'Body', 'target': 'all'})
chk('admin announce', int(q("SELECT COUNT(*) FROM announcements WHERE title='%s'" % ANN_TITLE) or 0) >= 1, code)
code, _ = post('admin&p=features', {'act': 'feature_save', 'cost[voice]': '9', 'plan[voice]': 'pro', 'limit[voice]': '25'})
chk('admin feature save', q("SELECT credit_cost FROM feature_flags WHERE fkey='voice'") == '9', q("SELECT credit_cost FROM feature_flags WHERE fkey='voice'"))
# keep `enabled` set: the admin form's checkbox semantics mean an omitted value would
# switch the provider OFF and silently break every later generation in this suite
code, _ = post('admin&p=providers', {'act': 'provider_save', 'pkey': 'mock.text', 'priority': '3', 'enabled': '1'})
chk('admin provider save', q("SELECT priority FROM providers WHERE pkey='mock.text'") == '3', q("SELECT priority FROM providers WHERE pkey='mock.text'"))
chk('admin provider save keeps provider enabled', q("SELECT enabled FROM providers WHERE pkey='mock.text'") == '1',
    q("SELECT enabled FROM providers WHERE pkey='mock.text'"))
code, _ = post('admin&p=settings', {'act': 'settings_save', 'set[credits_new_user]': '175'})
chk('admin settings save', q("SELECT v FROM settings WHERE k='credits_new_user'") == '175', q("SELECT v FROM settings WHERE k='credits_new_user'"))
code, _ = post('admin&p=users', {'act': 'grant_credits', 'user_id': '1', 'amount': '10'})
chk('admin grant credits', code in (200, 302), code)
code, _ = post('admin&p=users', {'act': 'user_role', 'user_id': '1', 'role': 'admin', 'plan': 'agency'})
chk('admin user role', q("SELECT role FROM users WHERE id=1") == 'admin', q("SELECT role FROM users WHERE id=1"))
code, _ = post('admin&p=notifications', {'act': 'notify_user', 'user_id': '1', 'type': 'info', 'title': 'Hi', 'body': 'There'})
chk('admin notify user', q("SELECT COUNT(*) FROM notifications WHERE title='Hi'") != '0', code)
code, _ = post('admin&p=subscriptions', {'act': 'plan_save', 'pkey': 'pro', 'name': 'Pro', 'price': '25000', 'credits': '1600', 'features': 'x'})
chk('admin plan save', q("SELECT credits FROM plans WHERE pkey='pro'") == '1600', q("SELECT credits FROM plans WHERE pkey='pro'"))
CP_CODE = 'T' + RUN
code, _ = post('admin&p=billing-suite', {'act': 'coupon_new', 'code': CP_CODE, 'type': 'credit', 'value': '50', 'max_uses': '10'})
chk('admin coupon create', int(q("SELECT COUNT(*) FROM coupons WHERE code='%s'" % CP_CODE) or 0) >= 1, code)
code, _ = post('admin&p=media-pipeline', {'act': 'mp_probe', 'ffmpeg_path': ''})
chk('admin ffmpeg probe', code in (200, 302), code)

# billing forms
code, _ = post('billing/credits', {'act': 'buy_credits', 'pack': '500', 'method': 'wallet'})
chk('billing buy with wallet (may lack funds)', code in (200, 302), code)
code, _ = post('billing/credits', {'act': 'buy_credits', 'pack': '500', 'method': 'paystack'})
chk('billing buy simulated', q("SELECT COUNT(*) FROM transactions WHERE method='paystack'") != '0', code)
code, _ = post('billing/wallet', {'act': 'deposit', 'amount': '5000'})
chk('billing wallet deposit', code in (200, 302), code)
code, _ = post('billing/coupons', {'act': 'coupon', 'code': CP_CODE})
chk('billing coupon redeem', code in (200, 302), code)
code, _ = get('billing/plans&sub=pro')
chk('billing subscribe', q("SELECT plan FROM users WHERE id=1") == 'pro', q("SELECT plan FROM users WHERE id=1"))

# account forms
code, _ = post('account', {'act': 'profile', 'name': 'Platform Admin', 'bio': 'Test bio'})
chk('account profile save', code in (200, 302), code)
code, _ = post('account/security', {'act': 'password', 'current': 'AdminPass123', 'new': 'AdminPass123', 'confirm': 'AdminPass123'})
chk('account password form', code in (200, 302), code)

# project actions
code, _ = get('projects&a=duplicate&id=%d' % pid)
chk('project duplicate', code in (200, 302) and int(q("SELECT COUNT(*) FROM projects") or 0) >= 2, code)
code, _ = get('projects&a=delete&id=%d' % pid)
chk('project archive', q("SELECT COUNT(*) FROM projects WHERE id=%d AND deleted_at IS NULL" % pid) == '0', code)

# queue manager + asset library
code, b = get('admin&p=queue-manager')
chk('queue manager page', code == 200 and 'Worker' in b, code)
r = api('queue-manager.drain', {})
chk('queue drain', r.get('ok'), r.get('error', ''))
r = api('queue-manager.retry_failed', {})
chk('queue retry failed', r.get('ok'), r.get('error', ''))
r = api('queue-manager.prune', {})
chk('queue prune', r.get('ok'), r.get('error', ''))
aid = q("SELECT id FROM assets ORDER BY id DESC LIMIT 1")
if aid:
    r = api('asset-library.fav', {'id': int(aid)})
    chk('asset favourite', r.get('ok'), r.get('error', ''))

# ---------- brand kit (spec feature 70) ----------
kit_name = 'Brand ' + RUN
r = api('brand-kit.save', {'name': kit_name, 'c_primary': '#ff8800', 'c_secondary': '#101418',
                           'c_accent': '#6ea8fe', 'c_bg': '#0e1116', 'c_text': '#f2f4f8',
                           'font_heading': 'Display', 'font_body': 'System', 'style': 'cinematic',
                           'wm_pos': 'bottom-right', 'wm_opacity': '55'})
chk('brand kit save', r.get('ok'), r.get('error', ''))
kit_id = q("SELECT id FROM brand_kits WHERE name='%s' ORDER BY id DESC LIMIT 1" % kit_name)
chk('brand kit persisted', kit_id != '', kit_id)
r = api('brand-kit.default', {'id': int(kit_id or 0)})
chk('brand kit set default', r.get('ok'), r.get('error', ''))
chk('brand kit default flag', q("SELECT is_default FROM brand_kits WHERE id=%s" % kit_id) == '1',
    q("SELECT is_default FROM brand_kits WHERE id=%s" % kit_id))
r = api('brand-kit.apply', {'project_id': pid})
chk('brand kit apply to project', r.get('ok'), r.get('error', ''))
applied = q("SELECT data FROM project_data WHERE project_id=%d AND stage_key='brandkit'" % pid)
chk('brand kit stored on project', 'kit_id' in applied, applied[:60])
r = api('brand-kit.dup', {'id': int(kit_id or 0)})
chk('brand kit duplicate', r.get('ok'), r.get('error', ''))
r = api('brand-kit.template', {'id': int(kit_id or 0)})
chk('brand kit save template', r.get('ok'), r.get('error', ''))
tpl = q("SELECT id FROM brand_kits WHERE kind='template' ORDER BY id DESC LIMIT 1")
r = api('brand-kit.use_template', {'id': int(tpl or 0)})
chk('brand kit load template', r.get('ok'), r.get('error', ''))
img = q("SELECT id FROM assets WHERE kind='image' ORDER BY id DESC LIMIT 1")
r = api('brand-kit.watermark', {'id': int(img or 0)})
chk('brand kit watermark is honest (real or clearly not configured)',
    r.get('ok') or 'NOT CONFIGURED' in str(r.get('error', '')) or 'watermark PNG' in str(r.get('error', ''))
    or 'Image not found' in str(r.get('error', '')), r.get('error', ''))
code, b = get('brandkit')
chk('brandkit page renders inside the app shell', 'Brand Kit' in b and 'name="c_primary"' in b, code)
dup_id = q("SELECT id FROM brand_kits WHERE name LIKE '%%copy%%' ORDER BY id DESC LIMIT 1")
if dup_id:
    r = api('brand-kit.del', {'id': int(dup_id)})
    chk('brand kit delete', r.get('ok'), r.get('error', ''))

print('\n%d passed, %d failed' % (P, F))
sys.exit(1 if F else 0)


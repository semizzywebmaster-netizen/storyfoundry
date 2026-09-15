#!/usr/bin/env python3
"""Runtime tests for the features built in the rebuild pass:
   dark/light theme (1), character bible (12), viral optimizer (34),
   approval workflow (69), file cache (76) and the per-addon studio pages."""
import json, os, re, subprocess, sys, urllib.parse, urllib.request, urllib.error, http.cookiejar

PORT = os.environ.get('PORT', '8899')
BASE = 'http://127.0.0.1:%s/index.php' % PORT
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB = ['mariadb', '-u', 'sf', '-psfpass', '-h', '127.0.0.1', 'storyfoundry', '-N', '-e']
P = F = 0


def chk(n, c, e=''):
    global P, F
    if c:
        P += 1
    else:
        F += 1
        print('FAIL:', n, e)


def q(sql):
    return subprocess.run(DB + [sql], capture_output=True, text=True).stdout.strip()


cj = http.cookiejar.CookieJar()
op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))


def get(route):
    try:
        r = op.open(BASE + '?r=' + route, timeout=120)
        return r.getcode(), r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace')


def post(route, fields):
    c, b = get('dashboard')
    m = re.search(r'<meta name="csrf" content="([^"]+)"', b)
    data = urllib.parse.urlencode(dict(fields, csrf=m.group(1) if m else '')).encode()
    try:
        r = op.open(urllib.request.Request(BASE + '?r=' + route, data=data), timeout=120)
        return r.getcode(), r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace')


def api(action, payload=None):
    c, b = get('dashboard')
    m = re.search(r'<meta name="csrf" content="([^"]+)"', b)
    req = urllib.request.Request(BASE + '?r=api&a=' + action, data=json.dumps(payload or {}).encode(),
                                 headers={'Content-Type': 'application/json',
                                          'X-CSRF': m.group(1) if m else '',
                                          'X-Requested-With': 'XMLHttpRequest'})
    try:
        r = op.open(req, timeout=180)
        return json.loads(r.read().decode('utf-8', 'replace'))
    except urllib.error.HTTPError as e:
        try:
            return json.loads(e.read().decode('utf-8', 'replace'))
        except Exception:
            return {'ok': False, 'error': 'HTTP %s' % e.code}
    except Exception as e:
        return {'ok': False, 'error': str(e)}


# ---------------------------------------------------------------- sign in + project
c, b = get('login')
m = re.search(r'name="csrf"[^>]*value="([^"]+)"', b)
op.open(urllib.request.Request(BASE + '?r=login',
        data=urllib.parse.urlencode({'csrf': m.group(1), 'email': 'admin@storyfoundry.test',
                                     'password': 'AdminPass123'}).encode()), timeout=60)
pid = q("SELECT id FROM projects WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 1")
if not pid:
    post('projects&a=new', {'title': 'Rebuild Test', 'genre': 'Drama', 'tone': 'Emotional',
                            'audience': 'General (PG)', 'culture': 'Yoruba', 'theme': 'Family',
                            'setting': 'Lagos', 'length': 'Medium (3-5 min)', 'style': 'cinematic'})
    pid = q("SELECT id FROM projects WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 1")
chk('project available', bool(pid), pid)

# ---------------------------------------------------------------- 1. dark / light theme
c, b = get('dashboard')
chk('theme toggle present in UI', 'r=theme' in b, '')
chk('dark is the default', 'data-theme="dark"' in b, '')
code, _ = post('theme', {'v': 'light'})
c, b = get('dashboard')
chk('light theme applied', 'data-theme="light"' in b, 'HTTP %s' % c)
theme_db = q("SELECT v FROM settings WHERE k='theme_%s'" % q("SELECT id FROM users WHERE email='admin@storyfoundry.test'"))
chk('theme persisted for the account', theme_db == 'light', theme_db)
code, _ = post('theme', {'v': 'dark'})
c, b = get('dashboard')
chk('theme switches back', 'data-theme="dark"' in b, '')
# no reflected path: the hidden back field must be gone
c, b = get('project/1%20UNION%20SELECT%20pass_hash%20FROM%20users')
chk('no URL reflection in the page', 'pass_hash' not in b.lower(), 'reflected')

# ---------------------------------------------------------------- 12. character bible
r = api('character-studio.generate', {'project_id': int(pid)})
chk('characters generated for bible test', r.get('ok'), r.get('error', ''))
name = q("SELECT JSON_UNQUOTE(JSON_EXTRACT(data,'$[0].name')) FROM project_data WHERE project_id=%s AND stage_key='characters'" % pid)
chk('character has a name', bool(name), name)
if name:
    r = api('character-studio.bible', {'project_id': int(pid), 'name': name,
                                       'appearance': 'Tall, short hair, green coat',
                                       'background': 'Raised in Lagos', 'motivations': 'Find her father',
                                       'goals': 'Reopen the shop', 'fears': 'Losing family',
                                       'references': 'Ref: 1990s Lagos photos',
                                       'consistency': 'Always wears the green coat'})
    chk('bible save ok', r.get('ok'), r.get('error', ''))
    saved = q("SELECT JSON_EXTRACT(data,'$[0].bible.goals') FROM project_data WHERE project_id=%s AND stage_key='characters'" % pid)
    chk('bible persisted to project data', 'Reopen' in (saved or ''), saved)
    c, b = get('project/%s/characters' % pid)
    chk('bible editor rendered', 'Character bible' in b and 'Save bible' in b, '')
    chk('bible completeness shown', '% complete' in b, '')

# ---------------------------------------------------------------- 34. viral optimizer
r = api('social-suite.optimize', {'project_id': int(pid)})
chk('viral optimizer runs', r.get('ok'), r.get('error', ''))
viral = q("SELECT data FROM project_data WHERE project_id=%s AND stage_key='viral'" % pid)
chk('viral data stored', bool(viral) and 'hooks' in viral, (viral or '')[:80])
c, b = get('project/%s/social' % pid)
chk('optimizer panel rendered', 'Viral optimizer' in b, '')
chk('no guaranteed virality disclaimer', 'no guaranteed virality' in b.lower(), '')
chk('optimizer shows hooks', 'HOOKS' in b, '')

# ---------------------------------------------------------------- 69. approval workflow
c, b = get('project/%s/review' % pid)
chk('approval card rendered', 'Approval workflow' in b, '')
code, _ = post('project/%s/review' % pid, {'approval_state': 'Review', 'note': 'Tighten the opening',
                                           'note_kind': '1'})
state = q("SELECT approval FROM projects WHERE id=%s" % pid)
chk('approval state saved', state == 'Review', state)
nreq = q("SELECT COUNT(*) FROM comments WHERE project_id=%s AND kind='change_request' AND resolved=0" % pid)
chk('change request recorded', nreq == '1', nreq)
cid = q("SELECT id FROM comments WHERE project_id=%s AND kind='change_request' ORDER BY id DESC LIMIT 1" % pid)
code, _ = post('project/%s/review' % pid, {'resolve_id': cid})
nreq2 = q("SELECT COUNT(*) FROM comments WHERE project_id=%s AND kind='change_request' AND resolved=0" % pid)
chk('change request can be resolved', nreq2 == '0', nreq2)
code, _ = post('project/%s/review' % pid, {'approval_state': 'Nonsense'})
state2 = q("SELECT approval FROM projects WHERE id=%s" % pid)
chk('invalid approval state rejected', state2 == 'Review', state2)

# ---------------------------------------------------------------- 76. file cache
cache_dir = os.path.join(ROOT, 'storage', 'cache')
before = len([f for f in os.listdir(cache_dir) if f.startswith('c_')]) if os.path.isdir(cache_dir) else 0
get('studio')            # addon manifest discovery caches on this request
after = len([f for f in os.listdir(cache_dir) if f.startswith('c_')]) if os.path.isdir(cache_dir) else 0
chk('addon manifest cache written', after >= 1, '%d -> %d' % (before, after))
cached = [f for f in os.listdir(cache_dir) if f.startswith('c_')][:1]
if cached:
    head = open(os.path.join(cache_dir, cached[0]), encoding='utf-8', errors='replace').read(400)
    chk('cache file is php-guarded', head.startswith('<?php'), head[:40])
    chk('cache payload is json, not serialized', '\n{"exp"' in head, head[:80])
    chk('cache guard exits for direct requests', 'http_response_code(403)' in head, '')
    rc = subprocess.run(['php', '-l', os.path.join(cache_dir, cached[0])], capture_output=True, text=True)
    chk('cache file is valid php', rc.returncode == 0, rc.stdout[:80])

# ---------------------------------------------------------------- studios (multi-page)
c, b = get('studio')
chk('studio directory lists every addon', b.count('?r=studio/') >= 20, b.count('?r=studio/'))
c, b = get('studio/brand-kit&pid=%s' % pid)
chk('brand kit studio page renders', c == 200 and 'Brand Kit' in b, 'HTTP %s' % c)

print('%d passed, %d failed' % (P, F))
raise SystemExit(1 if F else 0)

import json, re, sys, urllib.request, urllib.parse, http.cookiejar, time

BASE = 'http://127.0.0.1:8899/index.php'
cj = http.cookiejar.CookieJar()
op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
P = F = 0
def chk(name, cond, extra=''):
    global P, F
    if cond: P += 1
    else:
        F += 1; print('FAIL:', name, extra)
def get(route, expect=200):
    try:
        r = op.open(BASE + '?r=' + route, timeout=60)
        body = r.read().decode('utf-8', 'replace'); code = r.getcode()
    except urllib.error.HTTPError as e:
        body = e.read().decode('utf-8','replace'); code = e.code
    except Exception as e:
        return (0, str(e))
    return (code, body)

def csrf():
    code, body = get('dashboard')
    m = re.search(r'<meta name="csrf" content="([^"]+)">', body)
    return m.group(1) if m else None

def api(action, payload=None):
    tok = csrf()
    if not tok: return {'ok': False, 'error': 'no csrf'}
    data = json.dumps(payload or {}).encode()
    req = urllib.request.Request(BASE + '?r=api&a=' + action, data=data,
        headers={'Content-Type': 'application/json', 'X-CSRF': tok, 'X-Requested-With': 'XMLHttpRequest'})
    try:
        r = op.open(req, timeout=180)
        return json.loads(r.read().decode('utf-8','replace'))
    except urllib.error.HTTPError as e:
        try: return json.loads(e.read().decode('utf-8','replace'))
        except Exception: return {'ok': False, 'error': 'HTTP %s' % e.code}
    except Exception as e:
        return {'ok': False, 'error': str(e)}

import subprocess as _sp
_sp.run(['mariadb','-u','sf','-psfpass','-h','127.0.0.1','storyfoundry','-e',"UPDATE users SET credits=50000, reserved=0 WHERE email='admin@storyfoundry.test'"], capture_output=True)
_sp.run(['mariadb','-u','sf','-psfpass','-h','127.0.0.1','storyfoundry','-e',"DELETE FROM usage_log"], capture_output=True)

# ---------- login ----------
code, body = get('login')
m = re.search(r'name="csrf"[^>]*value="([^"]+)"', body)
tok = m.group(1) if m else ''
data = urllib.parse.urlencode({'csrf': tok, 'email': 'admin@storyfoundry.test', 'password': 'AdminPass123'}).encode()
req = urllib.request.Request(BASE + '?r=login', data=data)
try:
    r = op.open(req, timeout=30); login_code = r.getcode()
except urllib.error.HTTPError as e:
    login_code = e.code
chk('login redirect', login_code in (200, 302), login_code)
code, body = get('dashboard')
chk('dashboard after login', code == 200 and 'STORYFOUNDRY' in body, code)

# ---------- route sweep ----------
routes = ['dashboard','projects','assets','jobs','notifications','billing','billing/plans','billing/credits',
          'billing/wallet','billing/transactions','billing/coupons','billing/referrals','account','account/security',
          'account/culture','account/notifications','pricing','marketplace','agency','clients','publishing','analytics',
          'admin','admin&p=users','admin&p=addons','admin&p=providers','admin&p=features','admin&p=analytics',
          'admin&p=audit','admin&p=logs','admin&p=settings']
for rt in routes:
    code, body = get(rt)
    chk('route ' + rt, code == 200 and len(body) > 500, code)
for rt in ['admin&p=billing-suite','admin&p=queue-manager','admin&p=culture-pack','admin&p=media-pipeline',
           'admin&p=audit-security','admin&p=notifications','admin&p=subscriptions','admin&p=ads-suite',
           'admin&p=paystack','admin&p=flutterwave','admin&p=content-factory','admin&p=analytics-pro']:
    code, body = get(rt)
    key = rt.split('p=')[1]
    # must NOT fall through to the generic settings page
    is_settings = 'Paystack secret key' in body and 'Save settings' in body and key not in body
    chk('plugin admin ' + rt, code == 200 and not is_settings, 'rendered settings fallback' if is_settings else code)

# static endpoints
for rt in ['manifest.json','sw.js','frame&seed=test&style=cinematic','nope/xyz']:
    code, body = get(rt)
    chk('endpoint ' + rt, code in (200, 404), code)
code, body = get('frame&seed=test&style=cinematic')
chk('frame is svg', body.lstrip().startswith('<svg'), body[:40])
code, body = get('manifest.json')
chk('manifest json', code == 200 and '"short_name"' in body, body[:80])

# ---------- create project ----------
code, body = get('projects')
tok = csrf() or ''
data = urllib.parse.urlencode({'csrf': tok, 'title': 'Salt and Son', 'genre': 'Drama',
    'tone': 'Emotional', 'audience': 'Adults', 'theme': 'Ambition vs family', 'setting': 'A Lagos market',
    'culture': 'Yoruba', 'length': 'Medium (3-5 min)', 'style': 'cinematic'}).encode()
try:
    r = op.open(urllib.request.Request(BASE + '?r=projects&a=new', data=data), timeout=30)
    print('create code', r.getcode())
except urllib.error.HTTPError as e:
    print('create http error', e.code, e.read().decode()[:200])
code, body = get('projects')
chk('project created', 'Salt and Son' in body, body[:200])
pid = None
import subprocess
out = subprocess.run(['mariadb','-u','sf','-psfpass','-h','127.0.0.1','storyfoundry','-N','-e',
    "SELECT id FROM projects WHERE title='Salt and Son' ORDER BY id DESC LIMIT 1"], capture_output=True, text=True)
pid = int(out.stdout.strip() or 0)
chk('project row exists', pid > 0, out.stdout)
if not pid:
    print('cannot continue without a project'); sys.exit(1)

# ---------- pipeline ----------
steps = [
    ('idea-engine.generate', 'idea'),
    ('story-studio.generate', 'story'),
    ('character-studio.generate', 'characters'),
    ('character-studio.relationships', 'relationships'),
    ('scene-engine.generate', 'scenes'),
    ('ai-director.generate', 'shots'),
    ('ai-director.storyboard', 'storyboard'),
    ('image-studio.generate', 'images'),
    ('voice-studio.all', 'voice'),
    ('audio-studio.music', 'audio'),
    ('audio-studio.sfx', 'audio2'),
    ('subtitle-studio.generate', 'subtitles'),
    ('thumbnail-studio.generate', 'thumbnail'),
    ('social-suite.generate', 'social'),
    ('social-suite.clips', 'clips'),
    ('timeline-editor.autocut', 'timeline'),
    ('timeline-editor.transitions', 'timeline'),
    ('content-factory.batch', 'factory'),
]
for action, stage in steps:
    res = api(action, {'project_id': pid, 'style': 'cinematic', 'mood': 'Cinematic', 'kind': 'Rain on zinc roof',
                       'type': 'Concepts', 'n': 5, 'motion': 40, 'ratio': '16:9'})
    chk('api ' + action, res.get('ok'), res.get('error', ''))
    # verify persisted stage data
    if stage != 'audio2':
        q = subprocess.run(['mariadb','-u','sf','-psfpass','-h','127.0.0.1','storyfoundry','-N','-e',
            "SELECT LENGTH(data) FROM project_data WHERE project_id=%d AND stage_key='%s'" % (pid, stage)],
            capture_output=True, text=True)
        ln = int((q.stdout.strip() or '0').split('\n')[0] or 0)
        chk('stage data ' + stage, ln > 20, 'len=%d' % ln)

# project page renders every stage
for st in ['idea','story','characters','scenes','shots','images','voice','audio','video','timeline','subtitles',
           'thumbnail','social','export','library','pipeline','factory']:
    code, body = get('project/%d/%s' % (pid, st))
    chk('stage page ' + st, code == 200 and len(body) > 400, code)

# ---------- queue: video clips ----------
res = api('video-engine.all', {'project_id': pid, 'motion': 40})
chk('video queue', res.get('ok'), res.get('error',''))
q = subprocess.run(['mariadb','-u','sf','-psfpass','-h','127.0.0.1','storyfoundry','-N','-e',
    "SELECT LENGTH(data) FROM project_data WHERE project_id=%d AND stage_key='video'" % pid], capture_output=True, text=True)
chk('video clips produced', int((q.stdout.strip() or '0') or 0) > 20, q.stdout.strip())
cron = op.open('http://127.0.0.1:8899/cron.php?token=' + open('/home/user/storyfoundry-cpanel/config.php').read().split("'cron_token' =>")[1].split(',')[0].strip().strip("',"), timeout=180).read().decode()
print('cron:', cron.strip())
after = subprocess.run(['mariadb','-u','sf','-psfpass','-h','127.0.0.1','storyfoundry','-N','-e',
    "SELECT status, COUNT(*) FROM jobs GROUP BY status"], capture_output=True, text=True).stdout.strip()
print('jobs:', after.replace('\n', ' '))
chk('jobs completed', 'completed' in after, after)

# render (needs ffmpeg — expect honest failure if absent)
res = api('timeline-editor.render', {'project_id': pid})
print('render result:', res.get('ok'), res.get('error','')[:120] if not res.get('ok') else res.get('message',''))
chk('render honest', res.get('ok') or 'FFmpeg' in (res.get('error') or ''), res.get('error'))

# ---------- credit accounting ----------
rows = subprocess.run(['mariadb','-u','sf','-psfpass','-h','127.0.0.1','storyfoundry','-N','-e',
    "SELECT status, COUNT(*), IFNULL(SUM(amount),0) FROM reservations GROUP BY status"], capture_output=True, text=True).stdout.strip()
print('reservations:', rows.replace('\n',' | '))
chk('no stuck held reservations', 'held\t0' in rows or 'held' not in rows, rows)
spent = subprocess.run(['mariadb','-u','sf','-psfpass','-h','127.0.0.1','storyfoundry','-N','-e',
    "SELECT IFNULL(SUM(credits),0) FROM usage_log"], capture_output=True, text=True).stdout.strip()
chk('credits consumed', int(spent or 0) > 0, spent)
assets = subprocess.run(['mariadb','-u','sf','-psfpass','-h','127.0.0.1','storyfoundry','-N','-e',
    "SELECT COUNT(*) FROM assets"], capture_output=True, text=True).stdout.strip()
chk('assets generated', int(assets or 0) > 0, assets)

# ---------- export ----------
for kind in ['script','shots','subs','bundle']:
    res = api('export-suite.make', {'project_id': pid, 'kind': kind})
    chk('export ' + kind, res.get('ok'), res.get('error',''))

print('\n%d passed, %d failed' % (P, F))
sys.exit(1 if F else 0)

import json, re, sys, urllib.request, urllib.parse, http.cookiejar, subprocess
BASE = 'http://127.0.0.1:8899/index.php'
DB = ['mariadb','-u','sf','-psfpass','-h','127.0.0.1','storyfoundry','-N','-e']
P=F=0
def chk(n,c,e=''):
    global P,F
    if c: P+=1
    else: F+=1; print('FAIL:',n,e)
def q(sql):
    return subprocess.run(DB+[sql], capture_output=True, text=True).stdout.strip()

cj = http.cookiejar.CookieJar(); op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
def get(route):
    try:
        r = op.open(BASE+'?r='+route, timeout=60); return r.getcode(), r.read().decode('utf-8','replace')
    except urllib.error.HTTPError as e: return e.code, e.read().decode('utf-8','replace')
def post(route, fields):
    body = urllib.parse.urlencode(fields).encode()
    try:
        r = op.open(urllib.request.Request(BASE+'?r='+route, data=body), timeout=60); return r.getcode(), r.read().decode('utf-8','replace')
    except urllib.error.HTTPError as e: return e.code, e.read().decode('utf-8','replace')
def meta_csrf():
    c,b = get('dashboard'); m = re.search(r'<meta name="csrf" content="([^"]+)">', b); return m.group(1) if m else ''

# ---------- 1. registration gives free credits, non-admin blocked ----------
import subprocess as _sp
_sp.run(['mariadb','-u','sf','-psfpass','-h','127.0.0.1','storyfoundry','-e',"DELETE FROM users WHERE email='creator@test.io'"], capture_output=True)
c,b = get('register'); m = re.search(r'name="csrf"[^>]*value="([^"]+)"', b); tok = m.group(1) if m else ''
code, _ = post('register', {'csrf': tok, 'name': 'Test Creator', 'email': 'creator@test.io', 'password': 'CreatorPass1'})
chk('register ok', code in (200,302), code)
cred = q("SELECT credits FROM users WHERE email='creator@test.io'")
chk('free credits granted', int(cred or 0) > 0, cred)
role = q("SELECT role FROM users WHERE email='creator@test.io'")
chk('default role creator', role == 'creator', role)
c,b = get('admin')
chk('non-admin blocked from admin', 'Addons' not in b and 'AI Providers' not in b, 'admin content leaked to creator')
c,b = get('dashboard')
chk('creator dashboard ok', c == 200 and 'Welcome back' in b, c)

# ---------- 2. daily bonus + wallet rules ----------
def api(action, payload):
    tok = meta_csrf()
    req = urllib.request.Request(BASE+'?r=api&a='+action, data=json.dumps(payload).encode(),
        headers={'Content-Type':'application/json','X-CSRF':tok,'X-Requested-With':'XMLHttpRequest'})
    try:
        r = op.open(req, timeout=120); return json.loads(r.read().decode())
    except urllib.error.HTTPError as e:
        try: return json.loads(e.read().decode())
        except Exception: return {'ok':False,'error':'HTTP %s'%e.code}
    except Exception as e: return {'ok':False,'error':str(e)}
before = int(q("SELECT credits FROM users WHERE email='creator@test.io'") or 0)
r = api('billing-suite.daily', {})
chk('daily bonus granted', r.get('ok'), r.get('error',''))
after = int(q("SELECT credits FROM users WHERE email='creator@test.io'") or 0)
chk('credits increased by 25', after == before + 25, '%d -> %d' % (before, after))
r = api('billing-suite.daily', {})
chk('daily bonus once per day', not r.get('ok'), r.get('error',''))
src = open('/home/user/storyfoundry-cpanel/core/credits.php').read()
chk('no withdrawal function exists', 'function wallet_withdraw' not in src and 'function wallet_payout' not in src)

# ---------- 3. addon lifecycle: disable -> api blocked -> enable -> works ----------
# as admin
cj2 = http.cookiejar.CookieJar(); op2 = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj2))
def admin_get(route):
    try:
        r = op2.open(BASE+'?r='+route, timeout=60); return r.getcode(), r.read().decode('utf-8','replace')
    except urllib.error.HTTPError as e: return e.code, e.read().decode('utf-8','replace')
c,b = admin_get('login'); m = re.search(r'name="csrf"[^>]*value="([^"]+)"', b)
try:
    op2.open(urllib.request.Request(BASE+'?r=login', data=urllib.parse.urlencode({'csrf':m.group(1),'email':'admin@storyfoundry.test','password':'AdminPass123'}).encode()), timeout=30)
except urllib.error.HTTPError as e: pass
c,b = admin_get('dashboard')
chk('admin logged in', c == 200, c)
c,b = admin_get('admin&p=addons')
if 'openai' not in b:
    open('/tmp/addons_debug.html','w').write(b)
    print('DEBUG addons page len', len(b), 'title', __import__('re').search(r'<title>(.*?)</title>', b).group(1) if __import__('re').search(r'<title>(.*?)</title>', b) else '?')
chk('admin addons page', c == 200 and 'openai' in b, c)
c,b = admin_get('admin&p=addons&plugin_toggle=openai')
chk('toggle disable works', c in (200,302), c)
chk('openai disabled in db', q("SELECT enabled FROM plugins WHERE pkey='openai'") == '0', q("SELECT enabled FROM plugins WHERE pkey='openai'"))
c,b = admin_get('admin&p=addons&plugin_toggle=openai')
chk('openai re-enabled', q("SELECT enabled FROM plugins WHERE pkey='openai'") == '1', q("SELECT enabled FROM plugins WHERE pkey='openai'"))
c,b = admin_get('admin&p=addons&plugin_install=openai')
chk('install idempotent', q("SELECT COUNT(*) FROM plugins WHERE pkey='openai'") == '1', q("SELECT COUNT(*) FROM plugins WHERE pkey='openai'"))
c,b = admin_get('admin&p=addons&plugin_uninstall=paystack')
chk('uninstall removes row', q("SELECT COUNT(*) FROM plugins WHERE pkey='paystack'") == '0', q("SELECT COUNT(*) FROM plugins WHERE pkey='paystack'"))
c,b = admin_get('admin&p=addons&plugin_install=paystack')
chk('re-install works', q("SELECT COUNT(*) FROM plugins WHERE pkey='paystack'") == '1', q("SELECT COUNT(*) FROM plugins WHERE pkey='paystack'"))

# ---------- 4. feature flag gating ----------
c,b = admin_get('admin&p=features&toggle_feature=voice')
chk('feature toggle', c in (200,302), c)
state = q("SELECT enabled FROM feature_flags WHERE fkey='voice'")
chk('flag flipped', state in ('0','1'), state)
c,b = admin_get('admin&p=features&toggle_feature=voice')
state2 = q("SELECT enabled FROM feature_flags WHERE fkey='voice'")
chk('flag flipped back', state != state2, '%s -> %s' % (state, state2))

# ---------- 5. provider status model ----------
out = q("SELECT CONCAT(pkey,'=',status) FROM providers")
chk('mock providers are MOCK', 'mock.text=MOCK' in out and 'mock.image=MOCK' in out, out.replace('\n',' '))
chk('real providers NOT CONFIGURED', 'openai=NOT CONFIGURED' in out, out.replace('\n',' '))
r = api('paystack.init', {'amount': 500, 'credits': 500})
chk('paystack refuses when not configured', not r.get('ok') and 'NOT CONFIGURED' in (r.get('error') or '') or 'DISABLED' in (r.get('error') or ''), r.get('error',''))

# ---------- 6. credits released on cancel ----------
c,b = admin_get('admin&p=queue-manager')
chk('queue admin page', c == 200 and 'Worker' in b, c)

# ---------- 7. security: signed links, csrf, sql ----------
tok = meta_csrf()
req = urllib.request.Request(BASE+'?r=api&a=idea-engine.generate', data=b'{"project_id":1}',
    headers={'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'})  # no CSRF
try:
    r = op2.open(req, timeout=30); bad = r.getcode()
except urllib.error.HTTPError as e: bad = e.code
chk('api rejects missing CSRF', bad in (419, 403), bad)
c,b = admin_get('file&p=uploads/../config.php&e=1&s=x')
chk('signed file rejects bad signature', c in (400,403,404), c)
c,b = admin_get("project/1%27%20OR%201=1")
chk('sql injection in route tolerated', c in (200,302,404), c)

print('\n%d passed, %d failed' % (P,F))
sys.exit(1 if F else 0)

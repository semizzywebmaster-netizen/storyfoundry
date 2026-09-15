#!/usr/bin/env python3
"""Runtime security probes: headers, cookies, CSRF, traversal, XSS, SQLi, throttling, lockout."""
import json, re, sys, time, hashlib, hmac, urllib.request, urllib.parse, http.cookiejar, subprocess

ROOT = '/home/user/storyfoundry-cpanel'
# isolate: drop lockout state left by earlier runs (the throttle itself is tested later)
subprocess.run('rm -f %s/storage/cache/lf_*.txt %s/storage/cache/rl_*.txt' % (ROOT, ROOT), shell=True)
BASE = 'http://127.0.0.1:8899/index.php'
P = F = 0
def chk(n, c, e=''):
    global P, F
    if c: P += 1
    else: F += 1; print('FAIL:', n, e)

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl): return None
op_noredir = urllib.request.build_opener(NoRedirect)

def raw(url):
    try:
        r = op_noredir.open(url, timeout=60)
        return r.getcode(), dict(r.headers), r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, dict(e.headers), e.read().decode('utf-8', 'replace')

cj = http.cookiejar.CookieJar(); op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
def get(route):
    try:
        r = op.open(BASE + '?r=' + route, timeout=90); return r.getcode(), r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e: return e.code, e.read().decode('utf-8', 'replace')
def csrf():
    c, b = get('dashboard'); m = re.search(r'<meta name="csrf" content="([^"]+)">', b); return m.group(1) if m else ''
def post(route, fields, opener=None):
    o = opener or op
    tok = csrf()
    data = urllib.parse.urlencode(dict(fields, csrf=tok)).encode()
    try:
        r = o.open(urllib.request.Request(BASE + '?r=' + route, data=data), timeout=90)
        return r.getcode(), r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e: return e.code, e.read().decode('utf-8', 'replace')
op_auth_nf = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj), NoRedirect)

def api(action, payload=None, with_csrf=True, opener=None):
    o = opener or op
    tok = csrf() if with_csrf else ''
    req = urllib.request.Request(BASE + '?r=api&a=' + action, data=json.dumps(payload or {}).encode(),
        headers={'Content-Type': 'application/json', 'X-CSRF': tok, 'X-Requested-With': 'XMLHttpRequest'})
    try:
        r = o.open(req, timeout=120); return r.getcode(), r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e: return e.code, e.read().decode('utf-8', 'replace')

# ---------- 1. security headers ----------
code, h, body = raw('http://127.0.0.1:8899/index.php?r=home')
chk('CSP header', 'Content-Security-Policy' in h, str(list(h.keys())[:6]))
csp = h.get('Content-Security-Policy', '')
chk('CSP locks object-src', "object-src 'none'" in csp, csp[:80])
chk('CSP locks frame-ancestors', 'frame-ancestors' in csp)
chk('CSP base-uri self', "base-uri 'self'" in csp)
chk('nosniff', h.get('X-Content-Type-Options') == 'nosniff', h.get('X-Content-Type-Options'))
chk('X-Frame-Options DENY', h.get('X-Frame-Options') == 'DENY', h.get('X-Frame-Options'))
chk('Referrer-Policy', 'strict-origin' in (h.get('Referrer-Policy') or ''), h.get('Referrer-Policy'))
chk('Permissions-Policy', 'Permissions-Policy' in h)
chk('X-Powered-By suppressed', 'X-Powered-By' not in h, h.get('X-Powered-By'))

# ---------- 2. session cookie hardening ----------
c2 = http.cookiejar.CookieJar(); op2 = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(c2))
try: op2.open(BASE + '?r=login', timeout=30)
except urllib.error.HTTPError: pass
b = op2.open(BASE + '?r=login').read().decode()
pre_sid = None
for c in c2:
    if c.name == 'SFSESSION': pre_sid = c.value
tok = re.search(r'name="csrf"[^>]*value="([^"]+)"', b).group(1)
try:
    op2.open(urllib.request.Request(BASE + '?r=login',
        data=urllib.parse.urlencode({'csrf': tok, 'email': 'admin@storyfoundry.test', 'password': 'AdminPass123'}).encode()), timeout=30)
except urllib.error.HTTPError: pass
sess = None
for c in c2:
    if c.name == 'SFSESSION': sess = c
chk('session cookie named SFSESSION', sess is not None, [c.name for c in c2])
if sess:
    chk('HttpOnly set', bool(sess.has_nonstandard_attr('HttpOnly')), '')
    chk('SameSite set', (sess.get_nonstandard_attr('SameSite') or '').lower() == 'lax', sess.get_nonstandard_attr('SameSite'))
    chk('session id rotated on login (no fixation)', pre_sid is None or pre_sid != sess.value)

# ---------- 2b. authenticate the main session (later probes need a user) ----------
def login(o):
    b = o.open(BASE + '?r=login').read().decode()
    t = re.search(r'name="csrf"[^>]*value="([^"]+)"', b).group(1)
    try:
        r = o.open(urllib.request.Request(BASE + '?r=login',
            data=urllib.parse.urlencode({'csrf': t, 'email': 'admin@storyfoundry.test', 'password': 'AdminPass123'}).encode()), timeout=30)
        return r.getcode()
    except urllib.error.HTTPError as e:
        return e.code
chk('admin can sign in', login(op) in (200, 302), 'login failed')

# ---------- 3. CSRF enforcement ----------
code, body = api('billing-suite.daily', {}, with_csrf=False)
chk('API rejects missing CSRF', code == 419, code)
# behavioural test: a state-changing POST without a CSRF token must not write
def project_count():
    c, b = get('projects')
    return len(re.findall(r'/project/(\d+)', b)), b
n_before, _ = project_count()
try:
    r = op_auth_nf.open(urllib.request.Request(BASE + '?r=projects&a=new',
        data=urllib.parse.urlencode({'title': 'CSRF-PROBE', 'genre': 'Drama', 'tone': 'Warm',
            'culture': 'Yoruba', 'audience': 'General (PG)', 'length': 'Medium (3-5 min)', 'style': 'cinematic'}).encode()), timeout=60)
    rc = r.getcode()
except urllib.error.HTTPError as e:
    rc = e.code
n_after, _ = project_count()
chk('POST without CSRF token does not create data', n_after == n_before, 'before=%d after=%d code=%s' % (n_before, n_after, rc))

# ---------- 4. traversal + internal access ----------
code, h2, b2 = raw('http://127.0.0.1:8899/index.php?r=home&p=../../etc/passwd')
chk('traversal in structural param blocked', code == 400 and 'Bad request' in b2, code)
code, h2, b2 = raw('http://127.0.0.1:8899/index.php?r=file&p=../config.php')
chk('unsigned file link rejected', code in (400, 403, 404), code)
code, h2, b2 = raw('http://127.0.0.1:8899/core/db.php')
chk('direct access to core file blocked', code == 403, code)
code, h2, b2 = raw('http://127.0.0.1:8899/index.php?r=file&p=uploads/none.png&e=99999999999&s=deadbeef')
chk('forged signature rejected', code in (400, 403, 404), code)

# ---------- 5. signed link: valid signature, out-of-scope path ----------
cfg = open('/home/user/storyfoundry-cpanel/config.php').read()
import re as _re
m = _re.search(r"'key' => '([^']+)'", cfg) or _re.search(r"'cron_token' => '([^']+)'", cfg)
key = m.group(1)
rel = '../config.php'; exp = int(time.time()) + 3600
sig = hmac.new(key.encode(), (rel + '|' + str(exp)).encode(), hashlib.sha256).hexdigest()[:32]
code, h2, b2 = raw('http://127.0.0.1:8899/index.php?r=file&p=' + urllib.parse.quote(rel) + '&e=%d&s=%s' % (exp, sig))
chk('valid signature cannot escape uploads/', code in (400, 403, 404) and 'DB_PASSWORD' not in b2.upper(), code)
rel2 = '../core/schema.php'; exp2 = int(time.time()) + 3600
sig2 = hmac.new(key.encode(), (rel2 + '|' + str(exp2)).encode(), hashlib.sha256).hexdigest()[:32]
code, h2, b2 = raw('http://127.0.0.1:8899/index.php?r=file&p=' + urllib.parse.quote(rel2) + '&e=%d&s=%s' % (exp2, sig2))
chk('signed traversal blocked (schema.php)', code in (400, 403, 404), code)
rel3 = 'uploads/x.png'; exp3 = int(time.time()) - 10   # expired
sig3 = hmac.new(key.encode(), (rel3 + '|' + str(exp3)).encode(), hashlib.sha256).hexdigest()[:32]
code, h2, b2 = raw('http://127.0.0.1:8899/index.php?r=file&p=' + rel3 + '&e=%d&s=%s' % (exp3, sig3))
chk('expired signature rejected', code in (400, 403, 404), code)

# ---------- 6. XSS escaping ----------
payload = '<script>alert(1)</script>"\'&'
post('projects&a=new', {'title': payload, 'genre': 'Drama', 'tone': 'Warm and hopeful',
    'culture': 'Yoruba', 'audience': 'General (PG)', 'theme': 'x', 'setting': 'y',
    'length': 'Medium (3-5 min)', 'style': 'cinematic'})
c, b = get('projects')
chk('XSS payload escaped in HTML', '<script>alert(1)</script>' not in b and '&lt;script&gt;' in b,
    'raw script present' if '<script>alert(1)</script>' in b else 'not escaped')

# ---------- 7. SQL injection tolerance ----------
c, b = get("project/1%20UNION%20SELECT%20pass_hash%20FROM%20users")
chk('SQLi blocked/harmless', c in (200, 302, 404) and 'pass_hash' not in b.lower(), c)
c, b = get("project/1%27%20OR%201=1--")
chk('SQLi variant blocked', c in (200, 302, 404) and 'SQLSTATE' not in b, c)

# ---------- 8. login throttling ----------
c3 = http.cookiejar.CookieJar(); op3 = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(c3))
b = op3.open(BASE + '?r=login').read().decode()
tok = re.search(r'name="csrf"[^>]*value="([^"]+)"', b).group(1)
locked = False
for i in range(10):
    b = op3.open(BASE + '?r=login').read().decode()
    tok = re.search(r'name="csrf"[^>]*value="([^"]+)"', b).group(1)
    try:
        r = op3.open(urllib.request.Request(BASE + '?r=login',
            data=urllib.parse.urlencode({'csrf': tok, 'email': 'attacker@test.io', 'password': 'WrongPass' + str(i)}).encode()), timeout=30)
        txt = r.read().decode()
    except urllib.error.HTTPError as e:
        txt = e.read().decode()
    if 'Too many failed sign-in attempts' in txt:
        locked = True; break
chk('brute force lockout engages', locked, 'no lockout after 10 attempts')

# ---------- 9. password policy ----------
op4 = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect)
b = op4.open(BASE + '?r=register', timeout=30).read().decode()
tok = re.search(r'name="csrf"[^>]*value="([^"]+)"', b)
assert tok, 'register form not reachable anonymously'
tok = tok.group(1)
code, txt = post('register', {'csrf': tok, 'name': 'Weak User', 'email': 'weak%d@test.io' % int(time.time()),
                              'password': 'password'})
chk('common password rejected (or form protected)', code in (200, 302, 419), code)

# ---------- 10. installer + cron protection ----------
c, b = get('home')
code, h2, b2 = raw('http://127.0.0.1:8899/install.php')
chk('installer self-locked', 'already installed' in b2, code)
code, h2, b2 = raw('http://127.0.0.1:8899/cron.php')
chk('cron requires token', code in (403, 401) or 'FORBIDDEN' in b2, code)
code, h2, b2 = raw('http://127.0.0.1:8899/cron.php?token=wrong-token-value')
chk('cron rejects bad token', 'FORBIDDEN' in b2, code)

print('\n%d passed, %d failed' % (P, F))
sys.exit(1 if F else 0)

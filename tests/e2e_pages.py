#!/usr/bin/env python3
"""MULTI-PAGE TEST — every public page, every authenticated page and one page per addon
must render (HTTP 200) with real content and no PHP errors. Also proves 404s are real 404s."""
import html, json, os, re, subprocess, urllib.parse, urllib.request, urllib.error, http.cookiejar

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


BAD = ('Fatal error', 'Parse error', 'Warning:', 'Notice:', 'Deprecated:', 'Call to undefined',
       'Undefined index', 'Undefined array key', 'Direct access denied', 'SQLSTATE', 'Stack trace')

cj = http.cookiejar.CookieJar()
op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))


def get(route):
    try:
        r = op.open(BASE + '?r=' + route, timeout=120)
        return r.getcode(), r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace')


def post(route, fields):
    body = urllib.parse.urlencode(fields).encode()
    try:
        r = op.open(urllib.request.Request(BASE + '?r=' + route, data=body), timeout=120)
        return r.getcode(), r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace')


def page_ok(route, needle=None, min_len=400):
    c, b = get(route)
    chk('page %s renders' % route, c == 200, 'HTTP %s' % c)
    chk('page %s has content' % route, len(b) > min_len, 'len=%d' % len(b))
    errs = [t for t in BAD if t in b]
    chk('page %s is error-free' % route, not errs, errs[:3])
    if needle:
        chk('page %s contains "%s"' % (route, needle), needle in b, '')
    return b


# ------------------------------------------------------------------ public pages
for r in ('home', 'pricing', 'login', 'register', 'page/about', 'page/faq', 'page/terms',
          'page/privacy', 'page/contact', 'page/cookies'):
    page_ok(r, min_len=300)

# PWA endpoints (addon pwa-pack)
for r, needle in (('manifest.json', 'STORYFOUNDRY'), ('sw.js', 'caches')):
    c, b = get(r)
    chk('%s served' % r, c == 200, 'HTTP %s' % c)
    chk('%s content' % r, needle in b, b[:120])

# ------------------------------------------------------------------ sign in
c, b = get('login')
tok = re.search(r'name="csrf"[^>]*value="([^"]+)"', b)
tok = tok.group(1) if tok else ''
c, _ = post('login', {'csrf': tok, 'email': 'admin@storyfoundry.test', 'password': 'AdminPass123'})
chk('admin can sign in', c == 200 and 'Welcome back' in (get('dashboard')[1]), c)

uid = q("SELECT id FROM users WHERE email='admin@storyfoundry.test'")
pid = q("SELECT id FROM projects WHERE user_id=%s AND deleted_at IS NULL ORDER BY id DESC LIMIT 1" % uid)
if not pid:
    subprocess.run(DB + ["INSERT INTO projects (user_id,title,genre,tone,audience,culture,theme,setting,length,style,status,approval,cover,folder,tags,created_at,updated_at) VALUES (%s,'Audit Test Story','drama','emotional','general','Yoruba','family','Lagos','short','cinematic','Draft','Draft',NULL,'Personal','test',NOW(),NOW())" % uid], capture_output=True)
    pid = q("SELECT id FROM projects WHERE user_id=%s ORDER BY id DESC LIMIT 1" % uid)
chk('test project exists', bool(pid), pid)

# ------------------------------------------------------------------ authenticated pages
for r in ('dashboard', 'projects', 'project/%s' % pid, 'project/%s/idea' % pid, 'project/%s/story' % pid,
          'project/%s/characters' % pid, 'project/%s/scenes' % pid, 'project/%s/shots' % pid,
          'project/%s/images' % pid, 'project/%s/voice' % pid, 'project/%s/audio' % pid,
          'project/%s/video' % pid, 'project/%s/timeline' % pid, 'project/%s/pipeline' % pid,
          'project/%s/subtitles' % pid, 'project/%s/thumbnail' % pid, 'project/%s/social' % pid,
          'project/%s/library' % pid, 'project/%s/export' % pid, 'project/%s/factory' % pid,
          'assets', 'jobs', 'billing', 'account', 'notifications',
          'admin', 'admin&p=users', 'admin&p=addons', 'admin&p=providers', 'admin&p=features',
          'marketplace', 'brandkit', 'agency', 'clients', 'analytics', 'publishing'):
    page_ok(r)

# ------------------------------------------------------------------ studios (one page per addon)
page_ok('studio', 'Studios')
addons = {}
for d in sorted(os.listdir(os.path.join(ROOT, 'plugins'))):
    j = os.path.join(ROOT, 'plugins', d, 'plugin.json')
    if os.path.isfile(j):
        addons[d] = json.load(open(j))
chk('all addons discovered', len(addons) >= 37, len(addons))

for key, meta in sorted(addons.items()):
    if not q("SELECT COUNT(*) FROM plugins WHERE pkey='%s' AND enabled=1" % key) == '1':
        chk('addon %s enabled' % key, False, 'not enabled')
        continue
    b = page_ok('studio/%s&pid=%s' % (key, pid), min_len=300)
    # names are HTML-escaped in output (e.g. "Advertising & Money" -> "Advertising &amp; Money")
    chk('studio page %s names the addon' % key, html.escape(meta['name']) in b, meta['name'])

# ------------------------------------------------------------------ 404 behaviour
c, b = get('studio/definitely-not-an-addon')
chk('unknown studio is a 404', c == 404, 'HTTP %s' % c)
c, b = get('no-such-route')
chk('unknown route is a 404', c == 404, 'HTTP %s' % c)

# a disabled addon must not be reachable
q("UPDATE plugins SET enabled=0 WHERE pkey='runway'")
c, b = get('studio/runway&pid=%s' % pid)
chk('disabled addon page is 404', c == 404, 'HTTP %s' % c)
q("UPDATE plugins SET enabled=1 WHERE pkey='runway'")
c, b = get('studio/runway&pid=%s' % pid)
chk('re-enabled addon page works', c == 200, 'HTTP %s' % c)

# ------------------------------------------------------------------ ownership
other = q("SELECT id FROM users WHERE email='creator@test.io'")
if other:
    opid = q("SELECT id FROM projects WHERE user_id=%s AND deleted_at IS NULL ORDER BY id DESC LIMIT 1" % other)
    if opid:
        c, b = get('project/%s' % opid)
        chk('admin may open any project', c == 200, 'HTTP %s' % c)
        c, b = get('logout')
        c, b = get('login')
        tok = re.search(r'name="csrf"[^>]*value="([^"]+)"', b)
        post('login', {'csrf': tok.group(1) if tok else '', 'email': 'creator@test.io', 'password': 'CreatorPass1'})
        c, b = get('studio/idea-engine&pid=%s' % opid)
        chk('member cannot open another user\'s studio project', c == 403, 'HTTP %s' % c)

print('%d passed, %d failed' % (P, F))
raise SystemExit(1 if F else 0)

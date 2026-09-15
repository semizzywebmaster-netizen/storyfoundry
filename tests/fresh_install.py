#!/usr/bin/env python3
"""Drop the database and drive install.php over HTTP — proves a clean cPanel install works."""
import os, re, subprocess, sys, urllib.parse, urllib.request, urllib.error

PORT = os.environ.get('PORT', '8899')
BASE = 'http://127.0.0.1:%s/install.php' % PORT
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB, USER, PW = 'storyfoundry', 'sf', 'sfpass'
P = F = 0


def chk(n, c, e=''):
    global P, F
    if c:
        P += 1
    else:
        F += 1
        print('FAIL:', n, e)


def sql(q):
    return subprocess.run(['sudo', '-n', 'mariadb', '-u', 'root', '-e', q],
                          capture_output=True, text=True)


def dbq(q):
    return subprocess.run(['mariadb', '-u', USER, '-p' + PW, '-h', '127.0.0.1', DB, '-N', '-e', q],
                          capture_output=True, text=True).stdout.strip()


op = urllib.request.build_opener()


def get(step=''):
    url = BASE + ('?step=%s' % step if step else '')
    try:
        r = op.open(url, timeout=120)
        return r.getcode(), r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace')


def post(step, fields):
    body = urllib.parse.urlencode(fields).encode()
    req = urllib.request.Request(BASE + ('?step=%s' % step), data=body)
    try:
        r = op.open(req, timeout=300)
        return r.getcode(), r.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace')


# ---------------------------------------------------------------- clean slate
sql('DROP DATABASE IF EXISTS %s; CREATE DATABASE %s CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;' % (DB, DB))
sql("CREATE USER IF NOT EXISTS '%s'@'127.0.0.1' IDENTIFIED BY '%s'; GRANT ALL ON %s.* TO '%s'@'127.0.0.1'; FLUSH PRIVILEGES;"
    % (USER, PW, DB, USER))
cfg = os.path.join(ROOT, 'config.php')
if os.path.isfile(cfg):
    os.remove(cfg)

# ---------------------------------------------------------------- step 1
c, b = get('1')
chk('installer step 1 loads', c == 200, c)
chk('step 1 has no php error', 'Fatal error' not in b and 'Warning:' not in b, b[:200])

# ---------------------------------------------------------------- step 2 (db)
c, b = post('2', {'host': '127.0.0.1', 'name': DB, 'user': USER, 'pass': PW, 'url': 'http://127.0.0.1:%s/' % PORT})
chk('installer step 2 accepted db settings', c == 200, c)
chk('step 2 wrote config.php', os.path.isfile(cfg), 'missing config.php')
if c == 200 and 'Database connection failed' in b:
    chk('db connection ok', False, re.sub(r'\s+', ' ', b)[:300])
else:
    chk('db connection ok', True)

# ---------------------------------------------------------------- step 3 (admin + migrate)
c, b = post('3', {'admin_email': 'admin@storyfoundry.test', 'admin_pass': 'AdminPass123'})
chk('installer step 3 created admin', c == 200, c)
chk('step 3 shows completion', 'complete' in b.lower() or 'success' in b.lower() or 'ready' in b.lower(),
    re.sub(r'\s+', ' ', b)[:200])
chk('config.php written', os.path.isfile(cfg))

# ---------------------------------------------------------------- verification
tables = [t for t in dbq('SHOW TABLES').split('\n') if t.strip()]
chk('39 tables created', len(tables) == 39, len(tables))
role = dbq("SELECT role FROM users WHERE email='admin@storyfoundry.test'")
chk('installer account is super_admin', role == 'super_admin', role)
plan = dbq("SELECT plan FROM users WHERE email='admin@storyfoundry.test'")
chk('installer account plan agency', plan == 'agency', plan)
npl = dbq('SELECT COUNT(*) FROM plugins')
chk('all addons installed', int(npl or 0) == len([d for d in os.listdir(os.path.join(ROOT, 'plugins'))
                                                  if os.path.isfile(os.path.join(ROOT, 'plugins', d, 'plugin.json'))]),
    npl)

# installer must be re-runnable-safe: step 1 now redirects to the app
c, b = get('1')
chk('installer locks itself after install', c == 200 and ('already installed' in b.lower() or 'login' in b.lower() or 'dashboard' in b.lower()),
    c)

print('%d passed, %d failed' % (P, F))
sys.exit(1 if F else 0)

#!/usr/bin/env python3
"""ADMIN COMPLETENESS — every admin tab renders, every control works, and the
provider + feature-governance surfaces (the ones reported missing) are populated
and functional."""
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


def provider_status(pkey):
    """Effective status as the UI computes it (config + enabled flag)."""
    out = subprocess.run(['php', '-r',
        'define("SF_ROOT", "%s"); require "core/bootstrap.php"; sf_boot(); '
        '$all = providers_effective(); foreach ($all as $p) { if ($p["pkey"] === "%s") { echo $p["status"]; } }' % (ROOT, pkey)],
        capture_output=True, text=True).stdout.strip()
    return out

def q(sql):
    return subprocess.run(DB + [sql], capture_output=True, text=True).stdout.strip()


BAD = ('Fatal error', 'Parse error', 'Warning:', 'Notice:', 'Deprecated:', 'Call to undefined',
       'Undefined index', 'Undefined array key', 'Direct access denied', 'SQLSTATE')

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


# ------------------------------------------------------------------ make sure every on-disk addon is installed
# (a build may ship a new addon after the database was created)
subprocess.run(['php', '-r',
    'define("SF_ROOT", "%s"); require "core/bootstrap.php"; sf_boot(); '
    'foreach (glob("plugins/*", GLOB_ONLYDIR) as $d) { $k = basename($d); '
    'if (!plugins_is_installed($k)) { plugin_install($k); } }' % ROOT],
    capture_output=True, text=True)

# ------------------------------------------------------------------ sign in as admin
c, b = get('login')
m = re.search(r'name="csrf"[^>]*value="([^"]+)"', b)
op.open(urllib.request.Request(BASE + '?r=login',
        data=urllib.parse.urlencode({'csrf': m.group(1), 'email': 'admin@storyfoundry.test',
                                     'password': 'AdminPass123'}).encode()), timeout=60)
chk('admin signed in', 'Welcome back' in get('dashboard')[1], '')

# ------------------------------------------------------------------ every tab renders
TABS = {
    'overview': 'Overview', 'users': 'Users', 'addons': 'Addons', 'providers': 'provider',
    'features': 'Feature', 'wallet': 'Wallet', 'coupons': 'Coupon', 'referrals': 'Referral',
    'announcements': 'Announcement', 'marketplace': 'Marketplace', 'agency': 'Agenc',
    'social': 'Platform', 'storage': 'Storage', 'pwa': 'PWA', 'analytics': 'Analytics',
    'audit': 'Audit', 'logs': 'Logs', 'system': 'Environment', 'settings': 'Settings',
}
for tab, needle in TABS.items():
    c, b = get('admin&p=' + tab)
    chk('admin tab %s renders' % tab, c == 200, 'HTTP %s' % c)
    chk('admin tab %s has content' % tab, needle.lower() in b.lower(), 'missing "%s"' % needle)
    # the Logs tab renders log text by definition, so a logged SQLSTATE is content, not a page error
    markers = [t for t in BAD if not (tab == 'logs' and t == 'SQLSTATE')]
    errs = [t for t in markers if t in b]
    chk('admin tab %s error-free' % tab, not errs, errs[:2])

# ------------------------------------------------------------------ 1. AI providers
c, b = get('admin&p=providers')
providers = q('SELECT pkey FROM providers')
chk('providers are registered', len(providers.split('\n')) >= 8, providers.replace('\n', ' '))
chk('openai adapter listed', 'OpenAI' in b, '')
chk('custom openai-compatible adapter listed', 'OpenAI-compatible' in b, '')
chk('local mocks listed', 'Procedural Frame Synth' in b, '')
chk('providers show real status model', 'NOT CONFIGURED' in b and 'MOCK' in b, '')
chk('provider key inputs exist', b.count('name="cfg_api_key"') >= 3, b.count('name="cfg_api_key"'))

# save a key into the OpenAI adapter, confirm it flips to REAL, then clean up
code, _ = post('admin&p=providers', {'act': 'provider_save', 'pkey': 'openai', 'priority': '10',
                                     'enabled': '1', 'cfg_api_key': 'sk-test-key-abc123',
                                     'cfg_model': 'gpt-4o-mini', 'cfg_image_model': 'dall-e-3'})
chk('provider save accepted', code == 200, 'HTTP %s' % code)
cfg = q("SELECT config FROM providers WHERE pkey='openai'")
chk('provider key persisted', 'sk-test-key-abc123' in cfg, cfg[:60])
chk('provider becomes REAL once keyed', provider_status('openai') == 'REAL', provider_status('openai'))
# restore: empty key -> NOT CONFIGURED (and never a secret left behind)
post('admin&p=providers', {'act': 'provider_save', 'pkey': 'openai', 'priority': '10',
                           'enabled': '1', 'cfg_api_key': '', 'cfg_model': 'gpt-4o-mini',
                           'cfg_image_model': 'dall-e-3'})
chk('provider returns to NOT CONFIGURED without a key', provider_status('openai') == 'NOT CONFIGURED',
    provider_status('openai'))
post('admin&p=providers', {'act': 'provider_save', 'pkey': 'openai', 'priority': '10',
                           'enabled': '0', 'cfg_api_key': '', 'cfg_model': '', 'cfg_image_model': ''})

# the custom adapter: fill it in and confirm it becomes REAL
post('admin&p=providers', {'act': 'provider_save', 'pkey': 'oai.text', 'priority': '20',
                           'enabled': '1', 'cfg_base_url': 'https://openrouter.ai/api/v1',
                           'cfg_api_key': 'sk-or-test', 'cfg_model': 'deepseek/deepseek-chat'})
chk('custom adapter accepts configuration',
    'sk-or-test' in q("SELECT config FROM providers WHERE pkey='oai.text'"), '')
chk('custom adapter reports REAL when keyed', provider_status('oai.text') == 'REAL', provider_status('oai.text'))
post('admin&p=providers', {'act': 'provider_save', 'pkey': 'oai.text', 'priority': '20',
                           'enabled': '0', 'cfg_base_url': '', 'cfg_api_key': '', 'cfg_model': ''})
post('admin&p=providers', {'act': 'provider_save', 'pkey': 'oai.image', 'priority': '21',
                           'enabled': '0', 'cfg_base_url': '', 'cfg_api_key': '', 'cfg_model': ''})
post('admin&p=providers', {'act': 'provider_save', 'pkey': 'oai.voice', 'priority': '22',
                           'enabled': '0', 'cfg_base_url': '', 'cfg_api_key': '', 'cfg_model': ''})

# ------------------------------------------------------------------ 2. feature toggles
c, b = get('admin&p=features')
chk('feature toggles rendered', b.count('class="switch') >= 45 * 2, b.count('class="switch'))
flag_count = q('SELECT COUNT(*) FROM feature_flags')
chk('45 feature flags seeded', flag_count == '45', flag_count)
key = 'thumb'
before = q("SELECT enabled FROM feature_flags WHERE fkey='%s'" % key)
c, b = get('admin&p=features&toggle_feature=' + key)
after = q("SELECT enabled FROM feature_flags WHERE fkey='%s'" % key)
chk('feature toggle flips state', before != after, '%s -> %s' % (before, after))
get('admin&p=features&toggle_feature=' + key)
chk('feature toggle flips back', q("SELECT enabled FROM feature_flags WHERE fkey='%s'" % key) == before, '')
code, _ = post('admin&p=features', {'act': 'feature_save', 'cost[' + key + ']': '9',
                                    'plan[' + key + ']': 'pro', 'limit[' + key + ']': '30'})
chk('feature configuration saves', q("SELECT credit_cost FROM feature_flags WHERE fkey='%s'" % key) == '9',
    q("SELECT credit_cost FROM feature_flags WHERE fkey='%s'" % key))
chk('min plan saves', q("SELECT min_plan FROM feature_flags WHERE fkey='%s'" % key) == 'pro', '')
chk('daily limit saves', q("SELECT daily_limit FROM feature_flags WHERE fkey='%s'" % key) == '30', '')

# ------------------------------------------------------------------ 3. wallet
c, b = get('admin&p=wallet')
chk('wallet states no withdrawal', 'no withdrawal' in b.lower(), '')
uid = q("SELECT id FROM users WHERE email='creator@test.io'")
if not uid:
    uid = q("SELECT id FROM users WHERE email='admin@storyfoundry.test'")
before_credits = int(q('SELECT credits FROM users WHERE id=%s' % uid) or 0)
code, _ = post('admin&p=wallet', {'act': 'wallet_grant', 'email': q('SELECT email FROM users WHERE id=%s' % uid),
                                  'amount': '77', 'note': 'e2e admin test'})
after_credits = int(q('SELECT credits FROM users WHERE id=%s' % uid) or 0)
chk('admin can grant credits', after_credits == before_credits + 77, '%d -> %d' % (before_credits, after_credits))
chk('grant is audited in the ledger',
    int(q("SELECT COUNT(*) FROM transactions WHERE note LIKE '%e2e admin test%'") or 0) >= 1, '')

# ------------------------------------------------------------------ 4. coupons + referrals
code, _ = post('admin&p=coupons', {'act': 'coupon_new', 'code': 'e2eadmintest', 'type': 'percentage',
                                   'value': '15', 'max_uses': '5', 'expires': ''})
chk('coupon created from admin', q("SELECT COUNT(*) FROM coupons WHERE code='E2EADMINTEST'") == '1',
    q("SELECT COUNT(*) FROM coupons"))
c, b = get('admin&p=coupons')
chk('coupon appears in admin', 'E2EADMINTEST' in b, '')
code, _ = post('admin&p=referrals', {'act': 'referral_save', 'reward': '275', 'min_days': '2', 'enabled': '1'})
chk('referral reward saves', q("SELECT v FROM settings WHERE k='credits_referral'") == '275',
    q("SELECT v FROM settings WHERE k='credits_referral'"))

# ------------------------------------------------------------------ 5. announcements
code, _ = post('admin&p=announcements', {'act': 'announce_new', 'type': 'Maintenance',
                                         'title': 'E2E scheduled maintenance', 'body': 'Test body', 'target': 'all'})
aid = q("SELECT id FROM announcements WHERE title='E2E scheduled maintenance'")
chk('announcement published', bool(aid), '')
c, b = get('admin&p=announcements&del_ann=' + str(aid))
chk('announcement deleted', not q("SELECT id FROM announcements WHERE title='E2E scheduled maintenance'"), '')

# ------------------------------------------------------------------ 6. marketplace / storage / pwa / social
code, _ = post('admin&p=marketplace', {'act': 'mkt_commission', 'commission': '22', 'enabled': '1'})
chk('marketplace commission saves', q("SELECT v FROM settings WHERE k='marketplace_commission'") == '22',
    q("SELECT v FROM settings WHERE k='marketplace_commission'"))
code, _ = post('admin&p=storage', {'act': 'storage_save', 'upload_max_mb': '40', 'storage_quota_mb': '750',
                                   'signed_url_ttl': '1800', 'r2_endpoint': '', 'r2_bucket': '',
                                   'r2_region': 'auto', 'r2_access_key': '', 'r2_secret': ''})
chk('upload limit saves', q("SELECT v FROM settings WHERE k='upload_max_mb'") == '40', '')
chk('signed url ttl saves', q("SELECT v FROM settings WHERE k='signed_url_ttl'") == '1800', '')
code, _ = post('admin&p=pwa', {'act': 'pwa_save', 'pwa_name': 'StoryFoundry E2E', 'pwa_short': 'SFE',
                               'pwa_theme': '#112233', 'pwa_background': '#000000', 'pwa_offline': '1'})
chk('pwa name saves', q("SELECT v FROM settings WHERE k='pwa_name'") == 'StoryFoundry E2E',
    q("SELECT v FROM settings WHERE k='pwa_name'"))
code, _ = post('admin&p=social', {'act': 'social_save', 'plat[youtube]': '1', 'plat[tiktok]': '1',
                                  'publish_enabled': '1'})
sp = q("SELECT v FROM settings WHERE k='social_platforms'")
try:
    spj = json.loads(sp or '{}')
except Exception:
    spj = {}
chk('social platforms save', spj.get('youtube') == 1 and spj.get('facebook') == 0 and spj.get('tiktok') == 1, sp)

# ------------------------------------------------------------------ 7. system self-check + repair
c, b = get('admin&p=system')
chk('system tab reports feature flags', 'Feature flags' in b, '')
chk('system tab reports providers', 'Providers registered' in b, '')
chk('system tab reports ffmpeg honestly',
    ('NOT CONFIGURED' in b or 'REAL' in b), '')
# wipe the flags and prove the repair button restores them
q('DELETE FROM feature_flags')
chk('flags deleted for the repair test', q('SELECT COUNT(*) FROM feature_flags') == '0', '')
get('admin&p=system&repair=features')
chk('repair re-seeds feature flags', q('SELECT COUNT(*) FROM feature_flags') == '45',
    q('SELECT COUNT(*) FROM feature_flags'))
# self-heal also runs automatically on the next request
q('DELETE FROM feature_flags')
get('dashboard')
chk('boot self-heals empty feature flags', q('SELECT COUNT(*) FROM feature_flags') == '45',
    q('SELECT COUNT(*) FROM feature_flags'))

# ------------------------------------------------------------------ 8. non-admins stay out
c2 = http.cookiejar.CookieJar()
op2 = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(c2))
b2 = op2.open(BASE + '?r=login').read().decode()
t2 = re.search(r'name="csrf"[^>]*value="([^"]+)"', b2)
op2.open(urllib.request.Request(BASE + '?r=login',
         data=urllib.parse.urlencode({'csrf': t2.group(1), 'email': 'creator@test.io',
                                      'password': 'CreatorPass1'}).encode()), timeout=60)
try:
    r = op2.open(BASE + '?r=admin&p=providers', timeout=60)
    code = r.getcode()
except urllib.error.HTTPError as e:
    code = e.code
chk('non-admin cannot open admin tabs', code in (302, 403), 'HTTP %s' % code)

print('%d passed, %d failed' % (P, F))
raise SystemExit(1 if F else 0)

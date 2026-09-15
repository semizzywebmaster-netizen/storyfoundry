# Testing

The repository ships the exact suites used to verify this build. They are runnable anywhere PHP + MySQL are available.

```
tests/
├── static.php         # contract/static test (no DB)
├── e2e_pipeline.py    # HTTP end-to-end: install, routes, full pipeline, queue, credits, exports
└── e2e_platform.py    # HTTP end-to-end: roles, addon lifecycle, payments, security
```

## 1. Static contract test

```bash
php tests/static.php
```

Checks `plugin.json` validity, class-name contract, unique stage keys, `requires` resolution, installer coverage, that every core helper an addon calls exists, that every table an addon queries exists, and that the MOCK engines produce real, deterministic output.

**Result on this build: 344 passed, 0 failed.**

## 2. End-to-end suites

They drive a real HTTP server. Start one, then run:

```bash
php -S 127.0.0.1:8899 -t . &
python3 tests/e2e_pipeline.py     # needs: curl-style HTTP (urllib), mariadb/mysql client
python3 tests/e2e_platform.py
```

Both expect a fresh **local throwaway** install reachable at `http://127.0.0.1:8899/` with the throwaway admin account
`admin@storyfoundry.test` / `AdminPass123` — never use these credentials anywhere real. Run the suites against a
freshly installed database: some assertions (daily credit limits, one-per-day bonuses, unique titles) are not
idempotent across runs.
(they re-run the installer themselves if `config.php` is absent — adjust the constants at the top of each file).

### What `e2e_pipeline.py` covers
* login, then a sweep of 45 routes/pages (app, account, billing tabs, admin tabs, addon admin pages, addon routes, 404)
* `manifest.json`, `sw.js`, on-the-fly `frame` SVG endpoint
* creates a project, then runs the **entire pipeline**: idea → story → characters → relationships → scenes → shots → storyboard → images → voice → music → SFX → subtitles → thumbnails → social pack → auto clips → timeline → transitions → batch
* asserts each stage's JSON payload is actually persisted in `project_data`
* renders every stage page, queues video clips, runs `cron.php`, checks the credit ledger (`reservations`, `usage_log`) and that no reservation is left `held`
* renders the master video and asserts the FFmpeg-absent failure is **honest**
* exports script / shot list / subtitles / full project bundle

**Result on this build: 116 passed, 0 failed.**

### What `e2e_platform.py` covers
* registration grants free credits, default role is `creator`, creator cannot reach Admin
* daily credit bonus, once per day; no wallet withdrawal function exists anywhere
* addon lifecycle: disable → enable → uninstall → reinstall (verified against the DB)
* feature-flag toggle round-trip
* provider status model: MOCK providers report `MOCK`, keyed adapters report `NOT CONFIGURED`
* Paystack refuses to initialise a payment when it is not configured
* API rejects requests **without** a CSRF token (419)
* signed file delivery rejects a forged signature
* SQL injection in a route parameter is tolerated (parameterised queries)

**Result on this build: 27 passed, 0 failed.**

### Added in the rebuild pass

| Suite | Command | What it proves |
|---|---|---|
| Integrity audit | `php tests/audit.php` | No missing includes/templates/assets, no duplicate files, functions or classes, no two addons claiming one route/stage, no unknown SQL tables, every addon installed by the installer |
| Fresh install | `python3 tests/fresh_install.py` | Drops the database and drives `install.php` over HTTP: 39 tables, super_admin account, all addons installed, installer self-locks |
| Pages (multi-page) | `python3 tests/e2e_pages.py` | Every page renders 200 with real content and no PHP errors, including one page per addon; unknown/disabled addon pages are 404; users cannot open another user's project |
| Feature matrix | `python3 tests/feature_matrix.py` | All **83** spec features confirmed with live evidence (page, table, API action, code symbol) |
| New features | `python3 tests/e2e_new_features.py` | Runtime tests for theme, character bible, viral optimizer, approval workflow and the file cache |
| Admin | `python3 tests/e2e_admin.py` | Every admin tab renders; providers can be keyed (MOCK → REAL → NOT CONFIGURED), feature toggles flip, coupons/announcements/referrals/marketplace/storage/PWA/social settings save, wallet grants land in the ledger, repair buttons work, non-admins are locked out |

Run everything: `bash tests/run_all.sh` (starts a scratch server, reinstalls from scratch, runs all ten suites, then checks the PHP error log is empty).

## 3. Known, intentional failures

* **Master video render fails** when FFmpeg is absent: `FFmpeg is NOT CONFIGURED on this server …`. This is correct behaviour — the job fails, the reservation is released and the user is told. The suite asserts that this specific error appears rather than a fake success.

## 4. Manual checklist before going live

1. `verify.php` — PHP version, extensions, DB, writable dirs, FFmpeg, queue depth.
2. **Admin → AI Providers** — every provider you intend to use shows `REAL`.
3. **Admin → Feature governance** — costs, plan gates, daily limits set.
4. Buy a small credit pack with Paystack/Flutterwave test keys and confirm the transaction row says `verified`.
5. Run one full project end-to-end and confirm **Jobs** shows every step completed.

## 5. Security suites

```bash
php tests/security_scan.php          # 57 static + unit checks
python3 tests/security_runtime.py    # 31 live probes against a running instance
```

`security_scan.php` greps the whole codebase for dangerous constructs (`eval`, `system`,
`unserialize`, raw superglobal `echo`, interpolated SQL, hard-coded live keys, debug dumps),
checks that the security primitives exist and are wired in, and unit-tests `sf_safe_path()`
containment, upload validation and the password policy.

`security_runtime.py` attacks a live install and asserts it survives: header checks,
cookie flags, session rotation, CSRF on API + forms, traversal payloads in structural
parameters, forged/expired/out-of-scope signed links, a stored XSS payload, SQL injection
in a route, brute-force lockout, installer self-lock and cron token enforcement.

## 6. Last full run (fresh install, `debug => false`)

Reinstalled from an empty database on PHP 8.4.24 + MariaDB 11.8.6, then every suite run in order:

| Suite | Result |
|---|---|
| fresh install | 13 / 0 |
| static | 360 / 0 |
| security scan | 64 / 0 |
| integrity audit | 20 / 0 |
| e2e pipeline | 116 / 0 |
| e2e platform | 27 / 0 |
| e2e actions | 99 / 0 |
| security runtime | 31 / 0 |
| e2e pages (multi-page) | 305 / 0 |
| feature matrix (83/83 features) | 272 / 0 |
| e2e new features | 30 / 0 |
| **e2e admin** (19 tabs render, every control exercised) | 97 / 0 |
| **total** | **1434 passed / 0 failed** |

PHP error log after the run: **empty** (0 bytes).

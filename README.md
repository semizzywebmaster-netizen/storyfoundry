# STORYFOUNDRY — AI Content Production Studio

> From Idea to Finished Story.
> Idea → Story → Characters → Scenes → Shots → Images → Voice → Music → SFX → Video → Timeline → Subtitles → Thumbnail → Social Pack → Auto Clips → Export → Publish

A complete, **cPanel-deployable** AI production studio: **PHP 7.4+ and MySQL only** — no Node, no Redis, no Composer, no build step, no guaranteed FFmpeg.

**Every platform feature is an addon.** Want to add a capability next year? Drop a folder in `plugins/<key>/` and enable it in Admin → Addons. You never edit core files.

---

## Security

Hardened for shared hosting: mandatory CSRF, bcrypt + lockout, server-side sessions with
`HttpOnly`/`SameSite` cookies, signed + path-contained file links, 100% parameterised SQL,
validated non-executable uploads, security headers/CSP, denied internals, and a 6-suite
automated test battery. Full detail: **[SECURITY.md](SECURITY.md)**.

---

## 1. Quick start (cPanel)

1. Upload the folder contents to `public_html` (or a subfolder/subdomain).
2. Create a MySQL database + user in cPanel → *MySQL Databases*, and add the user to the database with **ALL PRIVILEGES**.
3. Visit `https://yourdomain.com/install.php` and follow the 4 steps. It writes `config.php` for you.
4. Add the cron job the installer prints (drains the job queue):
   ```
   */5 * * * * /usr/local/bin/php -q /home/USER/public_html/cron.php token=YOUR_TOKEN
   ```
5. Sign in as the admin you created, then **Admin → AI Providers** to add API keys (optional — see §4).

Detailed walkthrough, permissions, and troubleshooting: **[INSTALL.md](INSTALL.md)**.
Self-check page after install: `verify.php`.

---

## 2. What is in the box

| Layer | Path | What it does |
|---|---|---|
| Front controller | `index.php` | Every request → `sf_boot()` → `sf_route()` → response |
| Core | `core/` | 17 files: bootstrap, db, util, settings, audit, auth, credits, storage, media, culture, mockai, providers, jobs, orchestrator, plugins, router, view, schema |
| Templates | `templates/` | 20 files: home, login, register, reset, pricing, page (about/faq/terms/privacy/contact/cookies), dashboard, projects, project, studio, jobs, assets, notifications, share, billing, account, admin, 404, plugin shell, layout |
| Addons | `plugins/` | **38 addons** — 32 platform features (incl. Brand Kit, spec #70) + 6 AI provider adapters |
| Queue worker | `cron.php` | Token-guarded worker + housekeeping (orphan release, expiries, log pruning) |
| Installer | `install.php` | 4-step web installer; self-locks once `config.php` exists |
| Tests | `tests/` | 12 suites + `run_all.sh` one-command battery (see [TESTING.md](TESTING.md)) |

**Schema:** 39 InnoDB/utf8mb4 tables, defined in `core/schema.php` (`sf_schema()`), seeded with 45 feature flags and 4 plans.

### Multi-page by design

STORYFOUNDRY is a **multi-page application**, not a single page. Every screen is a real URL rendered
server-side by PHP, so links, bookmarks, back/forward and the browser's 404 behaviour all work normally:

| Group | Pages |
|---|---|
| Public | `?r=home`, `?r=pricing`, `?r=login`, `?r=register`, `?r=reset`, `?r=page/about`, `?r=page/faq`, `?r=page/terms`, `?r=page/privacy`, `?r=page/contact`, `?r=page/cookies` |
| Account | `?r=dashboard`, `?r=projects`, `?r=account`, `?r=billing`, `?r=notifications`, `?r=jobs`, `?r=assets` |
| Production | `?r=project/<id>` plus one tab per pipeline stage (`/idea`, `/story`, `/characters`, `/scenes`, `/shots`, `/images`, `/voice`, `/audio`, `/video`, `/timeline`, `/subtitles`, `/thumbnail`, `/social`, `/library`, `/export`, `/pipeline`, `/factory`, `/review`) |
| **Studios** | `?r=studio` directory and **`?r=studio/<addon>`** — one dedicated page per addon (37 pages) |
| Addon pages | `?r=marketplace`, `?r=brandkit`, `?r=agency`, `?r=clients`, `?r=analytics`, `?r=publishing` |
| Admin | `?r=admin` — 19 core tabs plus one tab per addon that ships an admin page (see below) |
| Public endpoints | `?r=share/<token>`, `?r=manifest.json`, `?r=sw.js`, `?r=frame`, `?r=file` |

Addons that declare a pipeline stage automatically get their own studio page — `PluginBase::page()`
renders a header, a project picker and the addon's stage panel. Addons may override `page($ctx)`
to render something bespoke.


---

## 3. The pipeline is a chain of addons

Each addon declares the stage it owns in `plugin.json`:

```json
"stage": { "key": "shots", "label": "Shots", "icon": "🎞️", "order": 5 }
```

`plugins_stages()` collects stages from **enabled** addons and `templates/project.php` renders the rail. Turn off the *AI Director* addon and the Shots stage disappears. Turn on an addon you wrote tomorrow and its stage appears — core code untouched.

Shipped stage addons, in pipeline order:

`idea-engine → story-studio → character-studio → scene-engine → ai-director → image-studio → voice-studio → audio-studio → video-engine → timeline-editor → subtitle-studio → thumbnail-studio → social-suite → export-suite → asset-library → media-pipeline → content-factory`

Each addon persists its own JSON payload per project in `project_data` via `stage_data()` / `stage_save()`.


### Admin covers every feature, not just the pipeline

`?r=admin` is a full control room. Core tabs:

| Tab | What you control |
|---|---|
| Overview | Users, projects, generations, revenue KPIs |
| Users | Roles, plans, credit grants, lockouts |
| Addons | Install / enable / disable / configure all 38 addons |
| **AI Providers** | Every provider adapter: API keys, model, priority, enable/disable, live status (REAL / MOCK / NOT CONFIGURED / DISABLED) |
| **Feature governance** | 45 feature flags: on/off switch, maintenance switch, min plan, credit cost, daily limit |
| Wallet & Payments | Wallet balances, credit ledger, payment transactions, credit grants (no withdrawal exists) |
| Coupons · Referrals | Create coupons; set referral reward and anti-abuse rules |
| Announcements | Publish platform / maintenance / promo announcements per audience |
| Marketplace | Moderate listings (approve/hide/remove), orders, commission |
| Agencies | Agencies, seats, clients |
| Social | Platforms enabled, connected accounts, publishing queue |
| Storage | Upload limits, quotas, signed-URL lifetime, S3/R2 credentials |
| PWA | App name, colours, offline shell, install prompt |
| Analytics · Audit log · Logs | Usage analytics, audit trail, application logs |
| **System check** | Provider/flag/addon counts, table count, PHP/MySQL/FFmpeg status, writable dirs, one-click repair |
| Settings | Credits, signup, maintenance, Paystack/Flutterwave keys (never echoed back) |

**Bring your own AI provider.** Besides OpenAI, Stability, ElevenLabs, Replicate and Runway, the
`openai-compatible` addon registers three providers (text / images / voice) that talk to **any**
OpenAI-compatible endpoint — OpenRouter, Groq, Together, Fireworks, DeepSeek, Mistral, Ollama,
LM Studio or your own proxy. Enter base URL + key + model in Admin → AI Providers and the provider
turns from NOT CONFIGURED to REAL.

If a host interrupts the installer's seed step, the platform self-heals on the next request
(feature flags and providers are re-seeded), and Admin → System check has explicit repair buttons.

---

## 4. REAL / MOCK / NOT CONFIGURED — never faked

This is a hard rule, not a nicety. Every AI call goes through `core/providers.php` and **every result carries a status** that `ui_status()` badges in the UI.

| Status | Meaning |
|---|---|
| **REAL** | Adapter present, credentials set, enabled |
| **MOCK** | Local deterministic engine that runs on your server (no key, no cost). Badged MOCK everywhere. |
| **NOT CONFIGURED** | Adapter present, keys missing |
| **DISABLED** | Toggled off in Admin → AI Providers |

* With **no keys**, the platform is fully usable: story text, procedural SVG frames, synthesised voice/music/SFX WAVs — all generated locally and **labelled MOCK**.
* Production **never silently falls back to MOCK**: with keys configured, a provider failure fails the job (and refunds the credits) rather than quietly swapping in a substitute.
* **FFmpeg** is probed at runtime. If it is missing, video renders fail with `FFmpeg is NOT CONFIGURED …` instead of producing a fake file.

Add a key in **Admin → AI Providers** (OpenAI, Stability, ElevenLabs, Replicate, Runway ship as addons) and that capability flips from MOCK/NOT CONFIGURED to REAL.

---

## 5. Credits: check → reserve → execute → consume-or-release

```
credits_check()   → credits_reserve()  (users.reserved += n, row in reservations)
                  → orchestrator_execute()
                       success → credits_consume() (reserved → spent, credit_tx written)
                       failure → credits_release()  (refund; user notified)
```

* Cancelling a job, cron housekeeping, and any unhandled failure all release the reservation.
* **There is no withdrawal path.** `core/credits.php` exposes `wallet_deposit()` and `wallet_spend()` only — wallet funds are spendable inside STORYFOUNDRY and nowhere else.
* Payments (Paystack / Flutterwave) grant value **only after a server-side verify call** confirms amount and currency. Without keys, transactions are recorded with status `simulated` and labelled as such — that money never moved.

---

## 6. Queue without Redis

`core/jobs.php` is a MySQL queue with priority, progress, attempts, retry and cancel. It is drained by:

1. `cron.php?token=…` via cPanel cron (recommended), **and**
2. opportunistically for a few seconds on web requests (`app.queue_inline_seconds`).

Long work (video clips, renders, batches) is always queued; short text work runs inline.

---

## 7. Roles

`creator · pro_creator · studio · agency_owner · agency_admin · agency_member · client · marketplace_seller · admin · super_admin` (levels 10–100, permission map in `core/auth.php`, admins inherit everything). Enforced with `require_login()`, `require_admin()` and `auth_can()`.

---

## 8. Writing your own addon

See **[ADDONS.md](ADDONS.md)**. In short:

```
plugins/my-thing/plugin.json     # key, name, stage, features, capabilities, routes, settings
plugins/my-thing/plugin.php      # class SFPlugin_my_thing extends PluginBase
```
Then **Admin → Addons → Install**.

---

## 9. Safety rules baked in

* No voice cloning of real people, no actor likeness — voice addons use licensed/default voices only.
* Culture data supplies setting, texture, names, greetings, food and flora. It never assigns personality, morality or ability to a group.
* CSRF on every state-changing request (including the JSON API), bcrypt password hashing, prepared statements everywhere, signed + expiring file links, rate limiting, audit log, no secrets rendered to the client.

---

## 10. Verification status

This build was reinstalled from an empty database and exercised end-to-end on PHP 8.4 + MariaDB 11.8
before shipping. `bash tests/run_all.sh` reproduces the whole thing (~25 s).

| Suite | Result |
|---|---|
| `tests/fresh_install.py` (wipe DB, drive `install.php` over HTTP) | **13 / 0** |
| `tests/static.php` (code contract, addon manifests, cPanel compatibility) | **360 / 0** |
| `tests/security_scan.php` (CSRF, XSS, SQLi, traversal, RCE, secrets) | **64 / 0** |
| `tests/audit.php` (missing/duplicate files & symbols, route & stage collisions) | **20 / 0** |
| `tests/e2e_pipeline.py` (17-stage pipeline, credits, queue) | **116 / 0** |
| `tests/e2e_platform.py` (roles, addon lifecycle, payments, wallet rules) | **27 / 0** |
| `tests/e2e_actions.py` (addon API actions) | **99 / 0** |
| `tests/security_runtime.py` (headers, cookies, lockout, traversal, XSS, SQLi) | **31 / 0** |
| `tests/e2e_pages.py` (multi-page: every route renders, 404s, ownership) | **305 / 0** |
| `tests/feature_matrix.py` (all **83** spec features confirmed with live evidence) | **272 / 0** |
| `tests/e2e_new_features.py` (theme, bible, optimizer, approval, cache) | **30 / 0** |
| `tests/e2e_admin.py` (every admin tab renders and every control works) | **97 / 0** |
| **Total** | **1434 passed / 0 failed**, PHP error log empty |

Details and how to re-run: [TESTING.md](TESTING.md). Feature-by-feature traceability:
[FEATURE-MAP.md](FEATURE-MAP.md). Addon SDK: [ADDONS.md](ADDONS.md). Security model: [SECURITY.md](SECURITY.md).

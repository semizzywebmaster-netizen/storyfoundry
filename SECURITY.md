# SECURITY — STORYFOUNDRY hardening

This document describes the security controls built into every layer of the platform,
how they are verified, and what you must do on the server. **It is written for a
shared-hosting (cPanel) deployment**, where you do not control the OS, the web server
config, or PHP ini globally — so every control has a PHP-level fallback.

Status: **6 automated suites, 661 assertions, 0 failures, 0 PHP warnings** (see §7).

---

## 1. Threat model

| Threat | Control | Where |
|---|---|---|
| Account takeover / credential stuffing | bcrypt (`password_hash`, cost 11), password policy, per-IP+email lockout (8 attempts / 15 min), session id rotation on login | `core/auth.php`, `core/security.php` |
| Session hijacking / fixation | server-side sessions (`sessions` table, revoke + touch), `HttpOnly`+`SameSite=Lax`+`Secure` cookies, `use_strict_mode`, `use_only_cookies`, id regenerated at login | `core/security.php`, `core/auth.php` |
| CSRF | CSRF token on **every** API call (419 on failure) and every form POST; tokens are per-session and rotated at login | `core/util.php`, `core/router.php` |
| XSS | every dynamic value passes `e()` (`htmlspecialchars`, ENT_QUOTES, UTF-8); output-only escaping, no raw superglobal echo anywhere | all 18 templates + 36 addons |
| SQL injection | 100% PDO prepared statements with bound parameters — no SQL string is ever concatenated with input | `core/db.php` |
| Path traversal / arbitrary file read | signed links (HMAC-SHA256 + expiry) **and** `sf_safe_path()` realpath containment restricted to `uploads/`; structural params (`r,p,a,file,path`) reject `..`, null bytes and remote schemes with HTTP 400 | `core/security.php`, `core/router.php` |
| Malicious upload / RCE | extension allowlist, size cap, `is_uploaded_file()`, image validation, random filenames, executable extensions hard-blocked, `uploads/` cannot execute PHP (`.htaccess` + `php_flag engine off`) | `core/util.php`, `core/security.php`, `uploads/.htaccess` |
| Command injection | every argument passed to FFmpeg goes through `escapeshellarg()` inside `media_run()` | `core/media.php` |
| Brute force / abuse | 900 requests/hour/IP rate limit, per-feature daily limits, credit reservation before any paid work | `core/util.php`, `core/credits.php` |
| Info disclosure | `debug => false` hides errors; `core/`, `storage/`, `tests/`, `plugins/`, `config.php`, `cron.php`, `*.sql|log|ini|env|md|json` are denied by `.htaccess`; server/version headers stripped | `.htaccess`, `core/security.php` |
| Interrupted install → error loops | installer self-locks; if `config.php` exists but the schema is missing the app redirects to the installer instead of failing per-query | `install.php`, `core/bootstrap.php` |
| Recursive failure / DoS | `sf_log()` re-entrancy guard (a failing log write can no longer recurse into itself and exhaust memory) | `core/util.php` |
| Secret leakage | provider API keys are stored in the DB, never rendered, and masked in the UI (`ui_status()`); no key material is ever sent to the browser or committed | `core/providers.php`, `core/view.php` |

---

## 2. Security layer (`core/security.php`)

Loaded by `core/bootstrap.php` before `session_start()`, so hardening is applied first:

* `sf_session_harden()` — cookie flags, strict mode, `SFSESSION` name, idempotent (no-op if a session is already active).
* `sf_security_headers()` — CSP, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`,
  `Referrer-Policy`, `Permissions-Policy`, COOP/CORP, HSTS (HTTPS only), `X-Powered-By`/`Server` removed.
* `sf_login_allowed()` / `sf_login_fail()` / `sf_login_clear()` — brute-force lockout (configurable:
  `security.login_max_attempts`, `security.login_window`).
* `sf_password_policy()` — minimum length + common-password blacklist.
* `sf_safe_path()` — realpath containment; returns `null` (and logs `path.traversal_blocked`) for anything
  that escapes the allowed roots.
* `sf_upload_validate()` — `is_uploaded_file`, size, extension allowlist, executable-extension block,
  real image validation.
* `sf_security_key()` — dedicated 64-char signing key generated at install (`app.key`); falls back to the
  cron token only for installs created before this release.
* `sf_guard_request()` — rejects traversal/remote-scheme payloads in structural GET parameters (HTTP 400).
* `sf_security_event()` — writes security events to both the file log and the audit trail.

**Framing:** the app refuses to be framed by default (`X-Frame-Options: DENY` +
`frame-ancestors 'none'`). If you must embed it in a partner portal or LMS, opt in explicitly with
`security.allow_framing => true` in `config.php` (or `SF_ALLOW_FRAMING=1` in the environment). Enabling it
removes `X-Frame-Options` and `frame-ancestors` entirely, so only do it for a trusted embedding host —
otherwise you re-open clickjacking on the admin console.

**CSP note:** `script-src`/`style-src` include `'unsafe-inline'` because the UI uses inline style attributes
and inline event handlers. Everything else is locked down (`object-src 'none'`, `base-uri 'self'`,
`form-action 'self'`, `frame-ancestors 'none'`). Removing the remaining inline handlers is the only step
needed to drop `'unsafe-inline'`.

---

## 3. File layout protections

| Path | Protection |
|---|---|
| `core/*.php` | `defined('SF_ROOT') || exit` guard — direct web access returns 403 |
| `storage/` | `.htaccess`: `Require all denied` |
| `uploads/` | `.htaccess`: no PHP execution (`php_flag engine off`, `RemoveHandler`), no indexes |
| `tests/` | denied by `.htaccess` |
| `config.php`, `cron.php` | denied by `.htaccess` |
| `install.php` | self-locks once `config.php` exists (reinstall requires `?force=1`) |
| `.git`, `.env`, `*.sql`, `*.log`, `*.md`, `*.json` | denied by `.htaccess` |

---

## 4. Server checklist (cPanel)

1. **HTTPS everywhere** — install a free AutoSSL/Let's Encrypt certificate and force HTTPS.
   The app only sends `Secure` cookies and HSTS when the request is HTTPS.
2. **Cron token** — keep `app.cron_token` secret; it gates `cron.php`.
3. **Delete `verify.php`** after the first health check (it prints environment details).
4. **Permissions** — `chmod 644` files, `755` directories; `config.php` should not be world-writable.
5. **Backups** — exclude `config.php` from any public/exported backup.
6. **Database user** — use a dedicated MySQL user with privileges on the STORYFOUNDRY database only.
7. **Mail/PHP version** — run PHP 8.x if your host offers it (7.4 is the floor).
8. **Provider keys** — add them in Admin → Settings → AI Providers; they are encrypted at rest only if
   your host encrypts MySQL data, so treat DB backups as secret material.

---

## 5. Operational guarantees

* **No silent mock fallback in production.** Every stage result is labelled
  `REAL`, `MOCK`, `NOT CONFIGURED` or `DISABLED` from `providers_effective()`, and the UI badges it.
* **Credit safety:** `check → reserve → execute → consume on success / release on failure`.
  Reservations are linked to their job id so a crash releases the hold.
* **No wallet withdrawal.** The wallet can only be spent inside the platform
  (`wallet_deposit` / `wallet_spend`); there is no payout path in the codebase.
* **Audit trail:** logins, failures, lockouts, blocked requests, admin actions and credit moves are
  recorded in `audit` + `logs` (Admin → Audit & Security).

---

## 6. Reporting a vulnerability

Email the operator of your deployment. Include the URL, steps to reproduce, and the
`storage/logs/app-YYYY-MM-DD.log` excerpt. Security events are logged under `security.*` and
`auth.*` actions for triage.

---

## 7. Verification

```bash
php tests/security_scan.php          # 57 static + unit checks (code patterns, containment, policy)
python3 tests/security_runtime.py    # 31 live probes (headers, cookies, CSRF, traversal, XSS, SQLi, lockout)
php tests/static.php                 # 344 contract checks
python3 tests/e2e_pipeline.py        # 116 end-to-end pipeline checks
python3 tests/e2e_platform.py        # 27 platform checks (roles, addons, payments, security)
python3 tests/e2e_actions.py         # 86 action/form/api checks
```

`security_runtime.py` proves, against a running instance, that:
security headers are present, the session cookie is `HttpOnly`/`SameSite` and rotates at login,
API and form writes fail without a CSRF token, traversal in `p`/`r`/`a` returns 400, signed links
cannot escape `uploads/` (even with a valid HMAC), a stored `<script>` payload renders escaped,
SQL injection in a route is harmless, and 10 wrong passwords lock the account out.

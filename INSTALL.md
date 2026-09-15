# Installing STORYFOUNDRY on cPanel

Works on any cPanel/WHM shared host with **PHP 7.4+**, **MySQL 5.7+/MariaDB 10+** and these PHP extensions: `pdo_mysql`, `mbstring`, `json`, `curl`, `gd` (optional but recommended), `zip` (optional), `openssl`.

---

## A. Upload

1. In cPanel → **File Manager**, go to `public_html` (or the folder for your subdomain/addon domain: `public_html/storyfoundry`).
2. Upload the **contents** of this folder (not the folder itself) — you should see `index.php`, `install.php`, `core/`, `plugins/`, `templates/`, `assets/`.
3. Set permissions:
   * Folders → `0755`
   * Files → `0644`
   * `uploads/`, `storage/`, `storage/cache/`, `storage/logs/` → **writable by PHP** (`0755` is normally enough; use `0775` if the installer complains).
4. The installer creates `config.php` in the root, so the **root folder must be writable** during installation.

## B. Database

1. cPanel → **MySQL Databases** → create database (e.g. `myuser_storyfoundry`) and a user with a strong password.
2. Add the user to the database with **ALL PRIVILEGES**.
3. You do **not** need to import any SQL — the installer runs the schema.

## C. Run the installer

Visit `https://yourdomain.com/install.php`:

* **Step 1** — requirements check (PHP version, extensions, writable folders).
* **Step 2** — database host/name/user/password + site URL. The installer creates the database if it can, writes `config.php`, and generates a random cron token.
* **Step 3** — creates 38 tables, seeds plans, 45 feature flags and default settings, creates your admin account, and installs + enables all 36 bundled addons.
* **Step 4** — prints your cron command.

`install.php` self-locks once `config.php` exists. To reinstall, delete `config.php` (or open `install.php?force=1` — this wipes data).

## D. Cron (queue worker)

cPanel → **Cron Jobs** → add (Every 5 minutes):

```
*/5 * * * * /usr/local/bin/php -q /home/USER/public_html/cron.php token=YOUR_TOKEN
```

`YOUR_TOKEN` is in `config.php` under `app.cron_token` and is printed on installer step 4.
Short text jobs also run inline; cron handles video renders, batches and anything slow.

## E. After install

1. Sign in with the admin account you created.
2. **Admin → Addons** — everything is already installed and enabled. Install/enable/disable/uninstall anything here.
3. **Admin → AI Providers** — add API keys (OpenAI, Stability, ElevenLabs, Replicate, Runway). Until you do, those capabilities report `NOT CONFIGURED` and the platform uses its local MOCK engines, clearly badged.
4. **Admin → Feature governance** — switch individual features off or put them into maintenance, and set plan gating, credit cost and daily limits.
5. **Admin → Settings** — payment keys, credit rewards, culture defaults, maintenance mode.
6. Visit `verify.php` for a read-only health check.

## F. Email

Password resets use PHP `mail()`. If your host restricts it, configure SMTP in cPanel → **Email Deliverability**, or set an SMTP relay and send through it (edit the `@mail()` call in `core/auth.php`).

## G. HTTPS / hardening

* Enable **Force HTTPS** in cPanel (or keep the shipped `.htaccess` rules).
* `config.php` contains DB credentials — the shipped `.htaccess` denies direct access to it, and `.sql`/`.log`/`.env` files are blocked too.
* Consider deleting `install.php` and `verify.php` once you are live.

## H. FFmpeg (video rendering)

Rendering a real MP4 needs FFmpeg. Probe it at **Admin → Addons → Media Pipeline**.

* If it is present: set the path in **Admin → Addons → Media Pipeline** and renders become REAL.
* If it is absent (common on shared hosting): ask the host to enable it, upload a static `ffmpeg` binary and set the path, or render off-server and upload the result.
* **The platform will not fake a finished video.** Without FFmpeg the render job fails with an explicit `NOT CONFIGURED` error and the reserved credits are released.

## I. Troubleshooting

| Symptom | Fix |
|---|---|
| `Database connection failed: could not find driver` | Enable `pdo_mysql` in cPanel → Select PHP Version → Extensions |
| Blank page after upload | Check `storage/logs/app-YYYY-MM-DD.log`; confirm `config.php` exists |
| `Security token expired` | Page was open too long — reload and resubmit |
| Images/audio don't load | `uploads/` must be writable; check the file path in Asset Library |
| Jobs stuck in `queued` | Cron not running or wrong token — run the cron command manually in Terminal to see the error |
| 404 on every page except home | Apache `mod_rewrite` / `.htaccess` not honoured — confirm `AllowOverride All`, or use cPanel → Domains → force HTTPS + rewrite |
| Rate limited (429) | 900 requests/hour/IP by default; clear `storage/cache/rl_*.txt` |

## J. Updating

Core and addons are separate. To update, overwrite `core/` + `templates/` (no schema changes are destructive — `CREATE TABLE IF NOT EXISTS`), then re-run nothing: addons stay configured. Back up the database and `uploads/` first.

## J. After install: security checklist

1. Force HTTPS (cPanel → AutoSSL / Let's Encrypt). Session cookies turn `Secure` and HSTS is sent automatically.
2. Confirm `config.php` is not readable from the web — `.htaccess` denies it; test `https://site/config.php` (must be 403).
3. Keep `app.cron_token` and `app.key` secret; never commit `config.php`.
4. Delete `verify.php` after the health check.
5. Re-run the security suites after any change: `php tests/security_scan.php` and `python3 tests/security_runtime.py`.
6. Read **[SECURITY.md](SECURITY.md)** for the complete control list and server checklist.

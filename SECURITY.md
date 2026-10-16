# Security policy

**English** | [Türkçe](SECURITY.tr.md)

## Reporting a vulnerability
Please do **not** open a public issue for security problems. Use GitHub's
[private vulnerability reporting](../../security/advisories/new) on this repository. Include the affected
version/commit, steps to reproduce and the impact. You can expect an answer within a few days; this is a
small personal project, so there is no formal SLA.

Only the latest commit on the default branch is supported.

## What Pika does for you
- **Sessions:** `HttpOnly`, `SameSite=Lax`, `Secure` over HTTPS, strict mode, id regenerated on login/logout, idle and absolute
  timeouts, non-cacheable responses, "log out everywhere", sessions end when the password changes.
- **CSRF:** every POST/PUT/PATCH/DELETE needs the session token (form field or `X-CSRF-Token`), compared in constant time.
- **Passwords:** `password_hash`, rehash on login, 10+ character policy, per-IP and per-username throttling, password reset with
  single-use hashed tokens, no account enumeration on the reset form.
- **Headers:** strict CSP with per-request nonces, `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS on HTTPS.
- **Database:** PDO with real prepared statements and exceptions; the query builder binds every value and validates identifiers.
- **Uploads:** extension allow-list, real MIME check, image decoding check, random names, script execution disabled in `public/uploads`.
- **Fail closed:** `APP_ENV=production` refuses to start with debug on, a weak `PIKA_LICENSE` or a non-HTTPS `SITE_URL`.
- **Cron:** the HTTP cron endpoint answers 404 unless `CRON_TOKEN` (32+ characters) is sent in the `X-Cron-Token` header (constant-time comparison, never read from the URL); jobs are plain classes under `app/jobs/` and never overlap.
- **Tooling:** `php pika scan:secrets`, `php pika doctor`, `composer audit` in CI.

## Web installer (`public/install.php`)
Meant for hosting without a terminal; the page does not exist unless you create `content/install.token`.
- **Gate:** the token file needs file access to create, so a visitor cannot "install first". The token is compared in constant time;
  5 wrong tokens lock the door for 15 minutes (for everybody, including the right token).
- **Lock:** once `.env` or `content/install.lock` exists the page answers 404 and cannot be re-enabled by recreating the token.
- **Transport:** refuses plain HTTP except on localhost; CSRF-protected; strict CSP; `no-store`, `noindex`.
- **Safe defaults:** never overwrites `.env`, writes it with mode 0600, refuses a database that already has users, one install at a time.
- **Remove it if you do not need it:** delete `public/install.php` after installing (it is inert anyway).
- **Limits:** the token sits in a plain file (protect it like any server file, and it is removed after installing); a stranger can
  trigger the 15-minute lock by guessing wrongly, but cannot install. Whoever can already write files on the server can do anything anyway.

## What you must do
- Point the web server's **document root at `public/`**. Never expose the project root.
- Keep `.env` out of version control and out of the web root; run `php pika key:generate` once.
- Set `SITE_URL` to your HTTPS address, serve only over HTTPS, and list reverse proxies in `TRUSTED_PROXIES`.
- Keep dependencies updated (`composer update`, `composer audit`).
- Review `includes/components/post_security.php` before adding CSRF-exempt paths (webhooks, token-authenticated APIs).

## Known limits
- Rate limiting is per IP (`RateLimit` middleware) and on login/reset; put a WAF or reverse-proxy limit in front for real DoS protection.
- Sessions use PHP's default file storage; on shared hosting make sure `session.save_path` is private to your account.
- Password reset needs working SMTP (`MAIL_*`); the `log` mail driver is refused outside `APP_ENV=local`.
- The password reset request has a small timing difference between known and unknown addresses (mail sending).

# Changelog

All notable changes to Pika. Format: [Keep a Changelog](https://keepachangelog.com/), versions follow [SemVer](https://semver.org/).

## [Unreleased]

### Security
Findings of an AI-assisted code review, all fixed (run `php pika migrate`: migration 007 adds `users.remember_issued`):
- A successful login no longer erases the failed attempts recorded against other usernames from the same IP.
- Client IP behind a proxy is read from the right of `X-Forwarded-For` (the left side is client-controlled).
- Login attempts are recorded before they are counted, so parallel requests cannot exceed the limit.
- The username lockout no longer applies to the account's last login IP, so strangers cannot lock the owner out.
- The dummy hash used for unknown usernames is created with the current `PASSWORD_DEFAULT` cost (timing).
- Password reset: mail is sent after the response (PHP-FPM / LiteSpeed) and at most once per account every 2 minutes.
- Remember-me tokens expire on the server after 30 days and rotate on every automatic login.
- Root `.htaccess` rewrite is anchored, so `.php` files outside `public/` cannot be reached with a trailing `/public/`.
- `safe_redirect_url` rejects backslashes and user-info URLs.
- `Query::where()` refuses `null` values (use `whereNull()` / `whereNotNull()`); `key:generate` writes `.env` with mode 0600.

## [0.1.0]
First public version.

### Added
- Router, Blade views (site, admin and shared themes), MySQL query builder and models, validator, pagination, uploads, mail.
- Security defaults: sessions, CSRF, CSP with nonces, rate limiting, login throttling, password reset, remember-me,
  `APP_ENV=production` start-up checks, secret scanner.
- Web installer (`public/install.php`, token file + self-locking) and `php pika install`.
- `php pika` command line: migrations, generators, `doctor`, `scan:secrets`, `log:clear`.
- Cron jobs: classes in `app/jobs/`, `php pika make:job` / `job:run`, HTTP endpoint with `X-Cron-Token`, per-job lock,
  admin **Jobs** page, one e-mail alert per failure streak (`CRON_ALERT_EMAIL`).
- Named routes: `route()` helper and `@route` directive; the built-in views use them.
- English and Turkish README and SECURITY documents, PHPUnit unit and HTTP integration tests, CI and release workflows.

# Changelog

All notable changes to Pika. Format: [Keep a Changelog](https://keepachangelog.com/), versions follow [SemVer](https://semver.org/).

## [Unreleased]

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

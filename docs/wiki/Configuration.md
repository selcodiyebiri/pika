# Configuration

[Home](Home) | **English** | [Türkçe](Configuration-TR)

Settings live in `.env` (copy `.env.example`). Real environment variables override `.env`. Production (`APP_ENV=production`) **refuses to start** with an unsafe configuration; the reasons are written to the server log.

## Settings
| Key | Purpose |
|---|---|
| `APP_ENV` | `local` or `production` (default). Production enforces the safety checks. |
| `APP_DEBUG` | `true` shows errors and a debug page. Never in production. |
| `PIKA_LICENSE` | Secret behind `SecureString` (AES-256-GCM). 32+ characters in production. Changing it makes old ciphertext unreadable. |
| `SITE_URL`, `ADMIN_URL` | Canonical URL (required in production, https) and the admin URL prefix (default `admin`). |
| `MYSQL_HOST/PORT/SOCKET/DB/USER/PASS` | Database. `MYSQL_SOCKET` (for example MAMP) replaces host and port. |
| `TRUSTED_PROXIES` | Comma-separated proxy and CDN addresses whose `X-Forwarded-*` headers are trusted. List every hop. |
| `SESSION_IDLE`, `SESSION_ABSOLUTE` | Session timeouts in seconds (1800 / 28800). |
| `RATE_LIMIT`, `RATE_WINDOW` | `RateLimit` middleware: requests per window per IP (60 / 60 s). |
| `MAIL_*` | SMTP for password reset. `MAIL_DRIVER=log` writes mails to the log and is allowed only with `APP_ENV=local`. |
| `CRON_TOKEN` | Secret for the HTTP cron endpoint (32+ characters). Empty disables it. |
| `CRON_ALERT_EMAIL` | One e-mail when a job starts failing. Empty = off. |
| `CSP` | Replace the default Content-Security-Policy (`{nonce}` is replaced per request). |

## Good to know
* `php pika key:generate` writes `PIKA_LICENSE` once; it refuses to replace an existing key unless you pass `--force`.
* Behind a proxy or CDN set `TRUSTED_PROXIES`, otherwise limits and logs see the proxy's address and `https` is not detected.
* In `.env`, quote values that contain `#` or spaces.

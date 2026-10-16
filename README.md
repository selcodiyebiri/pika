# Pika

**English** | [Türkçe](README.tr.md)

A small, security-minded PHP framework base for personal projects. MySQL (PDO), Blade views, a small router.
It is a starting point to copy and bend, not a general-purpose framework: no ORM, no DI container, no queue.

* PHP 8.2+, MySQL 5.7+/MariaDB, Composer, extensions `pdo_mysql`, `openssl`, `mbstring`, `curl`, `fileinfo`
* Secure defaults out of the box, see [SECURITY.md](SECURITY.md)
* Site and admin areas with separate themes, shared login/reset flows, permissions, audit log
* A modern, responsive UI (light and dark) with [Heroicons](https://heroicons.com) icons, no external requests, CSP-friendly
* A `php pika` command line and a test suite (unit + HTTP integration)

## Install
```bash
composer install
cp .env.example .env          # APP_ENV=local for development
php pika key:generate         # writes a random PIKA_LICENSE to .env
# edit MYSQL_* in .env, create the (empty) database, then:
php pika migrate
php pika make:admin           # first admin account
php pika serve                # http://127.0.0.1:8000  (admin: /admin/login)
php pika doctor               # checks configuration and the database connection
```
Or let the wizard do all of it: `composer install && php pika install` (asks for the site address, database and admin account,
writes `.env`, runs the migrations).

## Install without a terminal
For shared hosting where you cannot run Composer or `php pika`:
1. Download `pika-vX.Y.Z.zip` from the repository's **Releases** (it already contains `vendor/`), unzip it and upload it with
   the document root set to `public/` (see *Production*).
2. Create an empty MySQL database and user in your hosting panel.
3. In the file manager create **`content/install.token`** containing a secret of at least 16 characters that you make up.
4. Open `https://your.domain/install.php`, enter that token and fill in the form (site address, database, admin account, optional SMTP).
5. The installer checks the server, runs the migrations, creates the admin, writes `.env` (mode 0600 with a fresh random
   `PIKA_LICENSE`) and **locks itself**: the token file is deleted and `install.php` answers `404` from then on. You may delete
   `public/install.php` as well.

Why this is safe to leave in the project: without the token file the page does not exist (404), so a stranger cannot "install first";
wrong tokens are throttled (5 per 15 minutes); it only runs over HTTPS (or on localhost); it refuses a database that already has
users and never overwrites an existing `.env`. If the project folder is not writable it shows the `.env` text once for you to
create by hand. nginx users must also allow PHP to run `install.php` (`location = /install.php { ... }`) for the duration of the install.

## Production
1. Point the web server's **document root at `public/`**. Do not serve the project root.
2. `composer install --no-dev --optimize-autoloader`
3. `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `SITE_URL=https://your.domain`, strong `PIKA_LICENSE`, `MYSQL_*`, `MAIL_*`.
   Production **refuses to start** (HTTP 500, details in the log) if any of that is unsafe or missing.
4. `php pika migrate`, make sure `content/cache`, `content/logs` and `public/uploads` are writable by PHP.
5. Behind a reverse proxy / CDN, list its IPs in `TRUSTED_PROXIES` so client IPs and HTTPS are detected correctly.
6. Check `https://your.domain/.env` and `/vendor/autoload.php` return **404**.

**Apache / cPanel:** use a domain or sub-domain whose *Document Root* is `.../public`. On a main domain you cannot repoint,
put the project outside `public_html` and symlink `public_html` to `public/`. The root `.htaccess` (rewrites everything into
`public/`) exists only as a fallback, for example for MAMP serving `http://localhost:8888/pika/`.

**nginx:**
```nginx
root /var/www/pika/public;
location / { try_files $uri /index.php?$query_string; }
location ~ ^/(index|install)\.php$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; fastcgi_pass unix:/run/php/php8.2-fpm.sock; }
location ^~ /uploads/ { location ~ \.php$ { return 403; } add_header Content-Security-Policy "default-src 'none'; sandbox"; }
location ~ /\. { deny all; }
```

## Configuration (`.env`)
| Key | Purpose |
|---|---|
| `APP_ENV` | `local` or `production` (default). Production enforces the checks above. |
| `APP_DEBUG` | `true` shows errors and a debug page with stack traces. Never in production. |
| `PIKA_LICENSE` | Secret behind `SecureString` (AES-256-GCM). 32+ chars in production. Changing it makes old ciphertext unreadable. |
| `SITE_URL`, `ADMIN_URL` | Canonical URL (required in production) and the admin URL prefix (default `admin`). |
| `MYSQL_HOST/PORT/SOCKET/DB/USER/PASS` | Database. `MYSQL_SOCKET` (e.g. MAMP) overrides host/port. |
| `TRUSTED_PROXIES` | Comma-separated proxy IPs whose `X-Forwarded-*` headers are trusted. |
| `SESSION_IDLE`, `SESSION_ABSOLUTE` | Session timeouts in seconds (1800 / 28800). |
| `RATE_LIMIT`, `RATE_WINDOW` | `RateLimit` middleware: requests per window per IP (60 / 60 s). |
| `CRON_ALERT_EMAIL` | Address that gets one e-mail when a job starts failing (needs working `MAIL_*`). Empty = off. |
| `CRON_TOKEN` | Secret for the HTTP cron endpoint `/api/cron/<job>` (32+ characters, sent as `X-Cron-Token`). Empty disables it. |
| `MAIL_*` | SMTP for password reset (`MAIL_DRIVER=log` only with `APP_ENV=local`). |
| `CSP` | Replace the default Content-Security-Policy (`{nonce}` placeholder). |

Real environment variables override `.env`.

## Layout
```
public/        web root: index.php, install.php (web installer), assets/ (css, js), uploads/ (script execution disabled)
src/           Pika\ (PSR-4): DB, Query, Model, Controller, AdminController, Auth\BaseLogin, Validator, Pagination,
               SecureString, Upload, Mail, RateLimiter, Icons, Installer, Migrator, Console\Commands
app/           controllers/ (+ Admin/), middlewares/ (Admin, User, RateLimit), models/ (App\Models), jobs/ (App\Jobs)
routes/        *.php, all loaded automatically (web, admin, api)
includes/      loader, config (.env + boot checks), helpers.php, components/, error_pages/
content/       themes/<theme>/ + themes/shared/, cache/, logs/
database/      migrations/NNN_name.sql (+ NNN_name.down.sql)
tests/         Unit/ and Integration/ (PHPUnit)
```

## Everyday use
```php
// routes/web.php            GET routes should also list HEAD
$router->add('GET|HEAD', 'posts', 'Posts@index', ['name' => 'POSTS']);

// app/controllers/Posts.php  (php pika make:controller Posts)
class Posts extends Controller {
    public function index(Request $request, Response $response) {
        $posts = DB::table('posts')->where('status', 1)->orderBy('id', 'desc')->limit(20)->get();
        return $response->setContent($this->view('posts', ['posts' => $posts]));   // content/themes/default/posts.blade.php
    }
}
```
* **Themes:** a controller's `$theme` picks `content/themes/<theme>`; views fall back to `content/themes/shared`. `Controller` uses
  `default`, `AdminController` uses `admin`. Admin controllers live in `app/controllers/Admin/` (`'Admin.Name@method'`).
* **Auth:** `is_logged_in()`, `currentUser()`, `is_admin_logged_in()`, middleware `User` / `Admin`, `can('permission')` and
  `$this->authorize('permission')` (admins may do everything; others need a row in `user_permissions`),
  `audit_log('action', 'details')`, `logout_everywhere($userId)`, `set_user_password($userId, $plain)`.
* **Forms:** `{!! csrf_field() !!}`, `Validator::make($_POST, ['email' => 'required|email|unique:users,email'])`,
  `flash('status', '...')` / `flash_now()`, `old('field')`.
* **Views:** `{{ }}` escapes. Inline `<style>`/`<script>` need `nonce="{{ csp_nonce() }}"` (the CSP blocks inline code otherwise); put styles in
  `public/assets/css/pika.css` instead. Layouts: `shared/layouts/base` and `auth` (login, forgot, reset), `default/layouts/site`, `admin/layouts/admin`.
  Icons: `{!! icon('shield') !!}` (Heroicons, see `src/Icons.php` for the names).
* **Named routes:** give a route a name (`['name' => 'LOGIN']`), then use `{{ route('LOGIN') }}`, `{{ route('RESET', ['any' => $token]) }}` or
  `@route('LOGIN')` in views and `route('LOGIN')` in PHP. Values are URL-encoded and the result is HTML-escaped; an unknown name or a missing
  parameter gives `null` (empty in a view) and a warning in the log. `route()` is in `includes/helpers.php` and needs the router, so call it
  while handling a request (not from code that runs before the router is created).
* **Models/queries:** `DB::table()` builder (bound values only), `Pika\Model` subclasses (`$fillable` limits mass assignment),
  raw `DB::get/getRow/getVar/exec/insert`, `DB::transaction(fn)`.
* **Uploads:** `Upload::save($_FILES['f'], ['dir' => 'avatars', 'max_size' => 1_000_000])`.
* **API:** group routes with `['before' => RateLimit::class]`; token-authenticated endpoints must be listed in
  `includes/components/post_security.php` to be exempt from CSRF.

## Cron jobs
A job is a class in `app/jobs/`: `php pika make:job reports/daily` creates `app/jobs/Reports/Daily.php` (`mail-send` becomes `MailSend`).
Put the work in `handle()`; throw to report a failure. Do a bounded batch per run (for example 500 records) instead of one endless run.

**From cron (preferred).** Add one cron line per job. Cron starts each line as its own process, so jobs run in parallel and a slow
job never blocks the others. The CLI has no time limit, so this is the way for long jobs. In cPanel (Cron Jobs):
```bash
# project uploaded to public_html/pika (outside public_html it would be /home/user/pika/pika)
* * * * * /usr/local/bin/php -q /home/user/public_html/pika/pika job:run reports/daily
*/5 * * * * /usr/local/bin/php -q /home/user/public_html/pika/pika job:run mail-send
```
* `.../pika/pika` is the `pika` file in the project folder (the folder name, then the file); it needs no execute permission.
* Use a PHP 8.2+ binary. `php pika doctor` prints the PHP path and version of the command line and the project folder, and lists
  the jobs with their last run. Hosts often have several PHP versions (cPanel: `/opt/cpanel/ea-php82/root/usr/bin/php`).
* The admin area has a **Jobs** page (`/admin/jobs/`) with every job's last run, result, duration and failures in a row. The same
  information is in `php pika doctor`. With `CRON_ALERT_EMAIL` set, you get one e-mail when a job starts failing, not one per minute.
* A job never overlaps itself: if the previous run is still going, the new one is skipped (exit code 0, nothing breaks). Failures
  exit with code 1 and are written to `content/logs`.

**Over HTTP** (hosting without CLI cron). Set `CRON_TOKEN` to a random secret of 32+ characters (`openssl rand -hex 32`), then:
```bash
* * * * * wget -q -O /dev/null --header="X-Cron-Token: YOUR_TOKEN" https://your.domain/api/cron/reports/daily
```
The token must be sent in the header; a token in the URL is ignored because URLs end up in server logs. A missing or wrong token,
an unknown job or an empty `CRON_TOKEN` all answer `404`. The request is subject to PHP and web server time limits and to the
`RateLimit` budget per IP (`RATE_LIMIT`), so keep short jobs here and raise the limit if one server calls many jobs.

## Command line
`php pika` lists everything: `install`, `serve`, `migrate`, `migrate:rollback`, `make:migration`, `make:controller [--admin]`, `make:admin`,
`make:job <name>`, `job:run <name>`, `key:generate`, `log:clear [days]`, `scan:secrets`, `doctor`.

## Tests
```bash
vendor/bin/phpunit                       # unit + integration
vendor/bin/phpunit --testsuite unit      # no database needed
```
Integration tests start the real app with PHP's built-in server against the database `pika_framework_test`
(created and migrated automatically; any database whose name does not end in `_test` is never touched). Without a reachable
MySQL they are skipped. CI runs both suites on PHP 8.2-8.4 with a MySQL service.

## License
MIT, see [LICENSE](LICENSE). Icons: Heroicons (MIT, Tailwind Labs), see [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).

<?php
/* .env parser: KEY=VALUE per line, "#" comment lines, one pair of surrounding quotes is removed (no escapes) */
function pika_parse_env(string $content): array {
    $values = [];
    foreach (preg_split('/\r\n|\n|\r/', $content) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $v = trim($v);
        if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) {
            $v = substr($v, 1, -1);
        }
        $values[trim($k)] = $v;
    }
    return $values;
}

/* Real environment variables (and $_ENV) win over the file */
if (is_readable(BASEPATH . "/.env")) {
    foreach (pika_parse_env((string) file_get_contents(BASEPATH . "/.env")) as $k => $v) {
        if (!isset($_ENV[$k]) && getenv($k) === false) $_ENV[$k] = $v;
    }
}

function pika_env(string $key, $default = null) {
    $value = $_ENV[$key] ?? getenv($key);
    return ($value !== false && $value !== null && $value !== '') ? $value : $default;
}

define("APP_ENV", pika_env('APP_ENV', 'production'));            // local | production
define("APP_DEBUG", filter_var(pika_env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN));
define("PIKA_LICENSE", pika_env('PIKA_LICENSE', ''));
define("CHARSET", "utf8mb4");

define("MYSQL_HOST", pika_env('MYSQL_HOST', '127.0.0.1'));
define("MYSQL_SOCKET", pika_env('MYSQL_SOCKET', ''));
define("MYSQL_PORT", pika_env('MYSQL_PORT', 3306));
define("MYSQL_DB", pika_env('MYSQL_DB', 'pika'));
define("MYSQL_USER", pika_env('MYSQL_USER', 'root'));
define("MYSQL_PASS", pika_env('MYSQL_PASS', ''));

define("SESSION_IDLE", (int) pika_env('SESSION_IDLE', 1800));          // seconds without a request
define("SESSION_ABSOLUTE", (int) pika_env('SESSION_ABSOLUTE', 28800)); // max session age
define("RATE_LIMIT", (int) pika_env('RATE_LIMIT', 60));                // requests per window (RateLimit middleware)
define("RATE_WINDOW", (int) pika_env('RATE_WINDOW', 60));              // seconds

date_default_timezone_set(pika_env('APP_TIMEZONE', 'Europe/Istanbul'));

/* Errors: always report and log, only display in debug mode.
   E_DEPRECATED is off: the pinned vendor packages (blade 2.x) emit PHP 8.4 deprecations. */
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

/* Proxy headers (X-Forwarded-*) are trusted only from addresses listed in TRUSTED_PROXIES */
function is_trusted_proxy(): bool {
    $list = array_filter(array_map('trim', explode(',', (string) pika_env('TRUSTED_PROXIES', ''))));
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', $list, true);
}

function is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    return is_trusted_proxy() && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/**
 * Configuration problems that make the app unsafe or broken. Production fails closed on any of them.
 * Also printed by `php pika doctor`.
 */
function pika_boot_problems(): array {
    $problems = [];

    if (!in_array(APP_ENV, ['local', 'production'], true)) {
        $problems[] = 'APP_ENV must be "local" or "production".';
    }
    if (APP_ENV === 'production') {
        if (APP_DEBUG) $problems[] = 'APP_DEBUG must be false in production.';
        if (strlen(PIKA_LICENSE) < 32 || PIKA_LICENSE === 'change-me') {
            $problems[] = 'PIKA_LICENSE must be a random secret of at least 32 characters (php pika key:generate).';
        }
        if (strncmp((string) pika_env('SITE_URL', ''), 'https://', 8) !== 0) {
            $problems[] = 'SITE_URL must be set to your https:// address in production.';
        }
        if ((string) pika_env('CRON_TOKEN', '') !== '' && strlen((string) pika_env('CRON_TOKEN')) < 32) {
            $problems[] = 'CRON_TOKEN must be at least 32 characters (or empty to disable HTTP cron).';
        }
    }
    foreach (['content/cache', 'content/logs'] as $dir) {
        if (!is_dir(BASEPATH . "/$dir") || !is_writable(BASEPATH . "/$dir")) {
            $problems[] = "$dir must exist and be writable.";
        }
    }
    return $problems;
}

/* Web entry point calls this: production stops on problems, local only logs them. */
function pika_boot_enforce(): void {
    $problems = pika_boot_problems();
    if (!$problems) return;

    foreach ($problems as $p) error_log("[pika config] $p");
    if (APP_ENV === 'production') {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        exit("Server configuration error. See the server log.\n");
    }
}

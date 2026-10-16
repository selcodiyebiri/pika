<?php
namespace Pika;

use PDO;

/**
 * First-run installation, shared by `php pika install` and public/install.php.
 * Depends on nothing but PHP and the other installer classes, so it can run before vendor/ or .env exist.
 *
 * Web access is gated by a token FILE (content/install.token) that only someone with file access can create,
 * and everything locks once .env or content/install.lock exists.
 */
class Installer
{
    public const MIN_TOKEN = 16;
    private const MAX_FAILS = 5;
    private const FAIL_WINDOW = 900;   // seconds

    public function __construct(private string $base)
    {
        $this->base = rtrim($base, '/');
    }

    /* ---------- paths and lock ---------- */

    public function envFile(): string   { return $this->base . '/.env'; }
    public function lockFile(): string  { return $this->base . '/content/install.lock'; }
    public function tokenFile(): string { return $this->base . '/content/install.token'; }
    private function attemptsFile(): string { return $this->base . '/content/cache/install-attempts.json'; }
    private function runningFile(): string  { return $this->base . '/content/install.running'; }

    /** Installed already (or .env supplied by hand): the installer must behave as if it did not exist. */
    public function isLocked(): bool
    {
        return is_file($this->envFile()) || is_file($this->lockFile());
    }

    /* ---------- web access token ---------- */

    private function readToken(): ?string
    {
        return is_file($this->tokenFile()) && is_readable($this->tokenFile())
            ? trim((string) file_get_contents($this->tokenFile()))
            : null;
    }

    /** The web installer exists only while a token file of 16+ characters is present. */
    public function tokenEnabled(): bool
    {
        $t = $this->readToken();
        return $t !== null && strlen($t) >= self::MIN_TOKEN;
    }

    /** Seconds until token attempts are allowed again, or 0. */
    public function blockedFor(): int
    {
        $times = $this->recentFailures();
        if (count($times) < self::MAX_FAILS) return 0;
        return max(1, min($times) + self::FAIL_WINDOW - time());
    }

    private function recentFailures(): array
    {
        $raw = is_file($this->attemptsFile()) ? json_decode((string) file_get_contents($this->attemptsFile()), true) : [];
        return array_values(array_filter(is_array($raw) ? $raw : [], fn($t) => is_int($t) && $t > time() - self::FAIL_WINDOW));
    }

    /** Compare in constant time; failures are counted globally (5 per 15 minutes) and success clears them. */
    public function checkToken(string $given): bool
    {
        if ($this->blockedFor() > 0 || !$this->tokenEnabled()) return false;

        if (hash_equals((string) $this->readToken(), $given)) {
            @unlink($this->attemptsFile());
            return true;
        }
        $failures = $this->recentFailures();
        $failures[] = time();
        @file_put_contents($this->attemptsFile(), json_encode($failures), LOCK_EX);
        return false;
    }

    /* ---------- requirements ---------- */

    /** @return array{checks: array<int, array{label: string, ok: bool, required: bool}>, ok: bool} */
    public function requirements(): array
    {
        $checks = [['label' => 'PHP 8.2 or newer (found ' . PHP_VERSION . ')', 'ok' => PHP_VERSION_ID >= 80200, 'required' => true]];
        foreach (['pdo_mysql', 'openssl', 'mbstring', 'curl', 'fileinfo'] as $ext) {
            $checks[] = ['label' => "PHP extension $ext", 'ok' => extension_loaded($ext), 'required' => true];
        }
        $checks[] = ['label' => 'vendor/ is present (composer install, or use the release ZIP)', 'ok' => is_file($this->base . '/vendor/autoload.php'), 'required' => true];
        foreach (['content', 'content/cache', 'content/logs'] as $dir) {
            $checks[] = ['label' => "$dir/ is writable", 'ok' => is_dir("$this->base/$dir") && is_writable("$this->base/$dir"), 'required' => true];
        }
        $checks[] = ['label' => 'project folder is writable (.env can be created automatically; otherwise you copy it by hand)', 'ok' => is_writable($this->base), 'required' => false];
        $checks[] = ['label' => 'public/uploads/ is writable', 'ok' => is_dir($this->base . '/public/uploads') && is_writable($this->base . '/public/uploads'), 'required' => false];

        return ['checks' => $checks, 'ok' => !array_filter($checks, fn($c) => $c['required'] && !$c['ok'])];
    }

    /* ---------- input ---------- */

    public static function isLoopbackHost(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.localhost');
    }

    /** The web installer carries passwords: HTTPS only, except on the local machine. */
    public static function transportOk(bool $https, string $host): bool
    {
        return $https || self::isLoopbackHost(preg_replace('/:\d+$/', '', $host));
    }

    /** @return array<string,string> field => message (empty = valid) */
    public function validate(array $in): array
    {
        $e = [];
        $s = fn($k) => trim((string) ($in[$k] ?? ''));

        $url = $s('site_url');
        $parts = parse_url($url);
        if ($url === '' || !$parts || empty($parts['host']) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || preg_match('/\s/', $url) || isset($parts['query']) || isset($parts['fragment'])) {
            $e['site_url'] = 'Enter the full site address, e.g. https://example.com';
        } elseif ($parts['scheme'] !== 'https' && !self::isLoopbackHost($parts['host'])) {
            $e['site_url'] = 'The site address must use https:// (http is only allowed for localhost).';
        }

        if ($s('db_host') === '' && $s('db_socket') === '') $e['db_host'] = 'Enter the database host (or a socket path).';
        if ($s('db_port') !== '' && (!ctype_digit($s('db_port')) || (int) $s('db_port') < 1 || (int) $s('db_port') > 65535)) $e['db_port'] = 'Port must be 1-65535.';
        if (!preg_match('/^[A-Za-z0-9_$-]{1,64}$/', $s('db_name'))) $e['db_name'] = 'Database name may only contain letters, digits, _ $ -';
        if ($s('db_user') === '') $e['db_user'] = 'Enter the database user.';

        if (!preg_match('/^[A-Za-z0-9_.-]{3,60}$/', $s('admin_user'))) $e['admin_user'] = 'Username: 3-60 characters, letters, digits, _ . -';
        if ($s('admin_email') !== '' && !filter_var($s('admin_email'), FILTER_VALIDATE_EMAIL)) $e['admin_email'] = 'Invalid e-mail address.';
        $problems = PasswordPolicy::errors((string) ($in['admin_pass'] ?? ''), $s('admin_user'));
        if ($problems) $e['admin_pass'] = implode(' ', $problems);
        elseif (!hash_equals((string) ($in['admin_pass'] ?? ''), (string) ($in['admin_pass2'] ?? ''))) $e['admin_pass2'] = 'Passwords do not match.';

        if ($s('mail_host') !== '') {
            if (!filter_var($s('mail_from'), FILTER_VALIDATE_EMAIL)) $e['mail_from'] = 'Enter the sender address.';
            if ($s('mail_port') !== '' && !ctype_digit($s('mail_port'))) $e['mail_port'] = 'Port must be a number.';
        }

        foreach ($in as $k => $v) {   // values go into .env, one per line
            if (is_string($v) && preg_match('/[\r\n\0]/', $v)) $e[$k] = 'Line breaks are not allowed.';
        }
        return $e;
    }

    /* ---------- steps ---------- */

    public function connect(array $in): PDO
    {
        $socket = trim((string) ($in['db_socket'] ?? ''));
        $target = $socket !== '' ? "unix_socket=$socket" : 'host=' . trim($in['db_host']) . ';port=' . (int) (($in['db_port'] ?? '') ?: 3306);
        try {
            return new PDO("mysql:$target;dbname=" . trim($in['db_name']) . ';charset=utf8mb4', trim($in['db_user']), (string) ($in['db_pass'] ?? ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 8,
            ]);
        } catch (\PDOException $e) {
            throw new InstallException('Could not connect to the database: ' . $e->getMessage());
        }
    }

    /** The target database must not already hold users (never install over a live site). */
    private function assertNoUsers(PDO $pdo): void
    {
        $exists = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
        if ($exists && (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() > 0) {
            throw new InstallException('This database already contains users. Use an empty database.');
        }
    }

    public function generateLicense(): string
    {
        return bin2hex(random_bytes(32));
    }

    private static function quote(string $v): string
    {
        if (preg_match('/[\r\n\0]/', $v)) throw new InstallException('Line breaks are not allowed in settings.');
        return '"' . $v . '"';   // pika_parse_env() strips exactly one pair of quotes and does no escaping
    }

    public function envContent(array $in, string $license): string
    {
        $site = rtrim(trim($in['site_url']), '/');
        $local = self::isLoopbackHost((string) parse_url($site, PHP_URL_HOST));
        $s = fn($k, $d = '') => trim((string) ($in[$k] ?? '')) !== '' ? trim((string) $in[$k]) : $d;
        $q = [self::class, 'quote'];

        $lines = [
            '# Generated by the Pika installer on ' . date('Y-m-d H:i:s') . '. Keep this file private.',
            'APP_ENV=' . ($local ? 'local' : 'production'),
            'APP_DEBUG=false',
            'PIKA_LICENSE=' . $q($license),
            'SITE_URL=' . $q($site),
            'ADMIN_URL=admin',
            'APP_TIMEZONE=Europe/Istanbul',
            '',
            'MYSQL_HOST=' . $q($s('db_host', '127.0.0.1')),
            'MYSQL_PORT=' . $q($s('db_port', '3306')),
            'MYSQL_SOCKET=' . $q($s('db_socket')),
            'MYSQL_DB=' . $q($s('db_name')),
            'MYSQL_USER=' . $q($s('db_user')),
            'MYSQL_PASS=' . $q((string) ($in['db_pass'] ?? '')),
            '',
            'TRUSTED_PROXIES=',
            'SESSION_IDLE=1800',
            'SESSION_ABSOLUTE=28800',
            'RATE_LIMIT=60',
            'RATE_WINDOW=60',
            '',
            'MAIL_DRIVER=' . ($local && $s('mail_host') === '' ? 'log' : 'smtp'),
            'MAIL_HOST=' . $q($s('mail_host')),
            'MAIL_PORT=' . $q($s('mail_port', '587')),
            'MAIL_USER=' . $q($s('mail_user')),
            'MAIL_PASS=' . $q((string) ($in['mail_pass'] ?? '')),
            'MAIL_ENCRYPTION=' . $q($s('mail_encryption', 'tls')),
            'MAIL_FROM=' . $q($s('mail_from')),
            'MAIL_FROM_NAME=Pika',
            'CSP=',
            '',
        ];
        return implode("\n", $lines);
    }

    /** Create .env exclusively (never overwrites), owner-only permissions. */
    public function writeEnv(string $content): bool
    {
        $fh = @fopen($this->envFile(), 'x');
        if ($fh === false) return false;
        $ok = fwrite($fh, $content) === strlen($content);
        fclose($fh);
        @chmod($this->envFile(), 0600);
        return $ok;
    }

    /**
     * Run the whole installation. Returns ['env_written' => bool, 'env' => string] (the .env text is for
     * copying by hand when the folder is not writable).
     * @throws InstallException
     */
    public function install(array $in): array
    {
        if ($this->isLocked()) throw new InstallException('Already installed.');
        if ($errors = $this->validate($in)) throw new InstallException(reset($errors));

        // one installation at a time (a stale marker older than 5 minutes is ignored)
        if (is_file($this->runningFile()) && filemtime($this->runningFile()) > time() - 300) {
            throw new InstallException('An installation is already running.');
        }
        file_put_contents($this->runningFile(), (string) getmypid());

        try {
            $pdo = $this->connect($in);
            $this->assertNoUsers($pdo);

            (new Migrator($pdo, $this->base . '/database/migrations'))->migrate();

            $pdo->prepare("INSERT INTO users (username, email, password, admin, status) VALUES (?, ?, ?, 1, 1)")->execute([
                trim($in['admin_user']),
                trim((string) ($in['admin_email'] ?? '')) === '' ? null : trim($in['admin_email']),
                password_hash((string) $in['admin_pass'], PASSWORD_DEFAULT),
            ]);

            $env = $this->envContent($in, $this->generateLicense());
            $written = $this->writeEnv($env);

            $this->lock();
            return ['env_written' => $written, 'env' => $env];
        } catch (\PDOException $e) {
            throw new InstallException('Database error: ' . $e->getMessage());
        } finally {
            @unlink($this->runningFile());
        }
    }

    /** From now on the installer behaves as if it did not exist; the token file is removed. */
    public function lock(): void
    {
        @file_put_contents($this->lockFile(), 'installed ' . date('c') . "\n");
        @unlink($this->tokenFile());
        @unlink($this->attemptsFile());
    }
}

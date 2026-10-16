<?php
namespace Pika\Console;

use Pika\DB;
use Pika\Migrator;
use Pika\Installer;
use Pika\JobRunner;
use Pika\InstallException;

/** Command line tasks behind the `pika` script. Each cmd* method returns an exit code. */
class Commands
{
    private const COMMANDS = [
        'serve [port]'              => 'start the development server (default port 8000)',
        'install'                   => 'first-run wizard: .env, database, migrations, admin account',
        'migrate'                   => 'run pending SQL migrations',
        'migrate:rollback'          => 'undo the last batch of migrations (needs NNN_name.down.sql files)',
        'make:migration <name>'     => 'create database/migrations/NNN_name.sql and .down.sql',
        'make:controller <Name> [--admin]' => 'create a controller (in app/controllers/Admin with --admin)',
        'make:job <name>'           => 'create app/jobs/<Name>.php (name like "mail-send" or "reports/daily")',
        'job:run <name>'            => 'run a job now (cron: * * * * * php -q /path/pika job:run <name>)',
        'make:admin [user] [email]' => 'create an admin account (password prompt, or PIKA_ADMIN_PASSWORD)',
        'key:generate [--force]'    => 'write a random PIKA_LICENSE to .env',
        'log:clear [days]'          => 'delete log files and expired rows older than N days (default 14)',
        'scan:secrets'              => 'look for committed secrets (tokens, keys, passwords)',
        'doctor'                    => 'check the configuration',
    ];

    public static function run(array $argv): int
    {
        $name = $argv[1] ?? 'help';
        $args = array_slice($argv, 2);
        $method = 'cmd' . str_replace(' ', '', ucwords(str_replace([':', '-'], ' ', $name)));

        if (!method_exists(self::class, $method)) {
            self::help();
            return $name === 'help' ? 0 : 1;
        }
        try {
            return self::$method($args);
        } catch (\Throwable $e) {
            self::out('Error: ' . $e->getMessage());
            return 1;
        }
    }

    private static function out(string $line = ''): void { echo $line . "\n"; }

    private static function help(): void
    {
        self::out("Pika CLI\n\nUsage: php pika <command>\n");
        foreach (self::COMMANDS as $cmd => $desc) self::out(sprintf("  %-34s %s", $cmd, $desc));
    }

    /* ---------- server ---------- */

    private static function cmdServe(array $a): int
    {
        $port = (int) ($a[0] ?? 8000);
        passthru('php -S 127.0.0.1:' . $port . ' -t ' . escapeshellarg(BASEPATH . '/public') . ' ' . escapeshellarg(BASEPATH . '/includes/dev_router.php'));
        return 0;
    }

    /* ---------- install ---------- */

    private static function ask(string $label, string $default = '', bool $hidden = false): string
    {
        $answer = self::prompt($label . ($default !== '' ? " [$default]" : '') . ': ', $hidden);
        return $answer === '' ? $default : $answer;
    }

    private static function cmdInstall(array $a): int
    {
        $installer = new Installer(BASEPATH);
        if ($installer->isLocked()) {
            throw new \RuntimeException('Already installed (.env or content/install.lock exists). Nothing to do.');
        }
        $req = $installer->requirements();
        foreach ($req['checks'] as $c) {
            if (!$c['ok']) self::out(($c['required'] ? 'MISSING: ' : 'note: ') . $c['label']);
        }
        if (!$req['ok']) throw new \RuntimeException('Fix the missing requirements first.');

        self::out("Pika installer. The database must already exist and be empty.\n");
        $in = [
            'site_url'    => self::ask('Site address', 'http://localhost:8000'),
            'db_host'     => self::ask('Database host', '127.0.0.1'),
            'db_port'     => self::ask('Database port', '3306'),
            'db_socket'   => self::ask('Database socket (optional, replaces host/port)'),
            'db_name'     => self::ask('Database name'),
            'db_user'     => self::ask('Database user', 'root'),
            'db_pass'     => self::ask('Database password', '', true),
            'admin_user'  => self::ask('Admin username', 'admin'),
            'admin_email' => self::ask('Admin e-mail (optional)'),
            'admin_pass'  => self::ask('Admin password (10+ characters)', '', true),
            'admin_pass2' => self::ask('Repeat admin password', '', true),
        ];
        if (strtolower(self::ask('Configure SMTP for password reset now? (y/N)', 'n')) === 'y') {
            $in += [
                'mail_host' => self::ask('SMTP host'), 'mail_port' => self::ask('SMTP port', '587'),
                'mail_user' => self::ask('SMTP user'), 'mail_pass' => self::ask('SMTP password', '', true),
                'mail_from' => self::ask('Sender address'),
            ];
        }

        if ($errors = $installer->validate($in)) {
            foreach ($errors as $field => $msg) self::out("  $field: $msg");
            throw new \InvalidArgumentException('Please fix the problems above and run the installer again.');
        }

        $result = $installer->install($in);
        self::out("\nInstalled.");
        if (!$result['env_written']) {
            self::out("\n.env could not be written. Create it with this content:\n\n" . $result['env']);
        }
        self::out('Admin login: ' . rtrim($in['site_url'], '/') . '/admin/login/');
        self::out('Next: php pika doctor   (and php pika serve for local development)');
        return 0;
    }

    /* ---------- migrations ---------- */

    private static function migrationFiles(): array
    {
        $files = array_filter(glob(BASEPATH . '/database/migrations/*.sql'), fn($f) => !str_ends_with($f, '.down.sql'));
        sort($files);
        return $files;
    }

    private static function migrator(): Migrator
    {
        return new Migrator(DB::instance(), BASEPATH . '/database/migrations');
    }

    private static function cmdMigrate(array $a): int
    {
        $ran = self::migrator()->migrate();
        foreach ($ran as $name) self::out("migrated: $name");
        self::out($ran ? 'Done.' : 'Nothing to migrate.');
        return 0;
    }

    private static function cmdMigrateRollback(array $a): int
    {
        $undone = self::migrator()->rollback();
        foreach ($undone as $name) self::out("rolled back: $name");
        if (!$undone) self::out('Nothing to roll back.');
        return 0;
    }

    private static function cmdMakeMigration(array $a): int
    {
        $name = $a[0] ?? '';
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) throw new \InvalidArgumentException('Usage: make:migration <snake_case_name>');

        $max = 0;
        foreach (self::migrationFiles() as $f) $max = max($max, (int) basename($f));
        $base = BASEPATH . '/database/migrations/' . sprintf('%03d_%s', $max + 1, $name);

        file_put_contents("$base.sql", "-- up: $name\n");
        file_put_contents("$base.down.sql", "-- down: $name\n");
        self::out('created: ' . basename($base) . '.sql and .down.sql');
        return 0;
    }

    /* ---------- generators ---------- */

    private static function cmdMakeController(array $a): int
    {
        $name = $a[0] ?? '';
        $admin = in_array('--admin', $a, true);
        if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) throw new \InvalidArgumentException('Usage: make:controller <StudlyName> [--admin]');

        $file = BASEPATH . '/app/controllers/' . ($admin ? 'Admin/' : '') . $name . '.php';
        if (file_exists($file)) throw new \RuntimeException('Already exists: ' . $file);
        if (!is_dir(dirname($file))) mkdir(dirname($file), 0755, true);

        $ns = 'App\\Controllers' . ($admin ? '\\Admin' : '');
        $base = $admin ? 'AdminController' : 'Controller';
        $view = $admin ? 'dashboard' : 'home';   // views that ship with each theme
        file_put_contents($file, <<<PHP
<?php
namespace $ns;

use Pika\\$base,
    Symfony\\Component\\HttpFoundation\\Request,
    Symfony\\Component\\HttpFoundation\\Response;

class $name extends $base
{
    public function index(Request \$request, Response \$response)
    {
        return \$response->setContent(\$this->view('$view', ['title' => '$name']));
    }
}

PHP);
        self::out('created: ' . str_replace(BASEPATH . '/', '', $file));
        self::out("route:   \$router->add('GET|HEAD', '/path', '" . ($admin ? 'Admin.' : '') . "$name@index');");
        return 0;
    }

    private static function cmdMakeJob(array $a): int
    {
        $name = $a[0] ?? '';
        if (!JobRunner::validName($name)) throw new \InvalidArgumentException('Usage: make:job <name>  (lowercase letters, digits, - _ ; use / for sub-folders, e.g. reports/daily)');

        $class = self::jobRunner()->classFor($name);
        $file = BASEPATH . '/app/jobs/' . str_replace('\\', '/', substr($class, strlen('App\\Jobs\\'))) . '.php';
        if (file_exists($file)) throw new \RuntimeException('Already exists: ' . $file);
        if (!is_dir(dirname($file))) mkdir(dirname($file), 0755, true);

        $ns = substr($class, 0, strrpos($class, '\\'));
        $short = substr($class, strrpos($class, '\\') + 1);
        file_put_contents($file, <<<PHP
<?php
namespace $ns;

use Pika\\Job;

class $short extends Job
{
    public function handle(): void
    {
        // Do a bounded amount of work per run; throw to report a failure.
    }
}

PHP);
        self::out('created: ' . str_replace(BASEPATH . '/', '', $file));
        self::out("cron:    * * * * * " . PHP_BINARY . ' -q ' . BASEPATH . "/pika job:run $name");
        return 0;
    }

    private static function jobRunner(): JobRunner { return JobRunner::forApp(); }

    private static function cmdJobRun(array $a): int
    {
        $name = $a[0] ?? '';
        $r = self::jobRunner()->run($name);
        self::out("$name: {$r['status']} ({$r['seconds']}s)" . ($r['message'] !== '' ? " {$r['message']}" : ''));
        return $r['status'] === 'failed' ? 1 : 0;   // "skipped" is normal: the previous run has not finished
    }

    private static function prompt(string $label, bool $hidden = false): string
    {
        echo $label;
        $tty = stream_isatty(STDIN);
        if ($hidden && $tty) shell_exec('stty -echo');
        $line = trim((string) fgets(STDIN));
        if ($hidden && $tty) { shell_exec('stty echo'); echo "\n"; }
        return $line;
    }

    private static function cmdMakeAdmin(array $a): int
    {
        $username = $a[0] ?? self::prompt('Username: ');
        $email = $a[1] ?? self::prompt('E-mail (optional): ');
        $password = pika_env('PIKA_ADMIN_PASSWORD');
        if ($password === null) {
            $password = self::prompt('Password: ', true);
            if ($password !== self::prompt('Repeat password: ', true)) throw new \RuntimeException('Passwords do not match.');
        }

        if (!preg_match('/^[A-Za-z0-9_.-]{3,60}$/', $username)) throw new \InvalidArgumentException('Username must be 3-60 characters: letters, digits, _ . -');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Invalid e-mail address.');
        if ($errors = password_policy_errors($password, $username)) throw new \InvalidArgumentException(implode(' ', $errors));
        if (DB::getVar("SELECT COUNT(*) FROM users WHERE username = ?", [$username])) throw new \RuntimeException('Username already exists.');

        DB::insert(
            "INSERT INTO users (username, email, password, admin, status) VALUES (?, ?, ?, 1, 1)",
            [$username, $email === '' ? null : $email, password_hash($password, PASSWORD_DEFAULT)]
        );
        self::out("Admin '$username' created.");
        return 0;
    }

    /* ---------- maintenance ---------- */

    private static function cmdKeyGenerate(array $a): int
    {
        $env = BASEPATH . '/.env';
        $key = bin2hex(random_bytes(32));
        $content = is_file($env) ? file_get_contents($env) : '';

        if (preg_match('/^PIKA_LICENSE=(.+)$/m', $content, $m) && !in_array('--force', $a, true) && strlen(trim($m[1])) >= 32 && trim($m[1]) !== 'change-me') {
            throw new \RuntimeException('PIKA_LICENSE is already set. Changing it makes everything encrypted with SecureString unreadable; use --force to replace it.');
        }
        $content = preg_match('/^PIKA_LICENSE=.*$/m', $content)
            ? preg_replace('/^PIKA_LICENSE=.*$/m', "PIKA_LICENSE=$key", $content)
            : rtrim($content) . "\nPIKA_LICENSE=$key\n";
        file_put_contents($env, ltrim($content));
        self::out('PIKA_LICENSE written to .env');
        return 0;
    }

    private static function cmdLogClear(array $a): int
    {
        $days = max(1, (int) ($a[0] ?? 14));
        $cutoff = time() - $days * 86400;
        $removed = 0;
        foreach (glob(BASEPATH . '/content/logs/pika-*.log') as $f) {
            if (filemtime($f) < $cutoff && unlink($f)) $removed++;
        }
        self::out("log files removed: $removed");

        try {
            DB::exec("DELETE FROM login_attempts WHERE time < ?", [time() - 86400]);
            DB::exec("DELETE FROM rate_limits WHERE window_start < ?", [time() - 86400]);
            DB::exec("DELETE FROM password_resets WHERE expires_at < ?", [time() - 7 * 86400]);
            self::out('expired database rows removed');
        } catch (\Throwable $e) {
            self::out('database cleanup skipped: ' . $e->getMessage());
        }
        return 0;
    }

    private static function cmdDoctor(array $a): int
    {
        $problems = pika_boot_problems();
        self::out('APP_ENV=' . APP_ENV . ' APP_DEBUG=' . (APP_DEBUG ? 'true' : 'false'));
        try {
            DB::getVar('SELECT 1');
            self::out('database: ok');
        } catch (\Throwable $e) {
            $problems[] = 'database: ' . $e->getMessage();
        }
        self::out('PHP CLI: ' . PHP_BINARY . ' (' . PHP_VERSION . ')   project: ' . BASEPATH);
        self::out('HTTP cron: ' . (strlen((string) pika_env('CRON_TOKEN', '')) >= 32 ? 'enabled (CRON_TOKEN set)' : 'disabled (set CRON_TOKEN, 32+ characters, to enable)'));
        self::doctorJobs();
        foreach ($problems as $p) self::out("PROBLEM: $p");
        if (!$problems) self::out('No problems found.');
        return $problems ? 1 : 0;
    }

    private static function doctorJobs(): void
    {
        $jobs = self::jobRunner()->all();
        if (!$jobs) { self::out('jobs: none (php pika make:job <name>)'); return; }

        foreach ($jobs as $j) {
            $l = $j['last'];
            self::out(sprintf('job %-28s %s', $j['name'], $l ? "last run {$l['status']} " . date('Y-m-d H:i:s', $l['at']) : 'never run'));
        }
    }

    /* ---------- secret scan ---------- */

    private const SECRET_PATTERNS = [
        'private key'        => '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
        'AWS access key'     => '/\bAKIA[0-9A-Z]{16}\b/',
        'GitHub token'       => '/\bgh[pousr]_[A-Za-z0-9]{36,}\b/',
        'Slack token'        => '/\bxox[baprs]-[A-Za-z0-9-]{10,}/',
        'Telegram bot token' => '/\b\d{8,10}:[A-Za-z0-9_-]{35}\b/',
        'hard-coded secret'  => '/(password|passwd|secret|api[_-]?key|token)[\'"]?\s*(=>|=|:)\s*[\'"][^\'"\s$\{|]{12,}[\'"]/i',
    ];

    /** Lines containing this marker are skipped (for examples and test fixtures). */
    private const SCAN_IGNORE = 'pika-secret-scan: ignore';

    private static function cmdScanSecrets(array $a): int
    {
        $skip = ['vendor', 'node_modules', '.git', 'cache', 'logs', 'uploads'];
        $it = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator(BASEPATH, \FilesystemIterator::SKIP_DOTS),
            fn($f) => !in_array($f->getFilename(), $skip, true)
        ));

        $findings = 0;
        foreach ($it as $file) {
            $rel = substr($file->getPathname(), strlen(BASEPATH) + 1);
            if (!$file->isFile() || $file->getSize() > 1_000_000 || $rel === '.env' || str_starts_with($rel, 'public/uploads')) continue;
            $text = @file_get_contents($file->getPathname());
            if ($text === false || str_contains(substr($text, 0, 2048), "\0")) continue;   // binary

            foreach (explode("\n", $text) as $n => $line) {
                if (str_contains($line, self::SCAN_IGNORE)) continue;
                foreach (self::SECRET_PATTERNS as $label => $re) {
                    // test fixtures legitimately contain sample passwords: only high-confidence patterns apply there
                    if ($label === 'hard-coded secret' && str_starts_with($rel, 'tests/')) continue;
                    if (preg_match($re, $line)) {
                        self::out(sprintf('%s:%d  possible %s', $rel, $n + 1, $label));
                        $findings++;
                    }
                }
            }
        }

        $gitignore = is_file(BASEPATH . '/.gitignore') ? file_get_contents(BASEPATH . '/.gitignore') : '';
        if (!preg_match('/^\.env$/m', $gitignore)) {
            self::out('.gitignore does not list .env');
            $findings++;
        }
        self::out($findings ? "$findings possible problem(s). Add '" . self::SCAN_IGNORE . "' to a line to mark a false positive." : 'No secrets found.');
        return $findings ? 1 : 0;
    }
}

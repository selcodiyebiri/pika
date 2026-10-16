<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tests\Support\HttpClient;
use Tests\Support\Skeleton;
use Tests\Support\TestServer;

/**
 * The real installer page, served from a temporary copy of the project, installing into its own
 * database (pika_installer_test). Nothing here touches the real project folder or .env.
 */
class InstallerWebTest extends TestCase
{
    private const DB = 'pika_installer_test';
    private const TOKEN = 'correct-horse-battery-staple';

    private Skeleton $skel;
    private TestServer $server;
    private \PDO $admin;

    protected function setUp(): void
    {
        if (empty($GLOBALS['PIKA_TEST_DB'])) $this->markTestSkipped('No MySQL test database available.');

        $target = MYSQL_SOCKET !== '' ? 'unix_socket=' . MYSQL_SOCKET : 'host=' . MYSQL_HOST . ';port=' . MYSQL_PORT;
        $this->admin = new \PDO("mysql:$target;charset=utf8mb4", MYSQL_USER, MYSQL_PASS, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $this->admin->exec('DROP DATABASE IF EXISTS `' . self::DB . '`');
        $this->admin->exec('CREATE DATABASE `' . self::DB . '` CHARACTER SET utf8mb4');

        $this->skel = new Skeleton();
        $this->server = new TestServer([], $this->skel->root, true);
    }

    protected function tearDown(): void
    {
        if (!empty($this->server)) $this->server->stop();
        if (!empty($this->skel)) $this->skel->destroy();
        if (!empty($this->admin)) $this->admin->exec('DROP DATABASE IF EXISTS `' . self::DB . '`');
    }

    private function client(): HttpClient { return new HttpClient($this->server->url); }

    private function csrf(HttpClient $c, string $path = '/install.php'): string
    {
        preg_match('/name="csrf" value="([^"]+)"/', $c->get($path)->body, $m);
        return $m[1] ?? '';
    }

    private function unlock(HttpClient $c): void
    {
        $this->skel->writeToken(self::TOKEN);
        $r = $c->post('/install.php', ['csrf' => $this->csrf($c), 'token' => self::TOKEN]);
        $this->assertSame(303, $r->status, 'token accepted');
    }

    private function form(array $over = []): array
    {
        return $over + [
            'step' => 'install', 'site_url' => $this->server->url,
            'db_host' => MYSQL_HOST, 'db_port' => (string) MYSQL_PORT, 'db_socket' => MYSQL_SOCKET,
            'db_name' => self::DB, 'db_user' => MYSQL_USER, 'db_pass' => MYSQL_PASS,
            'admin_user' => 'owner', 'admin_email' => 'owner@example.com',
            'admin_pass' => 'Correct-horse-9', 'admin_pass2' => 'Correct-horse-9',
        ];
    }

    private function install(HttpClient $c, array $over = []): object
    {
        return $c->post('/install.php', ['csrf' => $this->csrf($c)] + $this->form($over));
    }

    private function testDb(): \PDO
    {
        $target = MYSQL_SOCKET !== '' ? 'unix_socket=' . MYSQL_SOCKET : 'host=' . MYSQL_HOST . ';port=' . MYSQL_PORT;
        return new \PDO("mysql:$target;dbname=" . self::DB . ';charset=utf8mb4', MYSQL_USER, MYSQL_PASS, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    }

    public function testDoesNotExistWithoutAValidTokenFile(): void
    {
        $c = $this->client();
        $this->assertSame(404, $c->get('/install.php')->status, 'no token file');
        $this->skel->writeToken('short');
        $this->assertSame(404, $c->get('/install.php')->status, 'token shorter than 16 characters');
        $this->assertSame(404, $c->post('/install.php', ['token' => 'short'])->status);
    }

    public function testGateHeadersAndNoSecretsInTheMarkup(): void
    {
        $this->skel->writeToken(self::TOKEN);
        $r = $this->client()->get('/install.php');
        $this->assertSame(200, $r->status);
        $this->assertStringContainsString('name="token"', $r->body);
        $this->assertStringNotContainsString(self::TOKEN, $r->body);
        $this->assertStringContainsString('noindex', $r->headers['x-robots-tag']);
        $this->assertStringContainsString('no-store', $r->headers['cache-control']);
        $this->assertStringNotContainsString('unsafe-inline', $r->headers['content-security-policy']);
        $this->assertStringContainsString("frame-ancestors 'none'", $r->headers['content-security-policy']);
        preg_match("/style-src 'nonce-([^']+)'/", $r->headers['content-security-policy'], $m);
        $this->assertStringContainsString('<style nonce="' . $m[1] . '">', $r->body);
    }

    public function testFormsNeedTheCsrfToken(): void
    {
        $this->skel->writeToken(self::TOKEN);
        $this->assertSame(403, $this->client()->post('/install.php', ['token' => self::TOKEN])->status);
    }

    public function testRefusesToRunOverPlainHttpOnARealHost(): void
    {
        $this->skel->writeToken(self::TOKEN);
        $r = $this->client()->get('/install.php', ['Host' => 'example.com']);
        $this->assertSame(403, $r->status);
        $this->assertStringContainsString('HTTPS required', $r->body);
    }

    public function testWrongTokensAreThrottled(): void
    {
        $this->skel->writeToken(self::TOKEN);
        $c = $this->client();
        $csrf = $this->csrf($c);
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(403, $c->post('/install.php', ['csrf' => $csrf, 'token' => "wrong-token-$i-xxxxxxxx"])->status);
        }
        $this->assertSame(429, $c->post('/install.php', ['csrf' => $csrf, 'token' => self::TOKEN])->status, 'even the right token waits');
    }

    public function testInvalidInputIsRejectedAndNothingIsWritten(): void
    {
        $c = $this->client();
        $this->unlock($c);
        $page = $c->get('/install.php');
        $this->assertStringContainsString('Server checks', $page->body);

        $r = $this->install($c, ['site_url' => 'http://example.com', 'admin_pass' => 'weakpass', 'admin_pass2' => 'weakpass']);
        $this->assertSame(200, $r->status);
        $this->assertStringContainsString('must use https', $r->body);
        $this->assertStringNotContainsString('weakpass', $r->body, 'passwords are never echoed back');
        $this->assertFileDoesNotExist($this->skel->path('.env'));
        $this->assertFileDoesNotExist($this->skel->path('content/install.lock'));
    }

    public function testRefusesADatabaseThatAlreadyHasUsers(): void
    {
        $db = $this->testDb();
        (new \Pika\Migrator($db, $this->skel->path('database/migrations')))->migrate();
        $db->exec("INSERT INTO users (username, password, admin, status) VALUES ('existing', 'x', 1, 1)");

        $c = $this->client();
        $this->unlock($c);
        $r = $this->install($c);
        $this->assertStringContainsString('already contains users', $r->body);
        $this->assertFileDoesNotExist($this->skel->path('.env'));
        $this->assertSame('1', (string) $db->query('SELECT COUNT(*) FROM users')->fetchColumn(), 'no admin was added');
    }

    public function testWrongDatabaseCredentialsGiveAMessageAndInstallNothing(): void
    {
        $c = $this->client();
        $this->unlock($c);
        $r = $this->install($c, ['db_pass' => 'definitely-wrong-password', 'db_socket' => '', 'db_host' => '127.0.0.1', 'db_port' => '1']);
        $this->assertStringContainsString('Could not connect to the database', $r->body);
        $this->assertFileDoesNotExist($this->skel->path('.env'));
    }

    public function testFullInstallationLocksTheInstallerAndTheAppWorks(): void
    {
        $c = $this->client();
        $this->unlock($c);

        $done = $this->install($c);
        $this->assertSame(200, $done->status);
        $this->assertStringContainsString('Installation complete', $done->body);
        $this->assertStringNotContainsString('Correct-horse-9', $done->body);
        $this->assertStringNotContainsString('<textarea', $done->body, '.env was written, so nothing is shown');

        // files
        $envPath = $this->skel->path('.env');
        $this->assertFileExists($envPath);
        $this->assertSame(0600, fileperms($envPath) & 0777);
        $env = pika_parse_env(file_get_contents($envPath));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $env['PIKA_LICENSE']);
        $this->assertSame(['local', 'false', self::DB], [$env['APP_ENV'], $env['APP_DEBUG'], $env['MYSQL_DB']]);
        $this->assertFileExists($this->skel->path('content/install.lock'));
        $this->assertFileDoesNotExist($this->skel->path('content/install.token'));

        // database
        $db = $this->testDb();
        $admin = $db->query("SELECT * FROM users WHERE username = 'owner'")->fetch(\PDO::FETCH_OBJ);
        $this->assertSame(1, (int) $admin->admin);
        $this->assertTrue(password_verify('Correct-horse-9', $admin->password));
        $this->assertGreaterThanOrEqual(6, (int) $db->query('SELECT COUNT(*) FROM migrations')->fetchColumn());

        // the installer is gone, even if someone recreates the token
        $this->assertSame(404, $this->client()->get('/install.php')->status);
        $this->skel->writeToken(self::TOKEN);
        $this->assertSame(404, $this->client()->get('/install.php')->status);

        // the freshly installed app boots from the generated .env and the admin can sign in
        $app = $this->client();
        $this->assertSame(200, $app->get('/login')->status);
        preg_match('/name="token" value="([^"]+)"/', $app->get('/admin/login')->body, $t);
        $login = $app->post('/admin/login', ['token' => $t[1], 'username' => 'owner', 'password' => 'Correct-horse-9']);
        $this->assertSame(303, $login->status);
        $this->assertStringContainsString('/admin/', $login->location);
        $this->assertSame(200, $app->get('/admin')->status);
    }

    public function testEnvIsShownOnceWhenTheFolderIsNotWritable(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) $this->markTestSkipped('root ignores directory permissions.');

        $c = $this->client();
        $this->unlock($c);
        chmod($this->skel->root, 0555);
        try {
            $done = $this->install($c);
        } finally {
            chmod($this->skel->root, 0755);
        }
        $this->assertStringContainsString('Installation complete', $done->body);
        $this->assertStringContainsString('<textarea', $done->body);
        $this->assertStringContainsString('PIKA_LICENSE=', $done->body);
        $this->assertFileDoesNotExist($this->skel->path('.env'));
        $this->assertFileExists($this->skel->path('content/install.lock'), 'still locked');
        $this->assertSame(404, $this->client()->get('/install.php')->status);
    }

    public function testCliWizardInstallsAndThenRefuses(): void
    {
        $answers = ["http://localhost:8000", MYSQL_HOST, (string) MYSQL_PORT, MYSQL_SOCKET, self::DB, MYSQL_USER, MYSQL_PASS,
            'cliadmin', 'cli@example.com', 'Correct-horse-9', 'Correct-horse-9', 'n'];
        $run = function (string $stdin) {
            $env = array_diff_key(array_filter(array_merge(getenv(), $_ENV), 'is_string'),
                array_flip(['APP_ENV', 'APP_DEBUG', 'PIKA_LICENSE', 'SITE_URL', 'MYSQL_HOST', 'MYSQL_PORT', 'MYSQL_SOCKET', 'MYSQL_DB', 'MYSQL_USER', 'MYSQL_PASS']));
            $p = proc_open([PHP_BINARY, 'pika', 'install'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->skel->root, $env);
            fwrite($pipes[0], $stdin);
            fclose($pipes[0]);
            $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            return [proc_close($p), $out];
        };

        [$code, $out] = $run(implode("\n", $answers) . "\n");
        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('Installed.', $out);
        $this->assertFileExists($this->skel->path('.env'));
        $this->assertFileExists($this->skel->path('content/install.lock'));
        $this->assertSame('1', (string) $this->testDb()->query("SELECT COUNT(*) FROM users WHERE username = 'cliadmin' AND admin = 1")->fetchColumn());

        [$code, $out] = $run('');
        $this->assertSame(1, $code);
        $this->assertStringContainsString('Already installed', $out);
    }
}

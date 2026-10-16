<?php
use PHPUnit\Framework\TestCase;
use Pika\InstallException;
use Pika\Installer;
use Tests\Support\Skeleton;

class InstallerTest extends TestCase
{
    private Skeleton $skel;
    private Installer $inst;

    protected function setUp(): void
    {
        $this->skel = new Skeleton();
        $this->inst = new Installer($this->skel->root);
    }

    protected function tearDown(): void { $this->skel->destroy(); }

    private function valid(array $over = []): array
    {
        return $over + [
            'site_url' => 'https://example.com', 'db_host' => 'localhost', 'db_port' => '3306', 'db_name' => 'pika_db', 'db_user' => 'u',
            'db_pass' => 'p', 'admin_user' => 'admin', 'admin_email' => 'a@example.com', 'admin_pass' => 'Correct-horse-9', 'admin_pass2' => 'Correct-horse-9',
        ];
    }

    public function testValidInputPasses(): void
    {
        $this->assertSame([], $this->inst->validate($this->valid()));
        $this->assertSame([], $this->inst->validate($this->valid(['site_url' => 'http://localhost:8888/pika', 'db_host' => '', 'db_socket' => '/tmp/mysql.sock'])));
    }

    /** @dataProvider invalidInput */
    public function testInvalidInputIsReported(array $over, string $field): void
    {
        $this->assertArrayHasKey($field, $this->inst->validate($this->valid($over)));
    }

    public static function invalidInput(): array
    {
        return [
            'http on a real host'   => [['site_url' => 'http://example.com'], 'site_url'],
            'no scheme'             => [['site_url' => 'example.com'], 'site_url'],
            'javascript url'        => [['site_url' => 'javascript:alert(1)'], 'site_url'],
            'url with query'        => [['site_url' => 'https://example.com/?a=1'], 'site_url'],
            'missing db host'       => [['db_host' => ''], 'db_host'],
            'bad port'              => [['db_port' => '99999'], 'db_port'],
            'db name with quote'    => [['db_name' => 'a`; DROP'], 'db_name'],
            'missing db user'       => [['db_user' => ''], 'db_user'],
            'short admin name'      => [['admin_user' => 'ab'], 'admin_user'],
            'bad email'             => [['admin_email' => 'nope'], 'admin_email'],
            'weak password'         => [['admin_pass' => 'short', 'admin_pass2' => 'short'], 'admin_pass'],
            'password mismatch'     => [['admin_pass2' => 'Different-pass-9'], 'admin_pass2'],
            'smtp without sender'   => [['mail_host' => 'smtp.example.com'], 'mail_from'],
            'newline in a value'    => [['db_pass' => "x\nAPP_DEBUG=true"], 'db_pass'],
        ];
    }

    public function testTransportRules(): void
    {
        $this->assertTrue(Installer::transportOk(true, 'example.com'));
        $this->assertTrue(Installer::transportOk(false, 'localhost:8888'));
        $this->assertTrue(Installer::transportOk(false, '127.0.0.1:8000'));
        $this->assertFalse(Installer::transportOk(false, 'example.com'));
        $this->assertFalse(Installer::transportOk(false, 'localhost.evil.com'));
    }

    public function testTokenFileGatesTheInstaller(): void
    {
        $this->assertFalse($this->inst->tokenEnabled(), 'no token file');
        $this->skel->writeToken('too-short');
        $this->assertFalse($this->inst->tokenEnabled(), 'token shorter than 16 characters');
        $this->skel->writeToken("a-long-enough-secret-1\n");
        $this->assertTrue($this->inst->tokenEnabled());
        $this->assertTrue($this->inst->checkToken('a-long-enough-secret-1'));
        $this->assertFalse($this->inst->checkToken('a-long-enough-secret-2'));
        $this->assertFalse($this->inst->checkToken(''));
    }

    public function testWrongTokensLockTheDoorEvenForTheRightToken(): void
    {
        $this->skel->writeToken('a-long-enough-secret-1');
        for ($i = 0; $i < 5; $i++) $this->assertFalse($this->inst->checkToken("wrong-$i"));
        $this->assertGreaterThan(0, $this->inst->blockedFor());
        $this->assertFalse($this->inst->checkToken('a-long-enough-secret-1'), 'blocked');

        unlink($this->skel->path('content/cache/install-attempts.json'));
        $this->assertTrue($this->inst->checkToken('a-long-enough-secret-1'));
    }

    public function testLockedByEnvOrLockFile(): void
    {
        $this->assertFalse($this->inst->isLocked());
        file_put_contents($this->skel->path('content/install.lock'), 'x');
        $this->assertTrue($this->inst->isLocked());
        unlink($this->skel->path('content/install.lock'));
        file_put_contents($this->skel->path('.env'), 'APP_ENV=local');
        $this->assertTrue($this->inst->isLocked());
    }

    public function testLockRemovesTheToken(): void
    {
        $this->skel->writeToken('a-long-enough-secret-1');
        $this->inst->lock();
        $this->assertFileDoesNotExist($this->skel->path('content/install.token'));
        $this->assertTrue($this->inst->isLocked());
        $this->assertFalse($this->inst->tokenEnabled());
    }

    public function testEnvContentSurvivesTheLoaderWithNastyValues(): void
    {
        $in = $this->valid(['db_pass' => 'p"a\'ss #w=ord\\', 'db_user' => '"quoted"', 'mail_host' => 'smtp.example.com', 'mail_from' => 'no-reply@example.com']);
        $env = pika_parse_env($this->inst->envContent($in, str_repeat('ab', 32)));

        $this->assertSame('p"a\'ss #w=ord\\', $env['MYSQL_PASS']);
        $this->assertSame('"quoted"', $env['MYSQL_USER']);
        $this->assertSame('https://example.com', $env['SITE_URL']);
        $this->assertSame(str_repeat('ab', 32), $env['PIKA_LICENSE']);
        $this->assertSame(['production', 'false', 'smtp'], [$env['APP_ENV'], $env['APP_DEBUG'], $env['MAIL_DRIVER']]);
    }

    public function testLocalSiteGetsLocalEnvironmentAndLogMailer(): void
    {
        $env = pika_parse_env($this->inst->envContent($this->valid(['site_url' => 'http://localhost:8888/pika']), str_repeat('a', 64)));
        $this->assertSame(['local', 'false', 'log'], [$env['APP_ENV'], $env['APP_DEBUG'], $env['MAIL_DRIVER']]);
    }

    public function testEnvContentRefusesLineBreaks(): void
    {
        $this->expectException(InstallException::class);
        $this->inst->envContent($this->valid(['db_pass' => "a\nb"]), 'x');
    }

    public function testWriteEnvNeverOverwritesAndIsPrivate(): void
    {
        $this->assertTrue($this->inst->writeEnv("A=1\n"));
        $this->assertSame(0600, fileperms($this->skel->path('.env')) & 0777);
        $this->assertFalse($this->inst->writeEnv("A=2\n"));
        $this->assertSame("A=1\n", file_get_contents($this->skel->path('.env')));
    }

    public function testInstallRefusesWhenLocked(): void
    {
        $this->inst->lock();
        $this->expectException(InstallException::class);
        $this->inst->install($this->valid());
    }

    public function testRequirementsReportRequiredAndOptionalChecks(): void
    {
        $r = $this->inst->requirements();
        $this->assertTrue($r['ok'], 'the test environment satisfies the requirements');
        $labels = implode("\n", array_column($r['checks'], 'label'));
        $this->assertStringContainsString('PHP 8.2', $labels);
        $this->assertStringContainsString('vendor/', $labels);

        unlink($this->skel->path('vendor'));
        $this->assertFalse((new Installer($this->skel->root))->requirements()['ok'], 'missing vendor/ blocks installation');
    }

    public function testEnvParser(): void
    {
        $env = pika_parse_env("# comment\r\nA=1\nB=\"two words\"\nC='single'\nD=\"a\"\"\nE=x=y\nEMPTY=\nF=\"unterminated\n\nnoequals");
        $this->assertSame(['A' => '1', 'B' => 'two words', 'C' => 'single', 'D' => 'a"', 'E' => 'x=y', 'EMPTY' => '', 'F' => '"unterminated'], $env);
    }
}

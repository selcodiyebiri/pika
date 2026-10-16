<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tests\Support\TestServer;

/** Each case starts its own server with a different environment. */
class BootAndErrorsTest extends TestCase
{
    protected function setUp(): void
    {
        if (empty($GLOBALS['PIKA_TEST_DB'])) $this->markTestSkipped('No MySQL test database available.');
    }

    public function testProductionRefusesToStartWithUnsafeConfig(): void
    {
        $server = new TestServer(['APP_ENV' => 'production', 'APP_DEBUG' => 'true', 'PIKA_LICENSE' => 'short', 'SITE_URL' => 'http://example.com']);
        try {
            $r = $server->client()->get('/login');
            $this->assertSame(500, $r->status);
            $this->assertStringContainsString('Server configuration error', $r->body);
            $this->assertStringNotContainsString('PIKA_LICENSE', $r->body, 'details stay in the log, not in the response');
        } finally { $server->stop(); }
    }

    public function testProductionStartsWithSafeConfig(): void
    {
        $server = new TestServer([
            'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'SITE_URL' => 'https://example.com',
            'PIKA_LICENSE' => str_repeat('a1b2c3d4', 8), 'MAIL_DRIVER' => 'smtp',
        ]);
        try {
            $this->assertSame(200, $server->client()->get('/login')->status);
        } finally { $server->stop(); }
    }

    public function testHstsOnlyOverHttps(): void
    {
        $server = new TestServer();
        try {
            $this->assertArrayNotHasKey('strict-transport-security', $server->client()->get('/login')->headers);
            $r = $server->client()->get('/login', ['X-Forwarded-Proto' => 'https']);
            $this->assertStringContainsString('max-age=', $r->headers['strict-transport-security']);
            $this->assertStringContainsStringIgnoringCase('secure;', $r->headers['set-cookie'], 'session cookie is Secure behind an https proxy');
        } finally { $server->stop(); }
    }

    /** A request that needs the database while the database is unreachable -> exception -> error page. */
    private function brokenDbRequest(array $env): array
    {
        $server = new TestServer($env + ['MYSQL_DB' => 'pika_does_not_exist_test']);
        try {
            $c = $server->client();
            return [$c->post('/login', ['token' => $c->csrf('/login'), 'username' => 'a', 'password' => 'b']), $server];
        } finally { $server->stop(); }
    }

    public function testProductionErrorPageHidesDetailsButShowsRequestId(): void
    {
        [$r] = $this->brokenDbRequest(['APP_DEBUG' => 'false']);
        $this->assertSame(500, $r->status);
        $this->assertStringContainsString('Reference: ' . $r->headers['x-request-id'], $r->body);
        $this->assertStringNotContainsString('PDOException', $r->body);
        $this->assertStringNotContainsString('SQLSTATE', $r->body);

        $log = file_get_contents(BASEPATH . '/content/logs/pika-' . date('Y-m-d') . '.log');
        $this->assertStringContainsString('[' . $r->headers['x-request-id'] . ']', $log, 'the same id is in the log');
    }

    public function testDebugModeShowsStackTrace(): void
    {
        [$r] = $this->brokenDbRequest(['APP_DEBUG' => 'true']);
        $this->assertSame(500, $r->status);
        $this->assertStringContainsString('PDOException', $r->body);
        $this->assertStringContainsString('<pre>', $r->body);
    }
}

<?php
namespace Tests\Integration;

class HttpSecurityTest extends IntegrationTestCase
{
    protected static function serverEnv(): array { return ['RATE_LIMIT' => '5', 'RATE_WINDOW' => '60']; }

    public function testSecurityHeadersAndNonceMatchesBody(): void
    {
        $r = $this->client()->get('/login');
        $h = $r->headers;

        $this->assertSame('nosniff', $h['x-content-type-options']);
        $this->assertSame('DENY', $h['x-frame-options']);
        $this->assertSame('strict-origin-when-cross-origin', $h['referrer-policy']);
        $this->assertArrayHasKey('permissions-policy', $h);
        $this->assertArrayNotHasKey('x-powered-by', $h);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $h['x-request-id']);
        $this->assertStringContainsString('no-store', $h['cache-control']);

        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-([^']+)'/", $h['content-security-policy']);
        $this->assertStringContainsString("frame-ancestors 'none'", $h['content-security-policy']);
        $this->assertStringNotContainsString('unsafe-inline', $h['content-security-policy']);

        // regular pages use the external stylesheet and no inline styling at all
        $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="[^"]+/assets/css/pika\.css">#', $r->body);
        $this->assertStringNotContainsString(' style="', $r->body, 'inline style attributes would be blocked by the CSP');

        // the self-contained error page carries the per-request nonce on its inline <style>
        $notFound = $this->client()->get('/nope');
        preg_match("/style-src 'self' 'nonce-([^']+)'/", $notFound->headers['content-security-policy'], $csp);
        $this->assertStringContainsString('<style nonce="' . $csp[1] . '">', $notFound->body);
    }

    public function testNonceChangesPerRequest(): void
    {
        $c = $this->client();
        $this->assertNotSame($c->get('/login')->headers['content-security-policy'], $c->get('/login')->headers['content-security-policy']);
    }

    public function testSessionCookieFlags(): void
    {
        $r = $this->client()->get('/login');
        $this->assertStringContainsString('HttpOnly', $r->headers['set-cookie']);
        $this->assertStringContainsString('SameSite=Lax', $r->headers['set-cookie']);
    }

    public function testHeadRequestsWork(): void
    {
        $c = $this->client();
        foreach (['/', '/login', '/forgot'] as $path) {
            $r = $c->request('HEAD', $path);
            $this->assertSame(200, $r->status, "HEAD $path");
            $this->assertSame('', $r->body);
        }
    }

    public function testUnknownAndSensitivePathsAre404(): void
    {
        $c = $this->client();
        foreach (['/nope', '/.env', '/composer.json', '/includes/config.php', '/vendor/autoload.php', '/database/migrations/001_init.sql'] as $path) {
            $this->assertSame(404, $c->get($path)->status, $path);
        }
    }

    public function testStateChangingRequestsNeedCsrfToken(): void
    {
        $c = $this->client();
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $m) {
            $this->assertSame(403, $c->request($m, '/login', ['username' => 'a'])->status, $m);
        }
    }

    public function testCsrfTokenAcceptedFromHeader(): void
    {
        $this->makeUser('alice');
        $c = $this->client();
        $token = $c->csrf('/login');
        $r = $c->post('/login', ['username' => 'alice', 'password' => 'Correct-horse-9'], ['X-CSRF-Token' => $token]);
        $this->assertSame(303, $r->status);
        $this->assertSame(403, $c->post('/login', ['username' => 'alice'], ['X-CSRF-Token' => 'forged'])->status);
    }

    public function testApiRateLimit(): void
    {
        $c = $this->client('10.77.0.1');
        for ($i = 0; $i < 5; $i++) $this->assertSame(200, $c->get('/api/health')->status);
        $blocked = $c->get('/api/health');
        $this->assertSame(429, $blocked->status);
        $this->assertArrayHasKey('retry-after', $blocked->headers);
        $this->assertSame(200, $this->client('10.77.0.2')->get('/api/health')->status, 'limit is per IP');
    }
}

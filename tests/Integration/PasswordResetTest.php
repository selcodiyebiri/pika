<?php
namespace Tests\Integration;

use Pika\DB;

class PasswordResetTest extends IntegrationTestCase
{
    private function mailedToken(): ?string
    {
        $log = BASEPATH . '/content/logs/pika-' . date('Y-m-d') . '.log';
        if (!is_file($log)) return null;
        preg_match_all('#/reset/([A-Za-z0-9]{64})/#', file_get_contents($log), $m);
        return $m[1] ? end($m[1]) : null;
    }

    private function request(string $email, string $ip = '10.5.0.1'): object
    {
        $c = $this->client($ip);
        return $c->post('/forgot', ['token' => $c->csrf('/forgot'), 'email' => $email]);
    }

    public function testRequestLooksTheSameForKnownAndUnknownAddresses(): void
    {
        $this->makeUser('alice', 'Correct-horse-9', 0, 'alice@example.com');

        $known = $this->request('alice@example.com');
        $unknown = $this->request('ghost@example.com');
        $this->assertSame($known->status, $unknown->status);
        $this->assertStringContainsString('a reset link has been sent', $known->body);
        $this->assertStringContainsString('a reset link has been sent', $unknown->body);
        $this->assertSame(1, (int) DB::getVar("SELECT COUNT(*) FROM password_resets"));
    }

    public function testFullResetFlow(): void
    {
        $id = $this->makeUser('alice', 'Correct-horse-9', 0, 'alice@example.com');
        $session = $this->client();
        $this->login($session, 'alice');

        $this->request('alice@example.com');
        $token = $this->mailedToken();
        $this->assertNotNull($token, 'reset mail (log driver) contains the link');
        $this->assertNotSame($token, DB::getVar("SELECT token_hash FROM password_resets WHERE user_id = ?", [$id]), 'only a hash is stored');

        $c = $this->client();
        $this->assertSame(200, $c->get("/reset/$token/")->status);

        $weak = $c->post("/reset/$token/", ['token' => $c->csrf("/reset/$token/"), 'password' => 'short', 'password_confirmation' => 'short']);
        $this->assertSame(422, $weak->status);
        $mismatch = $c->post("/reset/$token/", ['token' => $c->csrf("/reset/$token/"), 'password' => 'A-new-password-1', 'password_confirmation' => 'different-pass-1']);
        $this->assertSame(422, $mismatch->status);

        $ok = $c->post("/reset/$token/", ['token' => $c->csrf("/reset/$token/"), 'password' => 'A-new-password-1', 'password_confirmation' => 'A-new-password-1']);
        $this->assertSame(303, $ok->status);

        $this->assertSame(401, $this->login($this->client(), 'alice', 'Correct-horse-9')->status, 'old password is dead');
        $this->assertSame(303, $this->login($this->client(), 'alice', 'A-new-password-1')->status);
        $this->assertSame(200, $session->get('/login')->status, 'sessions opened before the reset are ended');
        $this->assertSame(400, $this->client()->get("/reset/$token/")->status, 'token is single use');
    }

    public function testExpiredAndMalformedTokensAreRejected(): void
    {
        $this->makeUser('alice', 'Correct-horse-9', 0, 'alice@example.com');
        $this->request('alice@example.com');
        $token = $this->mailedToken();
        DB::exec("UPDATE password_resets SET expires_at = ?", [time() - 1]);

        $this->assertSame(400, $this->client()->get("/reset/$token/")->status);
        $this->assertSame(400, $this->client()->get('/reset/not-a-token/')->status);
    }

    public function testNewRequestInvalidatesOlderToken(): void
    {
        $this->makeUser('alice', 'Correct-horse-9', 0, 'alice@example.com');
        $this->request('alice@example.com');
        $first = $this->mailedToken();
        DB::exec("UPDATE password_resets SET created_at = created_at - 600");   // the 2 minute cool-down has passed
        $this->request('alice@example.com', '10.5.0.2');
        $second = $this->mailedToken();

        $this->assertNotSame($first, $second);
        $this->assertSame(400, $this->client()->get("/reset/$first/")->status);
        $this->assertSame(200, $this->client()->get("/reset/$second/")->status);
    }

    public function testRepeatedRequestsDoNotFloodOrKillTheEarlierLink(): void
    {
        $this->makeUser('alice', 'Correct-horse-9', 0, 'alice@example.com');
        $this->request('alice@example.com');
        $first = $this->mailedToken();

        // an attacker asking again and again (from other IPs) changes nothing for the real owner
        foreach (['10.5.0.2', '10.5.0.3', '10.5.0.4'] as $ip) $this->request('alice@example.com', $ip);

        $this->assertSame(1, (int) DB::getVar("SELECT COUNT(*) FROM password_resets"));
        $this->assertSame($first, $this->mailedToken());
        $this->assertSame(200, $this->client()->get("/reset/$first/")->status);
    }

    public function testRequestsAreRateLimited(): void
    {
        for ($i = 0; $i < 5; $i++) $this->assertSame(200, $this->request('x@example.com', '10.6.0.1')->status);
        $this->assertSame(429, $this->request('x@example.com', '10.6.0.1')->status);
    }
}

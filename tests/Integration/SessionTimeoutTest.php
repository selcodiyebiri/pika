<?php
namespace Tests\Integration;

class SessionTimeoutTest extends IntegrationTestCase
{
    protected static function serverEnv(): array { return ['SESSION_IDLE' => '2', 'SESSION_ABSOLUTE' => '4']; }

    public function testIdleSessionExpires(): void
    {
        $this->makeUser('alice');
        $c = $this->client();
        $this->login($c, 'alice');
        $this->assertSame(303, $c->get('/login')->status);
        sleep(3);
        $this->assertSame(200, $c->get('/login')->status, 'idle for longer than SESSION_IDLE');
    }

    public function testActiveSessionHitsAbsoluteLimit(): void
    {
        $this->makeUser('alice');
        $c = $this->client();
        $this->login($c, 'alice');
        for ($i = 0; $i < 5; $i++) { sleep(1); $c->get('/forgot'); }   // stays active, never idle
        $this->assertSame(200, $c->get('/login')->status, 'older than SESSION_ABSOLUTE');
    }
}

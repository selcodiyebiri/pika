<?php
namespace Tests\Integration;

use Pika\DB;

class AuthFlowTest extends IntegrationTestCase
{
    public function testSiteLoginSuccessAndWrongPassword(): void
    {
        $this->makeUser('alice');
        $c = $this->client();

        $bad = $this->login($c, 'alice', 'wrong-password');
        $this->assertSame(401, $bad->status);
        $this->assertStringContainsString('Invalid username or password.', $bad->body);
        $this->assertStringContainsString('value="alice"', $bad->body, 'username is refilled');
        $this->assertStringNotContainsString('wrong-password', $bad->body, 'password is never echoed');

        $ok = $this->login($c, 'alice');
        $this->assertSame(303, $ok->status);
        $this->assertSame(1, (int) DB::getVar("SELECT COUNT(*) FROM user_logs WHERE action = 'login'"));
    }

    public function testLoginPageBuildsItsLinksFromNamedRoutes(): void
    {
        // BaseLogin calls route('FORGOT') while handling the request: route() lives in helpers.php, the router in the loader
        $site = $this->client()->get('/login');
        $this->assertMatchesRegularExpression('#href="' . preg_quote(self::$server->url, '#') . '/forgot/"#', $site->body);
        $this->assertSame(200, $this->client()->get('/forgot/')->status, 'the generated URL is a working page');
        $this->assertStringNotContainsString('Forgot password?', $this->client()->get('/admin/login')->body, 'admin login has no forgot link');
    }

    public function testUnknownUserLooksLikeWrongPassword(): void
    {
        $r = $this->login($this->client(), 'nobody');
        $this->assertSame(401, $r->status);
        $this->assertStringContainsString('Invalid username or password.', $r->body);
    }

    public function testErrorMessageIsEscaped(): void
    {
        $r = $this->login($this->client(), '"><script>alert(1)</script>');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $r->body);
    }

    public function testAdminLoginAcceptsOnlyAdmins(): void
    {
        $this->makeUser('bob');
        $this->makeUser('root', 'Correct-horse-9', 1);

        $this->assertSame(401, $this->login($this->client(), 'bob', 'Correct-horse-9', '/admin/login')->status);

        $c = $this->client();
        $this->assertSame(303, $this->login($c, 'root', 'Correct-horse-9', '/admin/login')->status);
        $page = $c->get('/admin');
        $this->assertSame(200, $page->status);
        $this->assertMatchesRegularExpression('#<h1[^>]*>Dashboard</h1>#', $page->body);
    }

    public function testAdminAreaNeedsAdminSession(): void
    {
        $this->makeUser('bob');
        $this->assertSame(303, $this->client()->get('/admin')->status);

        $c = $this->client();
        $this->login($c, 'bob');                      // a normal user session is not an admin session
        $r = $c->get('/admin');
        $this->assertSame(303, $r->status);
        $this->assertStringContainsString('/admin/login/', $r->location);
    }

    public function testLogoutRequiresCsrfAndEndsSession(): void
    {
        $this->makeUser('alice');
        $c = $this->client();
        $this->login($c, 'alice');

        $this->assertSame(403, $c->post('/logout')->status, 'no token, no logout');
        $token = $c->csrf('/forgot');                  // any page with a form carries the session token
        $this->assertSame(303, $c->post('/logout', ['token' => $token])->status);
        $this->assertSame(200, $c->get('/login')->status, 'login form is shown again, not redirected home');
    }

    public function testIpThrottleBlocksEvenCorrectPassword(): void
    {
        $this->makeUser('alice');
        $c = $this->client('10.9.9.9');
        for ($i = 0; $i < 5; $i++) $this->assertSame(401, $this->login($c, 'alice', 'bad-password-1')->status);
        $this->assertSame(429, $this->login($c, 'alice')->status);
        $this->assertSame(200, $this->client('10.9.9.10')->get('/login')->status, 'other IPs are unaffected');
    }

    public function testUsernameLockoutAcrossManyIps(): void
    {
        $this->makeUser('alice');
        for ($i = 1; $i <= 10; $i++) {
            $this->login($this->client("10.20.0.$i"), 'alice', 'bad-password-1');
        }
        $this->assertSame(429, $this->login($this->client('10.20.0.99'), 'alice')->status);
    }

    public function testSuccessfulLoginDoesNotForgetGuessesAgainstOtherAccounts(): void
    {
        $this->makeUser('alice');
        $this->makeUser('boss', 'Correct-horse-9', 1);

        // attacker guesses the admin password 4 times, then signs in as themselves, over and over
        $ip = '10.30.0.1';
        for ($i = 0; $i < 4; $i++) $this->login($this->client($ip), 'boss', 'bad-password-1', '/admin/login');
        $this->assertSame(303, $this->login($this->client($ip), 'alice')->status);

        $this->assertSame(4, (int) DB::getVar("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND username = 'boss'", [$ip]), 'the guesses stay on record');
        $this->assertSame(0, (int) DB::getVar("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND username = 'alice'", [$ip]), 'the owner of the account starts clean');

        // so the 5th and 6th guess still hit the per-IP limit
        $this->login($this->client($ip), 'boss', 'bad-password-1', '/admin/login');
        $this->assertSame(429, $this->login($this->client($ip), 'boss', 'bad-password-1', '/admin/login')->status);
    }

    public function testStrangersCannotLockTheOwnerOutFromTheirUsualIp(): void
    {
        $id = $this->makeUser('boss', 'Correct-horse-9', 1);
        DB::exec("UPDATE users SET last_ip = '10.31.0.1' WHERE id = ?", [$id]);

        for ($i = 1; $i <= 12; $i++) $this->login($this->client("10.31.1.$i"), 'boss', 'bad-password-1', '/admin/login');   // attack from 12 IPs

        $this->assertSame(429, $this->login($this->client('10.31.2.1'), 'boss', 'Correct-horse-9', '/admin/login')->status, 'a new IP waits');
        $this->assertSame(303, $this->login($this->client('10.31.0.1'), 'boss', 'Correct-horse-9', '/admin/login')->status, 'the owner\'s usual IP still works');
    }

    public function testDummyHashHasTheSameCostAsRealHashes(): void
    {
        $m = new \ReflectionMethod(\Pika\Auth\BaseLogin::class, 'dummyHash');
        $dummy = password_get_info($m->invoke(null));
        $real = password_get_info(password_hash('x', PASSWORD_DEFAULT));
        $this->assertSame($real['options']['cost'], $dummy['options']['cost'], 'else the response time reveals which usernames exist');
    }

    public function testRememberMeExpiresOnTheServerAndRotates(): void
    {
        $this->makeUser('alice');
        $c = $this->client();
        $this->login($c, 'alice', 'Correct-horse-9', '/login', ['remember' => '1']);
        $cookie = $c->cookie('remember_token');
        $this->assertNotNull(DB::getVar("SELECT remember_issued FROM users WHERE username = 'alice'"));

        // using the cookie logs in and replaces it: the old value is dead afterwards
        $browser = $this->client();
        $browser->defaultHeaders['Cookie'] = "remember_token=$cookie";
        $this->assertSame(303, $browser->get('/login')->status);
        $rotated = $browser->cookie('remember_token');
        $this->assertNotEmpty($rotated);
        $this->assertNotSame($cookie, $rotated);

        $thief = $this->client();
        $thief->defaultHeaders['Cookie'] = "remember_token=$cookie";
        $this->assertSame(200, $thief->get('/login')->status, 'the replaced token no longer works');

        // a token older than 30 days is refused even though it is still stored
        $fresh = $this->client();
        $fresh->defaultHeaders['Cookie'] = "remember_token=$rotated";
        DB::exec("UPDATE users SET remember_issued = ? WHERE username = 'alice'", [time() - 31 * 86400]);
        $this->assertSame(200, $fresh->get('/login')->status);
    }

    public function testRememberMeIsRevokedOnLogout(): void
    {
        $this->makeUser('alice');
        $c = $this->client();
        $this->assertSame(303, $this->login($c, 'alice', 'Correct-horse-9', '/login', ['remember' => '1'])->status);
        $cookie = $c->cookie('remember_token');
        $this->assertNotEmpty($cookie);
        $this->assertNotSame($cookie, DB::getVar("SELECT remember_token FROM users WHERE username = 'alice'"), 'database keeps only a hash');

        // a fresh browser (own session) holding only the remember cookie is logged in...
        $replay = function () use ($cookie) {
            $other = $this->client();
            $other->defaultHeaders['Cookie'] = "remember_token=$cookie";
            return $other->get('/login')->status;
        };
        $this->assertSame(303, $replay());

        // ...until the user logs out: the stored token is revoked, so the same cookie no longer works
        $token = $c->csrf('/forgot');
        $c->post('/logout', ['token' => $token]);
        $this->assertNull(DB::getVar("SELECT remember_token FROM users WHERE username = 'alice'"));
        $this->assertSame(200, $replay());
    }

    public function testPasswordChangeAndLogoutEverywhereEndSessions(): void
    {
        $id = $this->makeUser('alice');
        $a = $this->client();
        $b = $this->client();
        $this->login($a, 'alice');
        $this->login($b, 'alice');
        $this->assertSame(303, $a->get('/login')->status);

        logout_everywhere($id);
        $this->assertSame(200, $a->get('/login')->status);
        $this->assertSame(200, $b->get('/login')->status);

        $this->login($a, 'alice');
        $this->assertSame(303, $a->get('/login')->status);
        set_user_password($id, 'Another-pass-123');
        $this->assertSame(200, $a->get('/login')->status, 'session dies when the password changes');
    }

    public function testDisabledAccountCannotKeepSession(): void
    {
        $id = $this->makeUser('alice');
        $c = $this->client();
        $this->login($c, 'alice');
        DB::exec("UPDATE users SET status = 0 WHERE id = ?", [$id]);
        $this->assertSame(200, $c->get('/login')->status);
        $this->assertSame(401, $this->login($this->client(), 'alice')->status);
    }
}

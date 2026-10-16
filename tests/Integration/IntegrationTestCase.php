<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pika\DB;
use Tests\Support\HttpClient;
use Tests\Support\TestServer;

/** Base class: skips without a test database, starts a server per class, cleans tables per test. */
abstract class IntegrationTestCase extends TestCase
{
    protected static ?TestServer $server = null;

    /** Environment overrides for this class's server. */
    protected static function serverEnv(): array { return []; }

    public static function setUpBeforeClass(): void
    {
        if (empty($GLOBALS['PIKA_TEST_DB'])) {
            self::markTestSkipped('No MySQL test database available.');
        }
        self::$server = new TestServer(static::serverEnv());
    }

    public static function tearDownAfterClass(): void
    {
        self::$server?->stop();
        self::$server = null;
    }

    protected function setUp(): void
    {
        foreach (['users', 'login_attempts', 'user_logs', 'rate_limits', 'password_resets', 'user_permissions', 'options'] as $t) {
            DB::exec("DELETE FROM `$t`");
        }
    }

    protected function client(string $ip = '10.1.1.1'): HttpClient { return self::$server->client($ip); }

    protected function makeUser(string $username, string $password = 'Correct-horse-9', int $admin = 0, ?string $email = null): int
    {
        return (int) DB::insert(
            "INSERT INTO users (username, email, password, admin, status) VALUES (?, ?, ?, ?, 1)",
            [$username, $email, password_hash($password, PASSWORD_DEFAULT), $admin]
        );
    }

    /** Log in through the real form; returns the response of the POST. */
    protected function login(HttpClient $c, string $username, string $password = 'Correct-horse-9', string $path = '/login', array $extra = []): object
    {
        $token = $c->csrf($path);
        return $c->post($path, ['token' => $token, 'username' => $username, 'password' => $password] + $extra);
    }
}

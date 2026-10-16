<?php
namespace Tests\Integration;

class AdminJobsTest extends IntegrationTestCase
{
    private static string $job;

    protected static function serverEnv(): array
    {
        self::$job = BASEPATH . '/app/jobs/ZzAdminPageTest.php';
        file_put_contents(self::$job, "<?php\nnamespace App\\Jobs;\nclass ZzAdminPageTest extends \\Pika\\Job {\n    public function handle(): void { throw new \\RuntimeException('visible failure'); }\n}\n");
        return [];
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        @unlink(self::$job);
        foreach (glob(BASEPATH . '/content/cache/jobs/App_Jobs_ZzAdminPageTest.*') ?: [] as $f) @unlink($f);
    }

    public function testAdminSeesJobsAndTheirLastResult(): void
    {
        \Pika\JobRunner::forApp()->run('zz-admin-page-test');

        $this->makeUser('boss', 'Correct-horse-9', 1);
        $c = $this->client();
        $this->login($c, 'boss', 'Correct-horse-9', '/admin/login');

        $page = $c->get('/admin/jobs/');
        $this->assertSame(200, $page->status);
        $this->assertStringContainsString('zz-admin-page-test', $page->body);
        $this->assertStringContainsString('visible failure', $page->body);
        $this->assertStringContainsString('badge-danger', $page->body);
    }

    public function testAnonymousAndNormalUsersAreKeptOut(): void
    {
        $this->assertSame(303, $this->client()->get('/admin/jobs/')->status);

        $this->makeUser('plain');
        $c = $this->client();
        $this->login($c, 'plain');
        $this->assertNotSame(200, $c->get('/admin/jobs/')->status);
    }

    public function testNamedRoutesRenderTheSameLinksAsBefore(): void
    {
        $login = $this->client()->get('/login');
        $this->assertStringContainsString('href="http://127.0.0.1:', $login->body);
        $this->assertMatchesRegularExpression('#href="http://127\.0\.0\.1:\d+/forgot/"#', $login->body);
        $this->assertMatchesRegularExpression('#href="http://127\.0\.0\.1:\d+/"[^>]*>&larr; Back to site#', $login->body);

        $this->makeUser('boss2', 'Correct-horse-9', 1);
        $c = $this->client();
        $this->login($c, 'boss2', 'Correct-horse-9', '/admin/login');
        $admin = $c->get('/admin/')->body;
        $this->assertMatchesRegularExpression('#href="http://127\.0\.0\.1:\d+/admin/jobs/"#', $admin);
        $this->assertMatchesRegularExpression('#action="http://127\.0\.0\.1:\d+/admin/logout/"#', $admin);

        // the rendered logout form really works
        $out = $c->post('/admin/logout/', ['token' => $c->csrf('/admin/')]);
        $this->assertSame(303, $out->status);
        $this->assertSame(303, $c->get('/admin/')->status, 'signed out');
    }
}

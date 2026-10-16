<?php
namespace Tests\Integration;

class CronHttpTest extends IntegrationTestCase
{
    private const TOKEN = 'cron-test-token-0123456789abcdef0123456789';
    private static string $job;
    private static string $marker;

    protected static function serverEnv(): array
    {
        self::$marker = sys_get_temp_dir() . '/pika-cron-ran-' . bin2hex(random_bytes(4));
        self::$job = BASEPATH . '/app/jobs/ZzCronTest.php';
        file_put_contents(self::$job, "<?php\nnamespace App\\Jobs;\nclass ZzCronTest extends \\Pika\\Job {\n    public function handle(): void { file_put_contents(" . var_export(self::$marker, true) . ", 'x', FILE_APPEND); }\n}\n");
        return ['CRON_TOKEN' => self::TOKEN];
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        @unlink(self::$job);
        @unlink(self::$marker);
        foreach (glob(BASEPATH . '/content/cache/jobs/App_Jobs_ZzCronTest.*') ?: [] as $f) @unlink($f);
    }

    public function testRunsTheJobWithTheTokenHeader(): void
    {
        $r = $this->client()->get('/api/cron/zz-cron-test', ['X-Cron-Token' => self::TOKEN]);
        $this->assertSame(200, $r->status);
        $this->assertSame('ok', json_decode($r->body, true)['status']);
        $this->assertFileExists(self::$marker);
    }

    public function testRejectsMissingWrongAndQueryTokens(): void
    {
        @unlink(self::$marker);
        $c = $this->client();
        $this->assertSame(404, $c->get('/api/cron/zz-cron-test')->status);
        $this->assertSame(404, $c->get('/api/cron/zz-cron-test', ['X-Cron-Token' => 'wrong'])->status);
        $this->assertSame(404, $c->get('/api/cron/zz-cron-test?token=' . self::TOKEN)->status, 'tokens in the URL are not accepted');
        $this->assertFileDoesNotExist(self::$marker);
    }

    public function testUnknownJobIs404(): void
    {
        $this->assertSame(404, $this->client()->get('/api/cron/nope', ['X-Cron-Token' => self::TOKEN])->status);
        $this->assertSame(404, $this->client()->get('/api/cron/..%2F..%2Fetc', ['X-Cron-Token' => self::TOKEN])->status);
    }
}

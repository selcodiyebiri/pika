<?php
namespace Tests\Integration;

use Pika\DB;
use Pika\JobRunner;
use Tests\Support\WikiFiles;

/** Runs the example jobs from docs/wiki/Cron-Jobs.md exactly as written. */
class CronWikiTest extends IntegrationTestCase
{
    private static WikiFiles $files;

    public static function setUpBeforeClass(): void
    {
        if (empty($GLOBALS['PIKA_TEST_DB'])) self::markTestSkipped('No MySQL test database available.');
        self::$files = new WikiFiles();
        try {
            self::$files->install('Cron-Jobs');
            parent::setUpBeforeClass();
        } catch (\Throwable $e) { self::$files->remove(); throw $e; }
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$files->remove();
        foreach (glob(BASEPATH . '/content/cache/jobs/App_Jobs_Reports_*') ?: [] as $f) @unlink($f);
    }

    public function testTheDailyJobIsFoundAndRuns(): void
    {
        $names = array_column(JobRunner::forApp()->all(), 'name');
        $this->assertContains('reports/daily', $names);
        $this->assertContains('reports/archive', $names);

        // the example mails through MAIL_*; whether that works depends on this machine, but the job must not crash
        $this->assertContains(JobRunner::forApp()->run('reports/daily')['status'], ['ok', 'failed']);
    }

    public function testTheBatchJobContinuesWhereItStopped(): void
    {
        DB::exec('DELETE FROM user_logs');
        DB::exec("DELETE FROM options WHERE option_key = 'archive_last_id'");
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = (int) DB::insert("INSERT INTO user_logs (user_id, action, ip, user_agent, time) VALUES (1, 'x', '1.1.1.1', 'ua', ?)", [time()]);
        }

        $this->assertSame('ok', JobRunner::forApp()->run('reports/archive')['status']);
        $this->assertSame((string) max($ids), (string) DB::getVar("SELECT option_value FROM options WHERE option_key = 'archive_last_id'"));

        $more = (int) DB::insert("INSERT INTO user_logs (user_id, action, ip, user_agent, time) VALUES (1, 'x', '1.1.1.1', 'ua', ?)", [time()]);
        JobRunner::forApp()->run('reports/archive');
        $this->assertSame((string) $more, (string) DB::getVar("SELECT option_value FROM options WHERE option_key = 'archive_last_id'"), 'only the new row was processed');
    }
}

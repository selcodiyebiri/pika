<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pika\JobRunner;
use Tests\Fixtures\Jobs\Ok;

class JobRunnerTest extends TestCase
{
    private string $dir;
    private JobRunner $runner;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/pika-jobs-' . bin2hex(random_bytes(4));
        $this->runner = new JobRunner($this->dir, 'Tests\\Fixtures\\Jobs');
        Ok::$runs = 0;
    }

    protected function tearDown(): void
    {
        foreach (glob("$this->dir/*") ?: [] as $f) @unlink($f);
        @rmdir($this->dir);
    }

    public function testNamesMapToClasses(): void
    {
        $this->assertSame('Tests\\Fixtures\\Jobs\\MailSend', $this->runner->classFor('mail_send'));
        $this->assertSame('Tests\\Fixtures\\Jobs\\Reports\\DailySummary', $this->runner->classFor('reports/daily-summary'));
    }

    /** @dataProvider badNames */
    public function testBadNamesAreRejected(string $name): void
    {
        $this->assertFalse(JobRunner::validName($name));
        $this->expectException(\InvalidArgumentException::class);
        $this->runner->run($name);
    }

    public static function badNames(): array
    {
        return [[''], ['../etc/passwd'], ['Ok'], ['a//b'], ['a\\b'], ['/ok'], ['ok/'], ['1ok'], ["ok\0"]];
    }

    public function testRunsAJobAndRecordsIt(): void
    {
        $r = $this->runner->run('ok');
        $this->assertSame('ok', $r['status']);
        $this->assertSame(1, Ok::$runs);
        $last = $this->runner->lastRun(Ok::class);
        $this->assertSame('ok', $last['status']);
        $this->assertEqualsWithDelta(time(), $last['at'], 5);
    }

    public function testFailureIsReportedNotThrown(): void
    {
        $r = $this->runner->run('boom');
        $this->assertSame('failed', $r['status']);
        $this->assertStringContainsString('exploded', $r['message']);
        $this->assertSame('failed', $this->runner->lastRun('Tests\\Fixtures\\Jobs\\Boom')['status']);
    }

    public function testClassesThatAreNotJobsAreNeverRun(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->runner->run('not-a-job');
    }

    public function testRunningJobIsNotStartedTwice(): void
    {
        $this->runner->run('ok');   // creates the lock file
        $held = fopen("$this->dir/Tests_Fixtures_Jobs_Ok.lock", 'c');
        $this->assertTrue(flock($held, LOCK_EX | LOCK_NB));
        Ok::$runs = 0;

        $this->assertSame('skipped', $this->runner->run('ok')['status']);
        $this->assertSame(0, Ok::$runs);

        flock($held, LOCK_UN);
        $this->assertSame('ok', $this->runner->run('ok')['status']);
        $this->assertSame(1, Ok::$runs);
    }

    public function testAlertsOncePerFailureStreak(): void
    {
        $alerts = [];
        $runner = new JobRunner($this->dir, 'Tests\\Fixtures\\Jobs', null, function ($name, $r) use (&$alerts) { $alerts[] = [$name, $r['failures']]; });

        $runner->run('boom');
        $runner->run('boom');
        $this->assertSame([['boom', 1]], $alerts, 'the second failure in a row sends no new alert');
        $this->assertSame(2, $runner->lastRun('Tests\\Fixtures\\Jobs\\Boom')['failures']);

        $runner->run('ok');
        $this->assertSame([['boom', 1]], $alerts, 'a successful job sends nothing');
        $this->assertSame(0, $runner->lastRun(Ok::class)['failures']);
    }

    public function testAFailingAlertNeverBreaksTheRun(): void
    {
        $runner = new JobRunner($this->dir, 'Tests\\Fixtures\\Jobs', null, function () { throw new \RuntimeException('smtp down'); });
        $this->assertSame('failed', $runner->run('boom')['status']);
    }

    public function testListsJobFilesWithTheirLastRun(): void
    {
        $runner = new JobRunner($this->dir, 'Tests\\Fixtures\\Jobs', BASEPATH . '/tests/Fixtures/Jobs');
        $runner->run('ok');

        $byName = array_column($runner->all(), null, 'name');
        $this->assertSame(['boom', 'not-a-job', 'ok'], array_keys($byName));
        $this->assertSame('ok', $byName['ok']['last']['status']);
        $this->assertNull($byName['boom']['last']);
    }
}

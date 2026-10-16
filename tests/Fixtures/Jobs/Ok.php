<?php
namespace Tests\Fixtures\Jobs;

class Ok extends \Pika\Job
{
    public static int $runs = 0;
    public function handle(): void { self::$runs++; }
}

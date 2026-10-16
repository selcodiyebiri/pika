<?php
namespace Tests\Fixtures\Jobs;

class Boom extends \Pika\Job
{
    public function handle(): void { throw new \RuntimeException('exploded'); }
}

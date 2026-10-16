<?php
namespace Tests\Fixtures\Jobs;

class NotAJob { public function handle(): void { throw new \LogicException('must never run'); } }

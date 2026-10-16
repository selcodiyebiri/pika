<?php
namespace Pika;

/** A scheduled task. Put subclasses in app/jobs/ (php pika make:job name); run them with `php pika job:run name` or over HTTP. */
abstract class Job
{
    /** Do the work. Throw to report a failure; the runner logs it. Long jobs: process a bounded batch per run. */
    abstract public function handle(): void;
}

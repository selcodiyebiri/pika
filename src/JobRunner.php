<?php
namespace Pika;

/** Runs a Job by name ("reports/daily" -> App\Jobs\Reports\Daily) with a per-job lock so runs never overlap. */
class JobRunner
{
    /** @param ?callable $notify called as fn(string $name, array $result) when a job starts failing (not on every failed run) */
    public function __construct(
        private string $stateDir,
        private string $namespace = 'App\\Jobs',
        private ?string $jobsDir = null,
        private $notify = null,
    ) {}

    /** The runner for this application: jobs in app/jobs, state in content/cache/jobs, failure alert to CRON_ALERT_EMAIL. */
    public static function forApp(): self
    {
        return new self(BASEPATH . '/content/cache/jobs', 'App\\Jobs', BASEPATH . '/app/jobs', function (string $name, array $r) {
            $to = (string) pika_env('CRON_ALERT_EMAIL', '');
            if ($to !== '') Mail::send($to, "[Pika] job $name failed", "Job $name failed:\n{$r['message']}\n\nYou get one alert per failure streak; the job keeps running on schedule.");
        });
    }

    public static function validName(string $name): bool
    {
        return (bool) preg_match('#^[a-z][a-z0-9_-]*(/[a-z][a-z0-9_-]*)*$#', $name);
    }

    public function classFor(string $name): string
    {
        $parts = array_map(fn($p) => str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $p))), explode('/', $name));
        return $this->namespace . '\\' . implode('\\', $parts);
    }

    /** @return array{status:string,seconds:float,message:string}  status: ok | failed | skipped (already running) */
    public function run(string $name): array
    {
        $class = self::validName($name) ? $this->classFor($name) : '';
        if ($class === '' || !is_subclass_of($class, Job::class)) {
            throw new \InvalidArgumentException("Unknown job: $name");
        }

        if (!is_dir($this->stateDir)) @mkdir($this->stateDir, 0755, true);
        $key = $this->key($class);
        $lock = fopen("$this->stateDir/$key.lock", 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return ['status' => 'skipped', 'seconds' => 0.0, 'message' => 'previous run is still going'];
        }

        ignore_user_abort(true);
        set_time_limit(0);
        $start = microtime(true);
        try {
            (new $class())->handle();
            $result = ['status' => 'ok', 'message' => ''];
        } catch (\Throwable $e) {
            $result = ['status' => 'failed', 'message' => get_class($e) . ': ' . $e->getMessage()];
        }
        $result['seconds'] = round(microtime(true) - $start, 2);
        $result['failures'] = $result['status'] === 'failed' ? ($this->lastRun($class)['failures'] ?? 0) + 1 : 0;   // consecutive failed runs

        log_message($result['status'] === 'ok' ? 'info' : 'error', "job $name {$result['status']} in {$result['seconds']}s " . $result['message']);
        @file_put_contents("$this->stateDir/$key.json", json_encode($result + ['at' => time()]), LOCK_EX);
        flock($lock, LOCK_UN);
        fclose($lock);

        if ($result['failures'] === 1 && $this->notify) {   // first failure of a streak only: no mail every minute
            try { ($this->notify)($name, $result); } catch (\Throwable $e) { log_message('error', 'job alert failed: ' . $e->getMessage()); }
        }
        return $result;
    }

    /** Last finished run of a job class, or null. */
    public function lastRun(string $class): ?array
    {
        $file = "$this->stateDir/" . $this->key($class) . '.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        return is_array($data) ? $data : null;
    }

    /**
     * Every job file with its last run, for the admin page and `doctor`.
     * @return list<array{name:string,class:string,last:?array}>
     */
    public function all(): array
    {
        if ($this->jobsDir === null || !is_dir($this->jobsDir)) return [];

        $jobs = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->jobsDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $f) {
            if ($f->getExtension() !== 'php') continue;
            $rel = substr($f->getPathname(), strlen($this->jobsDir) + 1, -4);
            $class = $this->namespace . '\\' . str_replace('/', '\\', $rel);
            $name = implode('/', array_map(fn($p) => strtolower(preg_replace('/(?<!^)(?=[A-Z])/', '-', $p)), explode('/', $rel)));
            $jobs[] = ['name' => $name, 'class' => $class, 'last' => $this->lastRun($class)];
        }
        usort($jobs, fn($a, $b) => $a['name'] <=> $b['name']);
        return $jobs;
    }

    private function key(string $class): string { return str_replace('\\', '_', $class); }
}

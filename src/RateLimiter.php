<?php
namespace Pika;

/** Fixed-window rate limiter stored in the rate_limits table (one atomic upsert per hit). */
class RateLimiter
{
    /** @return array{allowed: bool, remaining: int, retry_after: int} */
    public static function hit(string $key, int $limit, int $window): array
    {
        $bucket = hash('sha256', $key);
        $now = time();
        $cutoff = $now - $window;

        DB::exec(
            "INSERT INTO rate_limits (bucket, hits, window_start) VALUES (?, 1, ?)
             ON DUPLICATE KEY UPDATE
               hits = IF(window_start <= ?, 1, hits + 1),
               window_start = IF(window_start <= ?, ?, window_start)",
            [$bucket, $now, $cutoff, $cutoff, $now]
        );
        $row = DB::getRow("SELECT hits, window_start FROM rate_limits WHERE bucket = ?", [$bucket]);

        if (random_int(1, 100) === 1) { // opportunistic cleanup
            DB::exec("DELETE FROM rate_limits WHERE window_start < ?", [$now - 86400]);
        }

        return [
            'allowed'     => (int) $row->hits <= $limit,
            'remaining'   => max(0, $limit - (int) $row->hits),
            'retry_after' => max(1, (int) $row->window_start + $window - $now),
        ];
    }
}

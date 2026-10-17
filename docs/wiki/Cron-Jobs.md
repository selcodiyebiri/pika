# Cron Jobs

[Home](Home) | **English** | [Türkçe](Cron-Jobs-TR)

A **cron job** is a task that runs by itself on a schedule: send a daily report, delete old rows, fetch data every 5 minutes. In Pika a job is a small PHP class in `app/jobs/`. The same class can be started by the server's cron (command line, best for long jobs) or by a URL (for hosting without a command-line cron). The files on this page are run by the project's test suite.

## 1. Create a job
```bash
php pika make:job reports/daily
```
This creates `app/jobs/Reports/Daily.php`. Names use lowercase letters, digits, `-` and `_`; `/` makes a sub-folder; `mail-send` becomes the class `MailSend`, `reports/daily` becomes `App\Jobs\Reports\Daily`. Put the work in `handle()`. If something goes wrong, throw an exception: the run is recorded as failed.

**File:** `app/jobs/Reports/Daily.php`
```php
<?php
namespace App\Jobs\Reports;

use Pika\DB;
use Pika\Job;
use Pika\Mail;

class Daily extends Job
{
    public function handle(): void
    {
        $users = (int) DB::getVar("SELECT COUNT(*) FROM users");

        if (!Mail::send('you@example.com', 'Daily report', "Users: $users")) {
            throw new \RuntimeException('The report could not be mailed');
        }
    }
}
```
A job runs without a browser: there is no signed-in user and no `$_POST`. You can use everything else (`DB`, `Mail`, models, `log_message()`, `get_option()`).

## 2. A job that works through a big pile in small steps
A run that takes too long may be cut off by the host. Process a limited batch per run and remember where you stopped; the next run continues from there. This example reads the audit log 500 rows at a time and keeps its position in the `options` table.

**File:** `app/jobs/Reports/Archive.php`
```php
<?php
namespace App\Jobs\Reports;

use Pika\DB;
use Pika\Job;

class Archive extends Job
{
    private const BATCH = 500;

    public function handle(): void
    {
        $lastId = (int) get_option('archive_last_id');   // 0 on the very first run
        $rows = DB::table('user_logs')->where('id', '>', $lastId)->orderBy('id')->limit(self::BATCH)->get();

        foreach ($rows as $row) {
            // process one row here: copy it, send it somewhere, calculate with it
            $lastId = (int) $row->id;
        }

        update_option('archive_last_id', (string) $lastId);   // the next run continues here
        log_message('info', 'archive: ' . count($rows) . " rows, now at id $lastId");
    }
}
```

## 3. Try it by hand
```bash
php pika job:run reports/daily
```
It prints `reports/daily: ok (0.01s)`. `failed` shows the error; `skipped` means the previous run is still going. `php pika doctor` lists your jobs and when each last ran. Results are also written to `content/logs`.

## 4. Schedule it with cron
A schedule has five fields: **minute, hour, day of month, month, day of week**. `*` means "every", `*/5` means "every 5".

| Schedule | Meaning |
|---|---|
| `* * * * *` | every minute |
| `*/5 * * * *` | every 5 minutes |
| `0 * * * *` | every hour, on the hour |
| `30 3 * * *` | every day at 03:30 |
| `0 9 * * 1` | every Monday at 09:00 |
| `0 0 1 * *` | on the 1st of every month at midnight |

### On the command line (preferred)
One cron line per job. Cron starts each line as its own process, so jobs run in parallel and a slow job never blocks the others. The command line has no time limit. In cPanel open **Cron Jobs**, choose the schedule and enter the command:
```bash
30 3 * * * /usr/local/bin/php -q /home/user/public_html/pika/pika job:run reports/daily
*/5 * * * * /usr/local/bin/php -q /home/user/public_html/pika/pika job:run reports/archive
```
* `/usr/local/bin/php` is the PHP program, `/home/user/public_html/pika/pika` is the `pika` file in your project folder (folder name, then the file; it needs no execute permission), `job:run reports/daily` is the job.
* Use a PHP 8.2+ binary. Hosts often have several; cPanel's are in paths such as `/opt/cpanel/ea-php82/root/usr/bin/php`. `php pika doctor` prints the PHP path and version it runs with, the project folder and the jobs, so you can copy the right values.
* To see errors, append `>> /home/user/cron.log 2>&1` to the line.
* A job never overlaps itself: if the previous run is still going, the new one is skipped (exit code 0). Failures exit with code 1.

### Over HTTP (no command-line cron)
Set `CRON_TOKEN` in `.env` to a random secret of 32+ characters (`openssl rand -hex 32`), then call the job's URL with the token in a header:
```bash
30 3 * * * wget -q -O /dev/null --header="X-Cron-Token: YOUR_TOKEN" https://your.domain/api/cron/reports/daily
```
Try it first from a terminal:
```bash
curl -H "X-Cron-Token: YOUR_TOKEN" http://127.0.0.1:8000/api/cron/reports/daily
```
The answer is JSON such as `{"status":"ok","seconds":0.01}`. A missing or wrong token, an unknown job and an empty `CRON_TOKEN` all give `404` on purpose. A token in the URL (`?token=`) is ignored because URLs end up in server logs. PHP and web server time limits apply and so does the per-IP `RateLimit` (`RATE_LIMIT`), so keep short jobs here.

## 5. Watch your jobs
* The admin area has a **Jobs** page (`/admin/jobs/`): last run, result, duration and failures in a row.
* Set `CRON_ALERT_EMAIL` in `.env` to get one e-mail when a job starts failing (not one per minute). It needs working `MAIL_*` settings.

## 6. When it does not run
| Symptom | Check |
|---|---|
| Nothing happens | Run the exact command in a terminal. Add `>> /home/user/cron.log 2>&1` and read the file. |
| "Could not open input file" | The path to the `pika` file is wrong (is it `public_html/pika/pika`?). |
| A PHP version error | The cron uses an older PHP. Use the full path of a PHP 8.2+ binary. |
| `Unknown job` | The name does not match a file in `app/jobs/` (`reports/daily` is `Reports/Daily.php`). |
| Always `skipped` | A previous run hangs or takes longer than the interval. Look at `content/logs`. |
| HTTP answers `404` | `CRON_TOKEN` is empty or shorter than 32 characters, the header is missing, or the name is wrong. |
| Wrong time | Schedules use the server's clock; the app's time zone is `APP_TIMEZONE`. |

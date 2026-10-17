# Command Line and Tests

[Home](Home) | **English** | [Türkçe](Command-Line-and-Tests-TR)

## `php pika`
| Command | What it does |
|---|---|
| `install` | first-run wizard: `.env`, database, migrations, admin account |
| `serve [port]` | development server (default port 8000) |
| `migrate` / `migrate:rollback` | run pending migrations / undo the last batch |
| `make:migration <name>` | create `NNN_name.sql` and `.down.sql` |
| `make:controller <Name> [--admin]` | create a controller |
| `make:job <name>` | create a job in `app/jobs/` |
| `job:run <name>` | run a job now |
| `make:admin [user] [email]` | create an admin (password prompt, or `PIKA_ADMIN_PASSWORD`) |
| `key:generate [--force]` | write a random `PIKA_LICENSE` to `.env` |
| `log:clear [days]` | delete old logs and expired rows (default 14 days) |
| `scan:secrets` | look for committed tokens, keys and passwords |
| `doctor` | check configuration, database, PHP, jobs |

`scan:secrets` skips a line that contains `pika-secret-scan: ignore` (for examples and test data).

## Tests
```bash
vendor/bin/phpunit                       # unit + integration
vendor/bin/phpunit --testsuite unit      # no database needed
```
Integration tests start the real app with PHP's built-in server against the database `pika_framework_test` (created and migrated automatically; a database whose name does not end in `_test` is never touched). Without a reachable MySQL they are skipped. CI runs both suites on PHP 8.2, 8.3 and 8.4 with a MySQL service, plus `composer audit` and the secret scan.

## Contributing a fix
Write a test that fails first, make it pass, run the whole suite and `php pika scan:secrets`.

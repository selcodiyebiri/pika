<?php
namespace Pika;

use PDO;

/** Runs database/migrations/NNN_name.sql (and NNN_name.down.sql) on a PDO connection, tracking batches in `migrations`. */
class Migrator
{
    public function __construct(private PDO $pdo, private string $dir)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /** @return string[] names of the migrations that were run */
    public function migrate(): array
    {
        $this->ensureTable();
        $done = $this->pdo->query("SELECT name FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
        $batch = (int) $this->pdo->query("SELECT COALESCE(MAX(batch), 0) FROM migrations")->fetchColumn() + 1;

        $ran = [];
        foreach ($this->files() as $file) {
            $name = basename($file);
            if (in_array($name, $done, true)) continue;
            $this->runSql($file);
            $this->pdo->prepare("INSERT INTO migrations (name, batch, ran_at) VALUES (?, ?, ?)")->execute([$name, $batch, time()]);
            $ran[] = $name;
        }
        return $ran;
    }

    /** @return string[] names of the migrations that were undone (the last batch) */
    public function rollback(): array
    {
        $this->ensureTable();
        $batch = (int) $this->pdo->query("SELECT COALESCE(MAX(batch), 0) FROM migrations")->fetchColumn();
        $stmt = $this->pdo->prepare("SELECT name FROM migrations WHERE batch = ? ORDER BY name DESC");
        $stmt->execute([$batch]);
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // check every down file first so a missing one cannot leave the batch half undone
        foreach ($names as $name) {
            if (!is_file($this->downFile($name))) throw new \RuntimeException("Missing down file for $name.");
        }
        foreach ($names as $name) {
            $this->runSql($this->downFile($name));
            $this->pdo->prepare("DELETE FROM migrations WHERE name = ?")->execute([$name]);
        }
        return $names;
    }

    private function downFile(string $name): string
    {
        return $this->dir . '/' . substr($name, 0, -4) . '.down.sql';
    }

    private function files(): array
    {
        $files = array_filter(glob($this->dir . '/*.sql'), fn($f) => !str_ends_with($f, '.down.sql'));
        sort($files);
        return $files;
    }

    private function runSql(string $file): void
    {
        foreach (preg_split('/;\s*(\r?\n|$)/', file_get_contents($file)) as $sql) {
            if (trim($sql) !== '') $this->pdo->exec($sql);
        }
    }

    private function ensureTable(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(191) PRIMARY KEY, batch INT UNSIGNED NOT NULL DEFAULT 1, ran_at INT UNSIGNED NOT NULL)");
        if (!$this->pdo->query("SHOW COLUMNS FROM migrations LIKE 'batch'")->fetch()) {   // table created by an older version
            $this->pdo->exec("ALTER TABLE migrations ADD COLUMN batch INT UNSIGNED NOT NULL DEFAULT 1 AFTER name");
        }
    }
}

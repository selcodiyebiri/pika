<?php
namespace Tests\Support;

/**
 * The wiki tutorials show complete files as "**File:** `path`" followed by a code block. Tests write those files into the
 * project, run the real app against them and remove them again, so the documentation cannot drift from the code.
 */
class WikiFiles
{
    private array $created = [];
    private array $dirs = [];

    /** @return array<string,string> path => content, in page order */
    public static function extract(string $page): array
    {
        $text = (string) file_get_contents(BASEPATH . "/docs/wiki/$page.md");
        preg_match_all('/\*\*(?:File|Dosya):\*\* `([^`]+)`\s*\n+```\w*\n(.*?)\n```/s', $text, $m, PREG_SET_ORDER);
        $files = [];
        foreach ($m as $block) $files[$block[1]] = $block[2] . "\n";
        return $files;
    }

    /** Write every block except database migrations (those are returned, to be executed by the test). */
    public function install(string $page): array
    {
        $migrations = [];
        foreach (self::extract($page) as $path => $content) {
            if (str_starts_with($path, 'database/migrations/')) { $migrations[$path] = $content; continue; }

            $file = BASEPATH . '/' . $path;
            if (file_exists($file)) throw new \RuntimeException("Refusing to overwrite $path");
            for ($dir = dirname($file); !is_dir($dir); $dir = dirname($dir)) $this->dirs[] = $dir;
            @mkdir(dirname($file), 0755, true);
            file_put_contents($file, $content);
            $this->created[] = $file;
        }
        return $migrations;
    }

    public function remove(): void
    {
        foreach ($this->created as $file) @unlink($file);
        usort($this->dirs, fn($a, $b) => strlen($b) <=> strlen($a));   // deepest first
        foreach (array_unique($this->dirs) as $dir) @rmdir($dir);
        $this->created = $this->dirs = [];
    }
}

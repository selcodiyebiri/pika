<?php
namespace Tests\Support;

/**
 * A throw-away copy of the project (vendor/ is linked) in a temp folder, so installer tests write their
 * .env, lock and token files there and never touch the real project.
 */
class Skeleton
{
    public string $root;

    public function __construct()
    {
        $this->root = sys_get_temp_dir() . '/pika-skeleton-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0755, true);
        foreach (['includes', 'src', 'app', 'routes', 'database', 'public', 'content/themes'] as $dir) {
            self::copy(BASEPATH . "/$dir", "$this->root/$dir");
        }
        copy(BASEPATH . '/pika', "$this->root/pika");
        foreach (['content/cache', 'content/logs', 'public/uploads'] as $dir) {
            @mkdir("$this->root/$dir", 0755, true);
        }
        symlink(BASEPATH . '/vendor', "$this->root/vendor");
    }

    public function path(string $rel = ''): string { return $this->root . ($rel === '' ? '' : "/$rel"); }

    public function writeToken(string $token): void { file_put_contents($this->path('content/install.token'), $token); }

    public function destroy(): void
    {
        @chmod($this->root, 0755);
        if (is_link("$this->root/vendor")) unlink("$this->root/vendor");
        self::remove($this->root);
    }

    private static function copy(string $from, string $to): void
    {
        if (is_file($from)) { @mkdir(dirname($to), 0755, true); copy($from, $to); return; }
        @mkdir($to, 0755, true);
        foreach (scandir($from) as $f) {
            if ($f !== '.' && $f !== '..') self::copy("$from/$f", "$to/$f");
        }
    }

    private static function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) { @unlink($path); return; }
        foreach (@scandir($path) ?: [] as $f) {
            if ($f !== '.' && $f !== '..') self::remove("$path/$f");
        }
        @rmdir($path);
    }
}

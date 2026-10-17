<?php
use PHPUnit\Framework\TestCase;
use Tests\Support\WikiFiles;

/** The wiki sources in docs/wiki: English and Turkish pages stay in step, links resolve, commands exist. */
class WikiDocsTest extends TestCase
{
    private const DIR = BASEPATH . '/docs/wiki';

    private static function pages(): array
    {
        $en = [];
        foreach (glob(self::DIR . '/*.md') as $f) {
            $name = basename($f, '.md');
            if ($name[0] !== '_' && !str_ends_with($name, '-TR')) $en[] = $name;
        }
        return $en;
    }

    public static function pairs(): array
    {
        return array_combine(self::pages(), array_map(fn($p) => [$p, $p . '-TR'], self::pages()));
    }

    private function read(string $page): string { return (string) file_get_contents(self::DIR . "/$page.md"); }

    /** @dataProvider pairs */
    public function testTurkishPageMirrorsEnglish(string $en, string $tr): void
    {
        $this->assertFileExists(self::DIR . "/$tr.md");
        $a = $this->read($en); $b = $this->read($tr);

        $this->assertSame(preg_match_all('/^## /m', $a), preg_match_all('/^## /m', $b), 'same number of sections');
        $this->assertSame(preg_match_all('/^```/m', $a), preg_match_all('/^```/m', $b), 'same number of code fences');
        $this->assertSame(substr_count($a, "\n|"), substr_count($b, "\n|"), 'same number of table rows');

        $tokens = function (string $t): array {
            preg_match_all('/`([A-Z][A-Z0-9_]{2,}(?:[\/*][A-Z_*]+)*)`/', $t, $keys);
            preg_match_all('/php pika [a-z:]+/', $t, $cmds);
            preg_match_all('/`((?:public|content|includes|src|app|database|routes)\/[A-Za-z0-9_.\/-]*)`/', $t, $paths);
            $all = array_merge($keys[1], $cmds[0], $paths[1]);
            sort($all);
            return array_values(array_unique($all));
        };
        $this->assertSame($tokens($a), $tokens($b), 'same settings, commands and paths');

        // complete files are shown in both languages and must be byte-identical (the tests run the English ones)
        $this->assertSame(WikiFiles::extract($en), WikiFiles::extract($tr), 'same files, same code');
    }

    public function testAllPagesAreInTheSidebarAndLinksResolve(): void
    {
        $sidebar = $this->read('_Sidebar');
        foreach (self::pages() as $page) {
            $this->assertStringContainsString("]($page)", $sidebar, "$page is in the sidebar");
            $this->assertStringContainsString("]($page-TR)", $sidebar, "$page-TR is in the sidebar");
        }
        foreach (array_merge(glob(self::DIR . '/*.md')) as $file) {
            preg_match_all('/\]\(((?!https?:|#)[^)\s]+)\)/', (string) file_get_contents($file), $m);
            foreach ($m[1] as $target) {
                $this->assertFileExists(self::DIR . "/$target.md", basename($file) . " links to $target");
            }
        }
    }

    public function testEveryDocumentedCommandExists(): void
    {
        $known = [];
        foreach (self::pages() as $page) {
            preg_match_all('/php pika ([a-z:]+)/', $this->read($page), $m);
            $known = array_merge($known, $m[1]);
        }
        $this->assertNotEmpty($known);
        foreach (array_unique($known) as $cmd) {
            $method = 'cmd' . str_replace(' ', '', ucwords(str_replace([':', '-'], ' ', $cmd)));
            $this->assertTrue(method_exists(Pika\Console\Commands::class, $method), "php pika $cmd is documented but does not exist");
        }
    }
}

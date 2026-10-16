<?php
use PHPUnit\Framework\TestCase;

/** Keeps the Turkish documents in step with the English ones (structure, config keys, commands, links). */
class DocsTest extends TestCase
{
    public static function pairs(): array
    {
        return [
            'README'   => ['README.md', 'README.tr.md'],
            'SECURITY' => ['SECURITY.md', 'SECURITY.tr.md'],
        ];
    }

    private function read(string $file): string { return (string) file_get_contents(BASEPATH . '/' . $file); }

    /** @dataProvider pairs */
    public function testSameStructure(string $en, string $tr): void
    {
        $a = $this->read($en); $b = $this->read($tr);

        $this->assertSame(preg_match_all('/^## /m', $a), preg_match_all('/^## /m', $b), 'same number of sections');
        $this->assertSame(preg_match_all('/^```/m', $a), preg_match_all('/^```/m', $b), 'same number of code fences');
        $this->assertSame(substr_count($a, "\n|"), substr_count($b, "\n|"), 'same table rows');
    }

    /** @dataProvider pairs */
    public function testSameConfigKeysCommandsAndPaths(string $en, string $tr): void
    {
        $tokens = function (string $text): array {
            preg_match_all('/`([A-Z][A-Z0-9_]{2,}(?:[\/*][A-Z_*]+)*)`/', $text, $keys);                 // `APP_ENV`, `MYSQL_HOST/PORT/...`
            preg_match_all('/php pika [a-z:]+/', $text, $commands);                                        // php pika migrate
            preg_match_all('/`((?:public|content|includes|src|app|database|vendor)\/[A-Za-z0-9_.\/-]*)`/', $text, $paths); // `public/install.php`
            $all = array_merge($keys[1], $commands[0], $paths[1]);
            sort($all);
            return array_values(array_unique($all));
        };

        $this->assertSame($tokens($this->read($en)), $tokens($this->read($tr)), "$tr mentions the same keys, commands and paths as $en");
    }

    /** @dataProvider pairs */
    public function testLanguageSwitchAndLocalLinks(string $en, string $tr): void
    {
        $this->assertStringContainsString("[Türkçe]($tr)", $this->read($en));
        $this->assertStringContainsString("[English]($en)", $this->read($tr));

        foreach ([$en, $tr] as $file) {
            preg_match_all('/\]\(((?!https?:|\.\.\/|#)[^)\s]+)\)/', $this->read($file), $m);
            foreach ($m[1] as $target) {
                $this->assertFileExists(BASEPATH . '/' . $target, "$file links to $target");
            }
        }
    }

    public function testTurkishReadmePointsToTheTurkishSecurityPolicy(): void
    {
        $this->assertStringContainsString('(SECURITY.tr.md)', $this->read('README.tr.md'));
        $this->assertStringContainsString('(SECURITY.md)', $this->read('README.md'));
    }
}

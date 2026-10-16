<?php
use PHPUnit\Framework\TestCase;
use Pika\Icons;

class IconsTest extends TestCase
{
    public function testOutlineAndSolidMarkup(): void
    {
        $outline = Icons::svg('shield');
        $this->assertStringContainsString('stroke-width="1.5"', $outline);
        $this->assertStringContainsString('stroke="currentColor"', $outline);
        $this->assertStringContainsString('aria-hidden="true"', $outline);

        $solid = Icons::svg('bolt', 'icon icon-lg');
        $this->assertStringContainsString('fill="currentColor"', $solid);
        $this->assertStringContainsString('class="icon icon-lg"', $solid);
    }

    public function testUnknownIconAndClassAreSafe(): void
    {
        $this->assertSame('', Icons::svg('does-not-exist'));
        $this->assertStringNotContainsString('"><script>', Icons::svg('check', '"><script>alert(1)</script>'));
    }

    public function testEveryIconIsPlainSvgPaths(): void
    {
        foreach (Icons::names() as $name) {
            $svg = Icons::svg($name);
            $this->assertNotSame('', $svg, $name);
            $this->assertSame(1, preg_match('#^<svg [^>]+>(<path [^>]+/>)+</svg>$#', $svg), "$name is only <svg> and <path>");
            $this->assertStringNotContainsString('<script', $svg);
        }
    }

    public function testEveryIconUsedInTemplatesExists(): void
    {
        $files = array_merge(
            glob(BASEPATH . '/content/themes/*/*.blade.php'), glob(BASEPATH . '/content/themes/*/*/*.blade.php'),
            [BASEPATH . '/public/install.php', BASEPATH . '/includes/error_pages/layout.php']
        );
        $used = [];
        foreach ($files as $f) {
            preg_match_all("/icon\\('([a-z-]+)'/", file_get_contents($f), $m);
            foreach ($m[1] as $n) $used[$n][] = basename($f);
        }
        $this->assertNotEmpty($used);
        foreach ($used as $name => $where) {
            $this->assertContains($name, Icons::names(), "icon('$name') used in " . implode(', ', array_unique($where)) . ' does not exist');
        }
    }

    public function testIconHelperDelegates(): void
    {
        $this->assertSame(Icons::svg('check', 'x'), icon('check', 'x'));
    }

    public function testThirdPartyNoticeShipsWithTheIcons(): void
    {
        $notice = file_get_contents(BASEPATH . '/THIRD_PARTY_NOTICES.md');
        $this->assertStringContainsString('Heroicons', $notice);
        $this->assertStringContainsString('Tailwind Labs', $notice);
    }
}

<?php
namespace Tests\Integration;

use Tests\Support\WikiFiles;

/** Builds the "shop" theme from docs/wiki/Blade-Cookbook.md exactly as written and renders it. */
class BladeCookbookTest extends IntegrationTestCase
{
    private static WikiFiles $files;

    public static function setUpBeforeClass(): void
    {
        if (empty($GLOBALS['PIKA_TEST_DB'])) self::markTestSkipped('No MySQL test database available.');
        self::$files = new WikiFiles();
        try {
            self::$files->install('Blade-Cookbook');
            parent::setUpBeforeClass();
        } catch (\Throwable $e) { self::$files->remove(); throw $e; }
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$files->remove();
    }

    public function testThePageRendersWithEveryTagDoingItsJob(): void
    {
        $r = $this->client()->get('/shop/');
        $this->assertSame(200, $r->status);
        $b = $r->body;

        $this->assertStringContainsString('<title>Products · Shop</title>', $b, '@section title and @yield in the base layout');
        $this->assertStringContainsString('2 products', $b, '@section with a value');
        $this->assertStringContainsString('1. Notebook &lt;A5&gt;', $b, '{{ }} escapes, $loop->iteration');
        $this->assertStringContainsString('2. Pen', $b);
        $this->assertSame(1, substr_count($b, 'Sold out'), '@if inside the partial');
        $this->assertStringContainsString('Notebook &lt;A5&gt;, Pen', preg_replace('/\s+/', ' ', $b), '$loop->last');
        $this->assertStringContainsString('No sale right now.', $b, '@elseif');
        $this->assertStringContainsString('Sign in to see your prices.', $b, '@unless');
        $this->assertStringContainsString('Everything together: 5.75', $b, '@php');
        $this->assertStringNotContainsString('A Blade comment', $b);
        $this->assertStringContainsString('{{ printed as it is, for JavaScript templates }}', $b, '@verbatim');
        $this->assertMatchesRegularExpression('#href="http://127\.0\.0\.1:\d+/shop/"#', $b, 'route()');
    }

    public function testScriptGetsTheNonceAndSafeJson(): void
    {
        $r = $this->client()->get('/shop/');
        preg_match("/script-src 'self' 'nonce-([^']+)'/", $r->headers['content-security-policy'], $csp);
        $this->assertStringContainsString('<script nonce="' . $csp[1] . '">', $r->body, '@push + csp_nonce()');
        // json_encode writes < as backslash-u003C ("\x5C" is the backslash)
        $this->assertStringContainsString("\"name\":\"Notebook \x5Cu003CA5\x5Cu003E\"", $r->body, '@json escapes < and >');
        $this->assertStringNotContainsString('"Notebook <A5>"', $r->body);
    }
}

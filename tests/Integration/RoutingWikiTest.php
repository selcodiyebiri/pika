<?php
namespace Tests\Integration;

use Tests\Support\WikiFiles;

/** Runs the route examples from docs/wiki/Routing-and-Controllers.md exactly as written. */
class RoutingWikiTest extends IntegrationTestCase
{
    private static WikiFiles $files;

    public static function setUpBeforeClass(): void
    {
        if (empty($GLOBALS['PIKA_TEST_DB'])) self::markTestSkipped('No MySQL test database available.');
        self::$files = new WikiFiles();
        try {
            self::$files->install('Routing-and-Controllers');
            parent::setUpBeforeClass();
        } catch (\Throwable $e) { self::$files->remove(); throw $e; }
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$files->remove();
    }

    public function testTheDocumentedUrlsAnswerAsDescribed(): void
    {
        $c = $this->client();
        $this->assertSame('{"status":true}', $c->get('/demo/ping/')->body);
        $this->assertSame('Hello Ayse', $c->get('/demo/hello/Ayse/')->body);
        $this->assertSame('Post 7, comment 3', $c->get('/demo/post/7/comment/3/')->body);
        $this->assertSame('File reports/2026/summary', $c->get('/demo/files/reports/2026/summary')->body);
        $this->assertStringContainsString('"time":', $c->get('/demo/api/time/')->body);
        $this->assertSame('Show the form', $c->get('/demo/form/')->body);
    }

    public function testPlaceholdersAreEscapedAndPostsNeedTheToken(): void
    {
        $c = $this->client();
        $this->assertStringNotContainsString('<b>', $c->get('/demo/hello/' . rawurlencode('<b>x</b>') . '/')->body);
        $this->assertSame(403, $c->post('/demo/form/')->status, 'no CSRF token');
        $this->assertSame('Saved', $c->post('/demo/form/', ['token' => $c->csrf('/login')])->body);
    }
}

<?php
use PHPUnit\Framework\TestCase;

class ErrorHandlerTest extends TestCase
{
    public function testKnownRouterHeadNoticeIsSwallowed(): void
    {
        $this->assertTrue(errorHandling(8, 'ob_end_clean(): Failed to delete buffer. No buffer to delete', '/x/vendor/izniburak/router/src/Router.php', 296));
    }

    public function testOtherNoticesAreStillLogged(): void
    {
        $log = BASEPATH . '/content/logs/pika-' . date('Y-m-d') . '.log';
        $before = is_file($log) ? filesize($log) : 0;
        errorHandling(2, 'Undefined variable $x', '/app/controllers/Home.php', 10);
        clearstatcache();
        $this->assertGreaterThan($before, filesize($log));
        $this->assertStringContainsString('Undefined variable $x', file_get_contents($log));
    }
}

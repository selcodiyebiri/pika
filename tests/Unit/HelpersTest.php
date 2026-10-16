<?php
use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    public function testSeoUrlTransliteratesTurkish(): void
    {
        $this->assertSame('guzel-cay-ve-seker', generateSeoURL('Güzel Çay ve  Şeker!'));
    }

    public function testIpSimilarity(): void
    {
        $this->assertTrue(is_ip_similar('10.20.1.1', '10.20.99.5'));
        $this->assertFalse(is_ip_similar('10.20.1.1', '10.21.1.1'));
        $this->assertTrue(is_ip_similar('2001:db8:1:2::1', '2001:db8:1:2::ffff'));
        $this->assertFalse(is_ip_similar('10.20.1.1', '2001:db8::1'));
        $this->assertFalse(is_ip_similar('garbage', '10.20.1.1'));
    }

    public function testForwardedForIgnoredFromUntrustedClient(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';
        $this->assertSame('203.0.113.9', getIP());
    }

    public function testForwardedForUsedFromTrustedProxy(): void
    {
        $_ENV['TRUSTED_PROXIES'] = '10.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4, 10.0.0.1';
        $this->assertSame('1.2.3.4', getIP());
        unset($_ENV['TRUSTED_PROXIES'], $_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    public function testBasePathFromFrontControllerLocation(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $this->assertSame('', base_path());
        $_SERVER['SCRIPT_NAME'] = '/pika/public/index.php';   // sub-folder install behind the root .htaccess rewrite
        $this->assertSame('/pika', base_path());
        $_SERVER['HTTP_HOST'] = 'localhost:8888';
        $this->assertSame('http://localhost:8888/pika', currentSiteUrl());
        $this->assertSame('http://localhost:8888/x?y=1', (function () { $_SERVER['REQUEST_URI'] = '/x?y=1'; return currentUrl(); })());
    }

    public function testSiteUrlRejectsBadHostHeader(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['HTTP_HOST'] = 'evil.com/<script>';
        $this->assertSame('http://localhost', currentSiteUrl());
        $_SERVER['HTTP_HOST'] = 'example.com';
        $this->assertSame('http://example.com', currentSiteUrl());
    }

    public function testUuidV4Shape(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', uuidv4());
    }

    public function testSafeRedirectOnlyAllowsSameSite(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $this->assertSame('http://example.com/dashboard', safe_redirect_url('/dashboard'));
        $this->assertSame('http://example.com/a', safe_redirect_url('http://example.com/a'));
        foreach (['https://evil.com/x', '//evil.com/x', '/\\evil.com', 'javascript:alert(1)', 'http://example.com.evil.com/'] as $bad) {
            $this->assertSame('http://example.com/', safe_redirect_url($bad), $bad);
        }
        $this->assertStringNotContainsString("\n", safe_redirect_url("/ok\r\nSet-Cookie: x=1"));
    }

    public function testEscapeHelper(): void
    {
        $this->assertSame('&lt;b&gt;&quot;x&quot; &amp; &#039;y&#039;', esc('<b>"x" & \'y\''));
    }

    public function testPasswordPolicy(): void
    {
        $this->assertSame([], password_policy_errors('Correct-horse-9', 'alice'));
        $this->assertNotEmpty(password_policy_errors('short1'));
        $this->assertNotEmpty(password_policy_errors('aaaaaaaaaaaa'));
        $this->assertNotEmpty(password_policy_errors('xx-alice-password', 'alice'));
        $this->assertNotEmpty(password_policy_errors(str_repeat('ab12', 20)), 'over 72 bytes (bcrypt truncates)');
    }

    public function testFlashShowsOnNextRequestOnly(): void
    {
        $_SESSION = [];
        flash('status', 'saved');
        $this->assertNull(flash_get('status'));
        flash_rotate();
        $this->assertSame('saved', flash_get('status'));
        flash_rotate();
        $this->assertNull(flash_get('status'));

        flash_now('error', 'now');
        $this->assertSame('now', flash_get('error'));
    }

    public function testOldInputSkipsSensitiveFields(): void
    {
        $_SESSION = []; $_POST = [];
        keep_old(['username' => 'alice', 'password' => 'secret-value-1', 'csrf_token' => 'abc', 'bio' => ['x']]);
        flash_rotate();
        $this->assertSame('alice', old('username'));
        $this->assertSame('', old('password'));
        $this->assertSame('', old('csrf_token'));
        $this->assertSame('fallback', old('missing', 'fallback'));

        $_SESSION = []; $_POST = ['username' => 'bob', 'password' => 'p'];
        $this->assertSame('bob', old('username'), 'falls back to the current POST');
        $this->assertSame('', old('password'));
    }

    public function testRequestIdAndNonceHelpers(): void
    {
        $this->assertSame(defined('REQUEST_ID') ? REQUEST_ID : '-', request_id());
        $this->assertSame(defined('CSP_NONCE') ? CSP_NONCE : '', csp_nonce());
    }
}

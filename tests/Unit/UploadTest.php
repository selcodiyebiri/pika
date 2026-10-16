<?php
use PHPUnit\Framework\TestCase;
use Pika\Upload;
use Pika\UploadException;

class UploadTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    private array $tmp = [];

    public static function setUpBeforeClass(): void
    {
        defined('SITE_UPLOAD_PATH') || define('SITE_UPLOAD_PATH', TEST_UPLOAD_DIR . '/');
    }

    protected function tearDown(): void
    {
        foreach ($this->tmp as $f) @unlink($f);
    }

    private function file(string $name, string $content, ?int $error = null): array
    {
        $path = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($path, $content);
        $this->tmp[] = $path;
        return ['name' => $name, 'tmp_name' => $path, 'error' => $error ?? UPLOAD_ERR_OK, 'size' => strlen($content)];
    }

    private function rejects(array $file, array $opts = [], string $why = ''): void
    {
        try { Upload::validate($file, $opts + ['allow_non_uploaded' => true]); $this->fail("accepted: $why"); }
        catch (UploadException $e) { $this->addToAssertionCount(1); }
    }

    public function testAcceptsRealPng(): void
    {
        [$ext, $mime] = Upload::validate($this->file('Photo.PNG', base64_decode(self::PNG)), ['allow_non_uploaded' => true]);
        $this->assertSame(['png', 'image/png'], [$ext, $mime]);
    }

    public function testRejectsScriptsAndDisguises(): void
    {
        $php = '<?php system($_GET["c"]); ?>';
        $this->rejects($this->file('shell.php', $php), [], 'plain php');
        $this->rejects($this->file('shell.php.png', $php), [], 'double extension');
        $this->rejects($this->file('shell.png', $php), [], 'php content with image extension');
        $this->rejects($this->file('shell.png.php', base64_decode(self::PNG)), [], 'real image, php extension');
        $this->rejects($this->file('x.svg', '<svg onload="alert(1)"/>'), [], 'svg');
        $this->rejects($this->file('x.png', 'GIF89a' . $php), [], 'gif header + php, png name');
        $this->rejects($this->file('noext', base64_decode(self::PNG)), [], 'no extension');
        $this->rejects($this->file('.htaccess', 'x'), [], 'htaccess');
    }

    public function testRejectsEmptyOversizedAndErrorUploads(): void
    {
        $this->rejects($this->file('a.png', ''), [], 'empty');
        $this->rejects($this->file('a.png', base64_decode(self::PNG)), ['max_size' => 10], 'too large');
        $this->rejects($this->file('a.png', 'x', UPLOAD_ERR_PARTIAL), [], 'upload error');
        $this->rejects(['name' => 'a.png'], [], 'malformed');
        // without the test flag a file that was not really uploaded via HTTP is refused
        $this->expectException(UploadException::class);
        Upload::validate($this->file('a.png', base64_decode(self::PNG)));
    }

    public function testSaveUsesRandomNameAndSafeDirectory(): void
    {
        $saved = Upload::save($this->file('my photo.png', base64_decode(self::PNG)), ['dir' => 'avatars', 'allow_non_uploaded' => true]);
        $this->assertMatchesRegularExpression('#^avatars/[0-9a-f]{32}\.png$#', $saved['path']);
        $this->assertFileExists(TEST_UPLOAD_DIR . '/' . $saved['path']);
        $this->assertStringNotContainsString('photo', $saved['name'], 'original name is not reused');
        @unlink(TEST_UPLOAD_DIR . '/' . $saved['path']);

        // a leading slash is trimmed, so the file still lands inside the upload folder
        $inside = Upload::save($this->file('a.png', base64_decode(self::PNG)), ['dir' => '/etc', 'allow_non_uploaded' => true]);
        $this->assertStringStartsWith('etc/', $inside['path']);
        $this->assertFileExists(TEST_UPLOAD_DIR . '/' . $inside['path']);
        @unlink(TEST_UPLOAD_DIR . '/' . $inside['path']);

        foreach (['../evil', 'a/../../b', 'a b', 'a\\b', '..'] as $dir) {
            try { Upload::save($this->file('a.png', base64_decode(self::PNG)), ['dir' => $dir, 'allow_non_uploaded' => true]); $this->fail("dir accepted: $dir"); }
            catch (UploadException $e) { $this->addToAssertionCount(1); }
        }
    }

    public function testUploadsFolderShipsWithScriptBlockingRules(): void
    {
        $htaccess = file_get_contents(BASEPATH . '/public/uploads/.htaccess');
        $this->assertStringContainsString('Require all denied', $htaccess);
        $this->assertStringContainsString('-ExecCGI -Indexes', $htaccess);
    }
}

<?php
use PHPUnit\Framework\TestCase;
use Pika\SecureString;

class SecureStringTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $this->assertSame('secret data', SecureString::decode(SecureString::encode('secret data')));
    }

    public function testTamperedPayloadIsRejected(): void
    {
        $raw = base64_decode(SecureString::encode('secret data'));
        $raw[strlen($raw) - 1] = $raw[strlen($raw) - 1] ^ "\x01";
        $this->assertFalse(SecureString::decode(base64_encode($raw)));
    }

    public function testGarbageIsRejected(): void
    {
        $this->assertFalse(SecureString::decode('not-base64!!'));
        $this->assertFalse(SecureString::decode(base64_encode('short')));
    }
}

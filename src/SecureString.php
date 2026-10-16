<?php
namespace Pika;

/**
 * Authenticated encryption (AES-256-GCM). The key derives from PIKA_LICENSE.
 * Format: base64(iv[12] . tag[16] . ciphertext)
 */
class SecureString
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LEN = 12;
    private const TAG_LEN = 16;

    private static function key(): string
    {
        if (PIKA_LICENSE === '' || PIKA_LICENSE === 'change-me') {
            throw new \RuntimeException('PIKA_LICENSE must be set in .env before using SecureString.');
        }
        return hash('sha256', PIKA_LICENSE, true);
    }

    public static function encode(string $data): string
    {
        $iv = random_bytes(self::IV_LEN);
        $cipher = openssl_encrypt($data, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LEN);
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decode(string $encrypted_data): string|false
    {
        $raw = base64_decode($encrypted_data, true);
        if ($raw === false || strlen($raw) <= self::IV_LEN + self::TAG_LEN) {
            return false;
        }
        $iv = substr($raw, 0, self::IV_LEN);
        $tag = substr($raw, self::IV_LEN, self::TAG_LEN);
        $cipher = substr($raw, self::IV_LEN + self::TAG_LEN);
        return openssl_decrypt($cipher, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
    }
}

<?php

declare(strict_types=1);

namespace LifeTwin\WooCommerce\Tests;

use LifeTwin\WooCommerce\Crypto;

final class CryptoTest extends TestCase
{
    public function test_encrypt_decrypt_roundtrip(): void
    {
        if (! Crypto::isAvailable()) {
            self::markTestSkipped('OpenSSL AES-256-GCM yok.');
        }

        $plain = 'lt_secret_1234567890';
        $encrypted = Crypto::encrypt($plain);

        self::assertNotSame($plain, $encrypted);
        self::assertSame($plain, Crypto::decrypt($encrypted));
        self::assertSame('7890', Crypto::lastFour($plain));
    }

    public function test_tampered_payload_is_rejected(): void
    {
        if (! Crypto::isAvailable()) {
            self::markTestSkipped('OpenSSL AES-256-GCM yok.');
        }

        $encrypted = Crypto::encrypt('secret');
        $data = json_decode($encrypted, true);
        $data['cipher'] = base64_encode(base64_decode($data['cipher'], true).'x');

        self::assertSame('', Crypto::decrypt((string) json_encode($data)));
    }

    public function test_empty_secret_stays_empty(): void
    {
        self::assertSame('', Crypto::encrypt(''));
        self::assertSame('', Crypto::decrypt(''));
    }
}

<?php

namespace LifeTwin\WooCommerce;

defined('ABSPATH') || exit;

/**
 * API secret'ını WordPress tuzlarından türetilen anahtarla şifreler.
 *
 * AES-256-GCM kullanılır; tag ve IV her kayıtta yenidir. OpenSSL yoksa
 * eklenti secret kaydetmez ve ayar ekranında hata gösterir.
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';

    private const IV_BYTES = 12;

    public static function isAvailable(): bool
    {
        return function_exists('openssl_encrypt')
            && function_exists('openssl_decrypt')
            && in_array(self::CIPHER, openssl_get_cipher_methods(), true);
    }

    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }

        if (! self::isAvailable()) {
            return '';
        }

        $iv = random_bytes(self::IV_BYTES);
        $tag = '';
        $cipher = openssl_encrypt(
            $plain,
            self::CIPHER,
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($cipher === false) {
            return '';
        }

        return (string) wp_json_encode([
            'v' => 1,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'cipher' => base64_encode($cipher),
        ]);
    }

    public static function decrypt(string $encoded): string
    {
        if ($encoded === '' || ! self::isAvailable()) {
            return '';
        }

        $data = json_decode($encoded, true);

        if (! is_array($data) || (int) ($data['v'] ?? 0) !== 1) {
            return '';
        }

        $iv = base64_decode((string) ($data['iv'] ?? ''), true);
        $tag = base64_decode((string) ($data['tag'] ?? ''), true);
        $cipher = base64_decode((string) ($data['cipher'] ?? ''), true);

        if ($iv === false || $tag === false || $cipher === false) {
            return '';
        }

        $plain = openssl_decrypt(
            $cipher,
            self::CIPHER,
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return is_string($plain) ? $plain : '';
    }

    public static function lastFour(string $plain): string
    {
        return $plain === '' ? '' : substr($plain, -4);
    }

    private static function key(): string
    {
        if (function_exists('wp_salt')) {
            $salt = wp_salt('auth').'|'.wp_salt('secure_auth');
        } else {
            $salt = (defined('AUTH_KEY') ? AUTH_KEY : 'lifetwin')
                .'|'.(defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : 'woocommerce');
        }

        return hash('sha256', $salt, true);
    }
}

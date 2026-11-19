<?php

namespace LifeTwin\WooCommerce;

defined('ABSPATH') || exit;

/**
 * Bearer token'ını WordPress transient'inde tutar.
 *
 * Token hiçbir zaman seçenek, post meta veya log'a yazılmaz. Anahtar
 * değiştiğinde transient anahtarı da değişir; eski token kullanılmaz.
 */
final class TokenStore
{
    public static function get(): ?string
    {
        $value = get_transient(self::key());

        if (! is_array($value)) {
            return null;
        }

        $token = (string) ($value['token'] ?? '');
        $expiresAt = (int) ($value['expires_at'] ?? 0);

        if ($token === '' || $expiresAt <= time() + 30) {
            delete_transient(self::key());

            return null;
        }

        return $token;
    }

    public static function put(string $token, int $expiresIn): void
    {
        $ttl = max(60, min($expiresIn - 120, 3300));

        set_transient(self::key(), [
            'token' => $token,
            'expires_at' => time() + $ttl,
        ], $ttl);
    }

    public static function forget(): void
    {
        delete_transient(self::key());
    }

    private static function key(): string
    {
        return 'lifetwin_wc_token_'.md5(Settings::apiUrl().'|'.Settings::apiKey());
    }
}

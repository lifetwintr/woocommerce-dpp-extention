<?php

namespace LifeTwin\WooCommerce;

defined('ABSPATH') || exit;

/**
 * LifeTwin webhook alıcısı.
 *
 * `POST /wp-json/lifetwin/v1/webhook`
 *
 * İmza `X-Lifetwin-Signature: t=<unix>,v1=<hex>` biçimindedir ve
 * `<t>.<raw body>` üzerinden HMAC-SHA256 ile hesaplanır. Zaman damgası
 * 5 dakikadan eskiyse istek reddedilir; aynı `id` ikinci kez işlenmez.
 */
final class Webhook
{
    public const TOLERANCE_SECONDS = 300;

    public const MAX_SEEN = 200;

    public static function boot(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoute']);
    }

    public static function registerRoute(): void
    {
        register_rest_route('lifetwin/v1', '/webhook', [
            'methods' => 'POST',
            'callback' => [self::class, 'handle'],
            'permission_callback' => '__return_true', // imza ile doğrulanır
        ]);
    }

    /**
     * @return \WP_REST_Response
     */
    public static function handle(\WP_REST_Request $request)
    {
        $raw = (string) $request->get_body();
        $secret = Settings::webhookSecret();

        if ($secret === '') {
            return new \WP_REST_Response(['error' => 'webhook_not_configured'], 503);
        }

        $header = (string) ($request->get_header('x-lifetwin-signature') ?? '');

        if (! self::verify($secret, $raw, $header)) {
            Logger::log('warning', 'Webhook imzası geçersiz.', [
                'ip' => (string) ($request->get_header('x-forwarded-for') ?? ''),
            ]);

            return new \WP_REST_Response(['error' => 'invalid_signature'], 401);
        }

        $event = json_decode($raw, true);

        if (! is_array($event)) {
            return new \WP_REST_Response(['error' => 'invalid_payload'], 400);
        }

        $deliveryId = (string) ($event['id'] ?? '');

        if ($deliveryId !== '' && self::alreadyProcessed($deliveryId)) {
            return new \WP_REST_Response(['ok' => true, 'duplicate' => true], 200);
        }

        $name = (string) ($event['event'] ?? '');
        $data = is_array($event['data'] ?? null) ? $event['data'] : [];

        if ($name === 'dpp.published') {
            self::applyDppPublished($data);
        }

        if ($deliveryId !== '') {
            self::remember($deliveryId);
        }

        Logger::log('info', 'Webhook alındı.', [
            'event' => $name,
            'delivery_id' => $deliveryId,
        ]);

        return new \WP_REST_Response(['ok' => true], 200);
    }

    public static function verify(
        string $secret,
        string $body,
        string $header,
        ?int $now = null,
        int $tolerance = self::TOLERANCE_SECONDS,
    ): bool {
        if ($secret === '' || $header === '') {
            return false;
        }

        $parts = [];

        foreach (explode(',', $header) as $segment) {
            $pair = explode('=', trim($segment), 2);

            if (count($pair) === 2) {
                $parts[$pair[0]] = $pair[1];
            }
        }

        $timestamp = isset($parts['t']) ? (int) $parts['t'] : 0;
        $provided = (string) ($parts['v1'] ?? '');

        if ($timestamp <= 0 || $provided === '') {
            return false;
        }

        if (abs(($now ?? time()) - $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        return hash_equals($expected, $provided);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function applyDppPublished(array $data): void
    {
        $productId = self::findProduct($data);

        if ($productId <= 0) {
            Logger::log('warning', 'Webhook ürünü eşleşmedi.', [
                'lifetwin_id' => (int) ($data['product_id'] ?? $data['id'] ?? 0),
                'external_ref' => (string) ($data['external_ref'] ?? ''),
            ]);

            return;
        }

        $dppUrl = (string) ($data['dpp_url'] ?? $data['url'] ?? '');
        $dppApiUrl = (string) ($data['dpp_api_url'] ?? '');

        if ($dppUrl !== '') {
            update_post_meta($productId, '_lifetwin_dpp_url', $dppUrl);
        }

        if ($dppApiUrl !== '') {
            update_post_meta($productId, '_lifetwin_dpp_api_url', $dppApiUrl);
        }

        update_post_meta($productId, '_lifetwin_status', 'published');
        update_post_meta($productId, '_lifetwin_last_sync', gmdate('c'));

        Logger::log('info', 'DPP yayınlandı; pasaport bağlantısı güncellendi.', [
            'product_id' => $productId,
            'dpp_url' => $dppUrl,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function findProduct(array $data): int
    {
        $lifetwinId = (int) ($data['product_id'] ?? $data['id'] ?? 0);

        if ($lifetwinId > 0) {
            return self::findByMeta('_lifetwin_id', (string) $lifetwinId);
        }

        $externalRef = (string) ($data['external_ref'] ?? '');

        if ($externalRef !== '') {
            return self::findByMeta('_lifetwin_external_ref', $externalRef);
        }

        return 0;
    }

    private static function findByMeta(string $key, string $value): int
    {
        if (! function_exists('get_posts')) {
            return 0;
        }

        $ids = get_posts([
            'post_type' => 'product',
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => 1,
            'meta_key' => $key,
            'meta_value' => $value,
        ]);

        return is_array($ids) && isset($ids[0]) ? (int) $ids[0] : 0;
    }

    private static function alreadyProcessed(string $deliveryId): bool
    {
        $seen = self::seen();

        return array_key_exists($deliveryId, $seen);
    }

    private static function remember(string $deliveryId): void
    {
        $seen = self::seen();
        $seen[$deliveryId] = time();

        // 7 günden eski kayıtları at, en yeni MAX_SEEN kaydı tut.
        $cutoff = time() - (7 * DAY_IN_SECONDS);
        $seen = array_filter($seen, static fn (int $at): bool => $at >= $cutoff);
        arsort($seen);

        update_option(
            Settings::WEBHOOK_SEEN_OPTION,
            array_slice($seen, 0, self::MAX_SEEN, true),
            false
        );
    }

    /**
     * @return array<string, int>
     */
    private static function seen(): array
    {
        $seen = get_option(Settings::WEBHOOK_SEEN_OPTION, []);

        return is_array($seen) ? $seen : [];
    }
}

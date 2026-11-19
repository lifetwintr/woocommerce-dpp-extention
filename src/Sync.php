<?php

namespace LifeTwin\WooCommerce;

defined('ABSPATH') || exit;

/**
 * Tek ürün senkronu. Kuyruk tarafından çağrılır; HTTP hatası olsa da
 * istisna fırlatmaz, ürün meta'sına durum yazar.
 */
final class Sync
{
    /**
     * Ürünü ve varsa varyasyonlarını LifeTwin'e gönderir.
     *
     * Varyasyonlar ayrı ürün olarak gönderilir ve `external_parent_ref` ile
     * ana ürüne bağlanır. Bir varyasyonun hatası ana ürünün sonucunu bozmaz;
     * hata ilgili varyasyon kaydında raporlanır.
     *
     * @return array{ok: bool, skipped?: bool, data?: array<string, mixed>, error?: string, variations?: array<int, array<string, mixed>>}
     */
    public static function push(int $productId, bool $force = false, ?Client $client = null): array
    {
        $client ??= new Client;

        $result = self::pushOne($productId, $force, $client, null);
        $variationIds = Mapper::variationIds($productId);

        if ($variationIds === []) {
            return $result;
        }

        $parentRef = Mapper::externalRef($productId);
        $variations = [];
        $allOk = (bool) ($result['ok'] ?? false);

        foreach ($variationIds as $variationId) {
            $variations[$variationId] = self::pushOne($variationId, $force, $client, $parentRef);
            $allOk = $allOk && (bool) ($variations[$variationId]['ok'] ?? false);
        }

        return [
            'ok' => $allOk,
            'skipped' => (bool) ($result['skipped'] ?? false),
            'data' => $result['data'] ?? [],
            'variations' => $variations,
        ];
    }

    /**
     * @return array{ok: bool, skipped?: bool, data?: array<string, mixed>, error?: string}
     */
    private static function pushOne(
        int $productId,
        bool $force,
        Client $client,
        ?string $parentRef,
    ): array {
        if (! Settings::isConfigured()) {
            return self::markError(
                $productId,
                new \WP_Error(
                    'lifetwin_not_configured',
                    __('LifeTwin bağlantısı yapılandırılmamış.', 'lifetwin-woocommerce')
                )
            );
        }

        $payload = Mapper::toPayload($productId, $parentRef);

        if (is_wp_error($payload)) {
            return self::markError($productId, $payload);
        }

        $hash = self::contentHash($payload);

        if (! $force
            && get_post_meta($productId, '_lifetwin_content_hash', true) === $hash
            && get_post_meta($productId, '_lifetwin_status', true) === 'synced') {
            return ['ok' => true, 'skipped' => true];
        }

        $result = $client->upsertProduct($payload);

        if (is_wp_error($result)) {
            return self::markError($productId, $result);
        }

        update_post_meta($productId, '_lifetwin_id', (int) ($result['id'] ?? 0));
        update_post_meta($productId, '_lifetwin_external_ref', (string) ($result['external_ref'] ?? ''));
        update_post_meta($productId, '_lifetwin_status', 'synced');
        update_post_meta($productId, '_lifetwin_last_sync', gmdate('c'));
        update_post_meta($productId, '_lifetwin_content_hash', $hash);

        $dppUrl = (string) ($result['dpp_url'] ?? '');
        $dppApiUrl = (string) ($result['dpp_api_url'] ?? '');
        $vcUrl = (string) ($result['vc_url'] ?? '');

        update_post_meta($productId, '_lifetwin_dpp_url', $dppUrl);
        update_post_meta($productId, '_lifetwin_dpp_api_url', $dppApiUrl);
        update_post_meta($productId, '_lifetwin_vc_url', $vcUrl);

        delete_post_meta($productId, '_lifetwin_last_error');
        delete_post_meta($productId, '_lifetwin_last_error_at');

        Logger::log('info', 'Ürün LifeTwin ile senkronlandı.', [
            'product_id' => $productId,
            'lifetwin_id' => (int) ($result['id'] ?? 0),
            'noop' => (bool) ($result['noop'] ?? false),
            'variant' => $parentRef !== null,
        ]);

        return ['ok' => true, 'data' => $result];
    }

    /**
     * @return array<string, mixed>
     */
    public static function status(int $productId): array
    {
        return [
            'status' => (string) get_post_meta($productId, '_lifetwin_status', true) ?: 'not_connected',
            'lifetwin_id' => (int) get_post_meta($productId, '_lifetwin_id', true),
            'external_ref' => (string) get_post_meta($productId, '_lifetwin_external_ref', true),
            'dpp_url' => (string) get_post_meta($productId, '_lifetwin_dpp_url', true),
            'dpp_api_url' => (string) get_post_meta($productId, '_lifetwin_dpp_api_url', true),
            'vc_url' => (string) get_post_meta($productId, '_lifetwin_vc_url', true),
            'last_sync' => (string) get_post_meta($productId, '_lifetwin_last_sync', true),
            'last_error' => (string) get_post_meta($productId, '_lifetwin_last_error', true),
            'last_error_at' => (string) get_post_meta($productId, '_lifetwin_last_error_at', true),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function contentHash(array $payload): string
    {
        return hash('sha256', (string) wp_json_encode($payload));
    }

    /**
     * @return array{ok: bool, error: string}
     */
    private static function markError(int $productId, \WP_Error $error): array
    {
        $message = $error->get_error_message();
        $code = $error->get_error_code();
        $data = $error->get_error_data();
        $data = is_array($data) ? $data : [];

        update_post_meta($productId, '_lifetwin_status', 'error');
        update_post_meta($productId, '_lifetwin_last_error', $code.': '.$message);
        update_post_meta($productId, '_lifetwin_last_error_at', gmdate('c'));
        delete_post_meta($productId, '_lifetwin_content_hash');

        Logger::log('error', 'LifeTwin senkronu başarısız.', [
            'product_id' => $productId,
            'code' => $code,
            'status' => $data['status'] ?? null,
            'request_id' => $data['request_id'] ?? null,
        ]);

        return ['ok' => false, 'error' => $code.': '.$message];
    }
}

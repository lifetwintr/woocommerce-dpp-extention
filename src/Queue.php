<?php

namespace LifeTwin\WooCommerce;

defined('ABSPATH') || exit;

/**
 * Ürün olaylarını kuyruğa alır; olay içinde HTTP çağrısı yapmaz.
 *
 * Action Scheduler varsa (WooCommerce ile gelir) onu kullanır; yoksa
 * WP-Cron tek olayına düşer.
 */
final class Queue
{
    public const HOOK = 'lifetwin_wc_sync_product';

    public static function boot(): void
    {
        add_action('woocommerce_new_product', [self::class, 'enqueue'], 20, 1);
        add_action('woocommerce_update_product', [self::class, 'enqueue'], 20, 1);
        add_action(self::HOOK, [self::class, 'handle'], 10, 2);

        // Varyasyon değişikliğinde ana ürünü yeniden göndeririz; Sync ana
        // ürünün ardından tüm varyasyonları upsert eder.
        add_action('woocommerce_save_product_variation', [self::class, 'onVariationSaved'], 20, 2);
        add_action('woocommerce_update_product_variation', [self::class, 'onVariationSaved'], 20, 1);

        add_action('before_delete_post', [self::class, 'onDelete'], 10, 1);
        add_action('wp_trash_post', [self::class, 'onDelete'], 10, 1);
        add_action('untrashed_post', [self::class, 'onUntrash'], 10, 1);
    }

    public static function enqueue(int $productId, bool $force = false): bool
    {
        if ($productId <= 0 || ! Settings::syncEnabled() || ! Settings::isConfigured()) {
            return false;
        }

        $payload = Mapper::toPayload($productId);

        if (is_wp_error($payload)) {
            update_post_meta($productId, '_lifetwin_status', 'error');
            update_post_meta(
                $productId,
                '_lifetwin_last_error',
                $payload->get_error_code().': '.$payload->get_error_message()
            );
            update_post_meta($productId, '_lifetwin_last_error_at', gmdate('c'));
            delete_post_meta($productId, '_lifetwin_content_hash');

            Logger::log('warning', 'Ürün kuyruğa alınamadı.', [
                'product_id' => $productId,
                'code' => $payload->get_error_code(),
            ]);

            return false;
        }

        $hash = Sync::contentHash($payload);
        $status = (string) get_post_meta($productId, '_lifetwin_status', true);
        $existingHash = (string) get_post_meta($productId, '_lifetwin_content_hash', true);

        if (! $force && $status === 'synced' && $existingHash === $hash) {
            return false;
        }

        update_post_meta($productId, '_lifetwin_status', 'pending');
        update_post_meta($productId, '_lifetwin_last_queued_at', gmdate('c'));

        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(self::HOOK, [$productId, $hash], 'lifetwin');

            return true;
        }

        wp_schedule_single_event(time() + 5, self::HOOK, [$productId, $hash]);

        return true;
    }

    public static function handle(int $productId, string $hash = ''): void
    {
        Sync::push($productId);
    }

    public static function onVariationSaved(int $variationId, int $index = 0): void
    {
        if ($variationId <= 0) {
            return;
        }

        $parentId = function_exists('wp_get_post_parent_id')
            ? (int) wp_get_post_parent_id($variationId)
            : 0;

        if ($parentId > 0) {
            self::enqueue($parentId, true);
        }
    }

    public static function onDelete(int $postId): void
    {
        if (get_post_type($postId) !== 'product') {
            return;
        }

        update_post_meta($postId, '_lifetwin_status', 'archived');
        delete_post_meta($postId, '_lifetwin_content_hash');

        Logger::log('info', 'Ürün arşivlendi; LifeTwin kaydı silinmedi.', [
            'product_id' => $postId,
        ]);
    }

    public static function onUntrash(int $postId): void
    {
        if (get_post_type($postId) !== 'product') {
            return;
        }

        self::enqueue($postId, true);
    }
}

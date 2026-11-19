<?php
/**
 * Ürün düzenleme ekranındaki LifeTwin paneli.
 *
 * @var array<string, mixed> $status
 * @var bool $isConfigured
 * @var bool $isConnected
 * @var bool $queued
 * @var \WP_Post $post
 */

defined('ABSPATH') || exit;

$state = (string) ($status['status'] ?? 'not_connected');

$labels = [
    'not_connected' => __('Bağlı değil', 'lifetwin-woocommerce'),
    'pending' => __('Kuyrukta', 'lifetwin-woocommerce'),
    'synced' => __('Senkron', 'lifetwin-woocommerce'),
    'error' => __('Hata', 'lifetwin-woocommerce'),
    'archived' => __('Arşiv', 'lifetwin-woocommerce'),
];

$label = $labels[$state] ?? $state;
?>
<div class="lifetwin-wc-panel">
    <?php if ($queued) : ?>
        <div class="notice notice-success inline"><p>
            <?php echo esc_html__('Senkron kuyruğa alındı.', 'lifetwin-woocommerce'); ?>
        </p></div>
    <?php endif; ?>

    <p>
        <span class="lifetwin-wc-badge is-<?php echo esc_attr($state); ?>">
            <?php echo esc_html($label); ?>
        </span>
    </p>

    <?php if (! $isConfigured) : ?>
        <p class="description">
            <?php echo esc_html__('LifeTwin bağlantısı yapılandırılmadı.', 'lifetwin-woocommerce'); ?>
        </p>
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=lifetwin-dpp')); ?>" class="button button-small">
                <?php echo esc_html__('Ayarlara git', 'lifetwin-woocommerce'); ?>
            </a>
        </p>
    <?php else : ?>
        <?php if (! $isConnected) : ?>
            <p class="description">
                <?php echo esc_html__('Bağlantı henüz doğrulanmadı. Ayarlar ekranından test edin.', 'lifetwin-woocommerce'); ?>
            </p>
        <?php endif; ?>

        <?php if (! empty($status['last_sync'])) : ?>
            <p class="description">
                <?php echo esc_html(sprintf(
                    /* translators: %s: date */
                    __('Son senkron: %s', 'lifetwin-woocommerce'),
                    (string) $status['last_sync']
                )); ?>
            </p>
        <?php endif; ?>

        <?php if (! empty($status['dpp_url'])) : ?>
            <p>
                <a href="<?php echo esc_url((string) $status['dpp_url']); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html__('Pasaportu aç', 'lifetwin-woocommerce'); ?>
                </a>
            </p>
        <?php endif; ?>

        <?php if (! empty($status['dpp_api_url'])) : ?>
            <p>
                <a href="<?php echo esc_url((string) $status['dpp_api_url']); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html__('DPP JSON', 'lifetwin-woocommerce'); ?>
                </a>
            </p>
        <?php endif; ?>

        <?php if (! empty($status['last_error'])) : ?>
            <div class="notice notice-error inline">
                <p><?php echo esc_html((string) $status['last_error']); ?></p>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="lifetwin_wc_sync_product">
            <input type="hidden" name="product_id" value="<?php echo esc_attr((string) $post->ID); ?>">
            <?php wp_nonce_field('lifetwin_wc_sync_product_'.$post->ID); ?>
            <p>
                <button type="submit" class="button button-small">
                    <?php echo esc_html__('Şimdi gönder', 'lifetwin-woocommerce'); ?>
                </button>
            </p>
        </form>
    <?php endif; ?>
</div>

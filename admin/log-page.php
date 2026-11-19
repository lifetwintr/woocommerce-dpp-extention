<?php
/**
 * Son senkron kayıtları.
 *
 * @var array<int, array<string, mixed>> $logs
 */

defined('ABSPATH') || exit;
?>
<div class="wrap lifetwin-wc-wrap">
    <h1><?php echo esc_html__('LifeTwin Log', 'lifetwin-woocommerce'); ?></h1>

    <p class="description">
        <?php echo esc_html__(
            'Son 20 kayıt gösterilir. Secret ve token değerleri loglanmaz.',
            'lifetwin-woocommerce'
        ); ?>
    </p>

    <table class="widefat striped lifetwin-wc-log">
        <thead>
            <tr>
                <th><?php echo esc_html__('Zaman', 'lifetwin-woocommerce'); ?></th>
                <th><?php echo esc_html__('Seviye', 'lifetwin-woocommerce'); ?></th>
                <th><?php echo esc_html__('Mesaj', 'lifetwin-woocommerce'); ?></th>
                <th><?php echo esc_html__('Detay', 'lifetwin-woocommerce'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($logs === []) : ?>
                <tr>
                    <td colspan="4"><?php echo esc_html__('Henüz kayıt yok.', 'lifetwin-woocommerce'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($logs as $entry) : ?>
                    <tr>
                        <td><code><?php echo esc_html((string) ($entry['time'] ?? '')); ?></code></td>
                        <td><?php echo esc_html((string) ($entry['level'] ?? '')); ?></td>
                        <td><?php echo esc_html((string) ($entry['message'] ?? '')); ?></td>
                        <td>
                            <code class="lifetwin-wc-log-context">
                                <?php
                                $context = $entry['context'] ?? [];
                                echo esc_html((string) wp_json_encode($context, JSON_UNESCAPED_UNICODE));
                                ?>
                            </code>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="lifetwin_wc_clear_log">
        <?php wp_nonce_field('lifetwin_wc_clear_log'); ?>
        <p>
            <button type="submit" class="button">
                <?php echo esc_html__('Logları temizle', 'lifetwin-woocommerce'); ?>
            </button>
        </p>
    </form>
</div>

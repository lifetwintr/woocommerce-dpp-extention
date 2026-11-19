<?php
/**
 * LifeTwin DPP ayar ekranı.
 *
 * @var array<string, mixed> $settings
 * @var array<string, mixed> $connection
 * @var array<string, string> $types
 * @var bool $isConfigured
 * @var bool $isConnected
 * @var string $secretLastFour
 * @var string $apiUrl
 * @var array<int, array<string, mixed>> $companies
 * @var bool $hasCompanies
 * @var string $webhookUrl
 * @var string $webhookSecretLastFour
 */

defined('ABSPATH') || exit;
?>
<div class="wrap lifetwin-wc-wrap">
    <h1><?php echo esc_html__('LifeTwin DPP', 'lifetwin-woocommerce'); ?></h1>

    <p class="lifetwin-wc-lead">
        <?php echo esc_html__(
            'Ürünlerinizi LifeTwin\'e gönderin, DPP pasaport bağlantısını WooCommerce ürününe ekleyin.',
            'lifetwin-woocommerce'
        ); ?>
    </p>

    <div class="lifetwin-wc-grid">
        <div class="lifetwin-wc-card">
            <h2><?php echo esc_html__('Bağlantı durumu', 'lifetwin-woocommerce'); ?></h2>

            <p>
                <span class="lifetwin-wc-badge <?php echo $isConnected ? 'is-ok' : 'is-off'; ?>">
                    <?php echo $isConnected
                        ? esc_html__('Bağlı', 'lifetwin-woocommerce')
                        : esc_html__('Bağlı değil', 'lifetwin-woocommerce'); ?>
                </span>
            </p>

            <?php if (! empty($connection['tenant_id'])) : ?>
                <p>
                    <strong><?php echo esc_html__('Tenant', 'lifetwin-woocommerce'); ?>:</strong>
                    <?php echo esc_html((string) $connection['tenant_id']); ?>
                </p>
            <?php endif; ?>

            <?php if (! empty($connection['modules']) && is_array($connection['modules'])) : ?>
                <p>
                    <strong><?php echo esc_html__('Modüller', 'lifetwin-woocommerce'); ?>:</strong>
                    <?php
                    $enabled = array_keys(array_filter($connection['modules']));
                    echo esc_html($enabled === [] ? '—' : implode(', ', $enabled));
                    ?>
                </p>
            <?php endif; ?>

            <?php if (! empty($connection['tested_at'])) : ?>
                <p class="description">
                    <?php echo esc_html(sprintf(
                        /* translators: %s: date */
                        __('Son test: %s', 'lifetwin-woocommerce'),
                        (string) $connection['tested_at']
                    )); ?>
                </p>
            <?php endif; ?>

            <?php if (! empty($connection['error'])) : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html((string) $connection['error']); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="lifetwin_wc_test_connection">
                <?php wp_nonce_field('lifetwin_wc_test_connection'); ?>
                <p>
                    <button type="submit" class="button">
                        <?php echo esc_html__('Bağlantıyı test et', 'lifetwin-woocommerce'); ?>
                    </button>
                </p>
            </form>

            <p class="description">
                <?php echo esc_html__(
                    'Test, token alır ve /account/capabilities üzerinden DPP modülünü kontrol eder.',
                    'lifetwin-woocommerce'
                ); ?>
            </p>
        </div>

        <div class="lifetwin-wc-card">
            <h2><?php echo esc_html__('Bağlantı bilgileri', 'lifetwin-woocommerce'); ?></h2>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="lifetwin_wc_save_settings">
                <?php wp_nonce_field('lifetwin_wc_save_settings'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('API adresi', 'lifetwin-woocommerce'); ?></th>
                        <td>
                            <code><?php echo esc_html($apiUrl); ?></code>
                            <p class="description">
                                <?php echo esc_html__(
                                    'Sabittir. Farklı ortam için lifetwin_wc_api_url filtresini kullanın.',
                                    'lifetwin-woocommerce'
                                ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="lifetwin_api_key">
                                <?php echo esc_html__('API Key', 'lifetwin-woocommerce'); ?>
                            </label>
                        </th>
                        <td>
                            <input
                                type="text"
                                class="regular-text"
                                id="lifetwin_api_key"
                                name="api_key"
                                value="<?php echo esc_attr((string) $settings['api_key']); ?>"
                                autocomplete="off"
                                placeholder="lt_live_..."
                            >
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="lifetwin_api_secret">
                                <?php echo esc_html__('API Secret', 'lifetwin-woocommerce'); ?>
                            </label>
                        </th>
                        <td>
                            <input
                                type="password"
                                class="regular-text"
                                id="lifetwin_api_secret"
                                name="api_secret"
                                value=""
                                autocomplete="new-password"
                                placeholder="<?php echo $secretLastFour !== ''
                                    ? esc_attr('•••• '.$secretLastFour)
                                    : esc_attr__('API secret', 'lifetwin-woocommerce'); ?>"
                            >
                            <p class="description">
                                <?php echo esc_html__(
                                    'Boş bırakılırsa mevcut secret korunur. Secret şifreli saklanır ve bir daha gösterilmez.',
                                    'lifetwin-woocommerce'
                                ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="lifetwin_company_id">
                                <?php echo esc_html__('LifeTwin Şirketi', 'lifetwin-woocommerce'); ?>
                            </label>
                        </th>
                        <td>
                            <?php if ($hasCompanies) : ?>
                                <select id="lifetwin_company_id" name="company_id">
                                    <option value="0"><?php echo esc_html__('— Seçin —', 'lifetwin-woocommerce'); ?></option>
                                    <?php foreach ($companies as $company) : ?>
                                        <?php $companyId = (int) ($company['id'] ?? 0); ?>
                                        <option
                                            value="<?php echo esc_attr((string) $companyId); ?>"
                                            <?php selected((int) $settings['company_id'], $companyId); ?>
                                        >
                                            <?php echo esc_html(sprintf(
                                                '%s (#%d)',
                                                (string) ($company['name'] ?? ''),
                                                $companyId
                                            )); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <?php echo esc_html__(
                                        'Liste bağlantı testinde LifeTwin\'den okunur.',
                                        'lifetwin-woocommerce'
                                    ); ?>
                                </p>
                            <?php else : ?>
                                <input
                                    type="number"
                                    min="1"
                                    class="small-text"
                                    id="lifetwin_company_id"
                                    name="company_id"
                                    value="<?php echo esc_attr((string) $settings['company_id']); ?>"
                                >
                                <p class="description">
                                    <?php echo esc_html__(
                                        'Önce bağlantıyı test edin; şirketler otomatik listelenir. Liste gelmezse ID elle girilebilir.',
                                        'lifetwin-woocommerce'
                                    ); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="lifetwin_product_type">
                                <?php echo esc_html__('Varsayılan DPP tipi', 'lifetwin-woocommerce'); ?>
                            </label>
                        </th>
                        <td>
                            <select id="lifetwin_product_type" name="product_type">
                                <?php foreach ($types as $slug => $label) : ?>
                                    <option
                                        value="<?php echo esc_attr($slug); ?>"
                                        <?php selected((string) $settings['product_type'], (string) $slug); ?>
                                    >
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php echo esc_html__(
                                    'Ürün bazında _lifetwin_product_type meta alanıyla ezilebilir.',
                                    'lifetwin-woocommerce'
                                ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php echo esc_html__('Senkron', 'lifetwin-woocommerce'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="sync_enabled" value="1" <?php checked((bool) $settings['sync_enabled']); ?>>
                                <?php echo esc_html__('Ürün kaydedildiğinde otomatik gönder', 'lifetwin-woocommerce'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="push_dpp_fields" value="1" <?php checked((bool) $settings['push_dpp_fields']); ?>>
                                <?php echo esc_html__('Malzeme alanlarını DPP\'ye gönder', 'lifetwin-woocommerce'); ?>
                            </label>
                            <p class="description">
                                <?php echo esc_html__(
                                    'DPP alanları varsayılan olarak LifeTwin\'de yönetilir. Bu seçenek yalnız _lifetwin_materials meta alanı doldurulduysa etkilidir.',
                                    'lifetwin-woocommerce'
                                ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo esc_html__('Kaydet', 'lifetwin-woocommerce'); ?>
                    </button>
                </p>
            </form>
        </div>

        <div class="lifetwin-wc-card">
            <h2><?php echo esc_html__('DPP webhook', 'lifetwin-woocommerce'); ?></h2>

            <p class="description">
                <?php echo esc_html__(
                    'LifeTwin\'de bir webhook aboneliği oluşturup aşağıdaki adresi ve secret\'ı girin. Pasaport yayınlandığında ürünün pasaport bağlantısı güncellenir.',
                    'lifetwin-woocommerce'
                ); ?>
            </p>

            <p>
                <strong><?php echo esc_html__('Adres', 'lifetwin-woocommerce'); ?>:</strong><br>
                <code><?php echo esc_html($webhookUrl); ?></code>
            </p>

            <p>
                <strong><?php echo esc_html__('Olaylar', 'lifetwin-woocommerce'); ?>:</strong><br>
                <code>dpp.published</code>
            </p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="lifetwin_wc_save_settings">
                <?php wp_nonce_field('lifetwin_wc_save_settings'); ?>

                <p>
                    <label for="lifetwin_webhook_secret">
                        <strong><?php echo esc_html__('Webhook secret', 'lifetwin-woocommerce'); ?></strong>
                    </label><br>
                    <input
                        type="password"
                        class="regular-text"
                        id="lifetwin_webhook_secret"
                        name="webhook_secret"
                        value=""
                        autocomplete="new-password"
                        placeholder="<?php echo $webhookSecretLastFour !== ''
                            ? esc_attr('•••• '.$webhookSecretLastFour)
                            : esc_attr__('whsec_...', 'lifetwin-woocommerce'); ?>"
                    >
                </p>

                <p class="description">
                    <?php echo esc_html__(
                        'Boş bırakılırsa mevcut secret korunur. İmza HMAC-SHA256 ile doğrulanır.',
                        'lifetwin-woocommerce'
                    ); ?>
                </p>

                <p>
                    <button type="submit" class="button button-primary">
                        <?php echo esc_html__('Webhook secret kaydet', 'lifetwin-woocommerce'); ?>
                    </button>
                </p>
            </form>
        </div>
    </div>

    <div class="lifetwin-wc-card">
        <h2><?php echo esc_html__('Toplu senkron', 'lifetwin-woocommerce'); ?></h2>

        <p class="description">
            <?php echo esc_html__(
                'Tüm ürünleri sıraya koyar. Her ürün ayrı görevde çalıştığı için tarayıcıyı açık tutmanız gerekmez.',
                'lifetwin-woocommerce'
            ); ?>
        </p>

        <?php if ($isConfigured) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="lifetwin_wc_sync_all">
                <?php wp_nonce_field('lifetwin_wc_sync_all'); ?>
                <p>
                    <button type="submit" class="button"
                        onclick="return confirm('<?php echo esc_js(__('Tüm ürünler LifeTwin\'e gönderilecek. Devam edilsin mi?', 'lifetwin-woocommerce')); ?>');">
                        <?php echo esc_html__('Tüm ürünleri gönder', 'lifetwin-woocommerce'); ?>
                    </button>
                </p>
            </form>
        <?php else : ?>
            <p class="description">
                <?php echo esc_html__('Bağlantı tamamlanınca bu seçenek etkinleşir.', 'lifetwin-woocommerce'); ?>
            </p>
        <?php endif; ?>
    </div>

    <p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=lifetwin-dpp-log')); ?>">
            <?php echo esc_html__('Senkron loglarını görüntüle', 'lifetwin-woocommerce'); ?>
        </a>
    </p>
</div>

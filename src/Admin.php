<?php

namespace LifeTwin\WooCommerce;

defined('ABSPATH') || exit;

final class Admin
{
    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('add_meta_boxes', [self::class, 'metaBox']);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);

        add_action('admin_post_lifetwin_wc_save_settings', [self::class, 'saveSettings']);
        add_action('admin_post_lifetwin_wc_test_connection', [self::class, 'testConnection']);
        add_action('admin_post_lifetwin_wc_sync_product', [self::class, 'syncProduct']);
        add_action('admin_post_lifetwin_wc_clear_log', [self::class, 'clearLog']);
        add_action('admin_post_lifetwin_wc_sync_all', [self::class, 'syncAll']);
        add_filter('bulk_actions-edit-product', [self::class, 'registerBulkAction']);
        add_filter('handle_bulk_actions-edit-product', [self::class, 'handleBulkAction'], 10, 3);
        add_action('admin_notices', [self::class, 'notices']);
    }

    public static function menu(): void
    {
        add_submenu_page(
            'woocommerce',
            __('LifeTwin DPP', 'lifetwin-woocommerce'),
            __('LifeTwin DPP', 'lifetwin-woocommerce'),
            'manage_woocommerce',
            'lifetwin-dpp',
            [self::class, 'renderSettings']
        );

        add_submenu_page(
            'woocommerce',
            __('LifeTwin Log', 'lifetwin-woocommerce'),
            __('LifeTwin Log', 'lifetwin-woocommerce'),
            'manage_woocommerce',
            'lifetwin-dpp-log',
            [self::class, 'renderLog']
        );
    }

    public static function renderSettings(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $settings = Settings::get();
        $connection = Settings::connection();
        $types = Settings::availableProductTypes();
        $isConfigured = Settings::isConfigured();
        $isConnected = Settings::isConnected();
        $secretLastFour = Settings::secretLastFour();
        $apiUrl = Settings::apiUrl();
        $companies = Settings::companies();
        $hasCompanies = $companies !== [];
        $webhookUrl = Settings::webhookEndpointUrl();
        $webhookSecretLastFour = Settings::webhookSecretLastFour();

        include LIFETWIN_WC_PATH.'admin/settings-page.php';
    }

    public static function renderLog(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $logs = Logger::recent();

        include LIFETWIN_WC_PATH.'admin/log-page.php';
    }

    public static function saveSettings(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Bu işlem için yetkiniz yok.', 'lifetwin-woocommerce'));
        }

        check_admin_referer('lifetwin_wc_save_settings');

        $result = Settings::save($_POST);

        if (! $result['ok']) {
            self::redirectWithNotice('error', implode(' ', $result['errors']));

            return;
        }

        self::redirectWithNotice(
            'success',
            __('Ayarlar kaydedildi.', 'lifetwin-woocommerce')
        );
    }

    public static function testConnection(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Bu işlem için yetkiniz yok.', 'lifetwin-woocommerce'));
        }

        check_admin_referer('lifetwin_wc_test_connection');

        if (! Settings::isConfigured()) {
            self::redirectWithNotice(
                'error',
                __('Önce API anahtarı, secret ve şirket ID alanlarını doldurun.', 'lifetwin-woocommerce')
            );

            return;
        }

        $client = new Client;
        $result = $client->testConnection();

        if (is_wp_error($result)) {
            Settings::setConnection([
                'connected' => false,
                'tenant_id' => 0,
                'modules' => [],
                'limits' => [],
                'features' => [],
                'tested_at' => gmdate('c'),
                'error' => $result->get_error_code().': '.$result->get_error_message(),
                'companies' => [],
            ]);

            self::redirectWithNotice('error', $result->get_error_message());

            return;
        }

        $companies = [];
        $companiesResult = $client->companies();

        if (! is_wp_error($companiesResult)) {
            $companies = array_values(array_filter($companiesResult, 'is_array'));
        }

        Settings::setConnection([
            'connected' => true,
            'tenant_id' => (int) $result['tenant_id'],
            'modules' => $result['modules'],
            'limits' => $result['limits'],
            'features' => $result['features'],
            'tested_at' => gmdate('c'),
            'error' => '',
            'companies' => $companies,
        ]);

        self::selectCompanyFromConnection($companies);

        self::redirectWithNotice(
            'success',
            sprintf(
                /* translators: %d: LifeTwin tenant ID */
                __('Bağlantı başarılı. Tenant: %d', 'lifetwin-woocommerce'),
                (int) $result['tenant_id']
            )
        );
    }

    /**
     * Bağlantı testinde gelen şirket listesine göre şirket seçimini yönetir.
     *
     * Tek şirket varsa otomatik seçilir; kayıtlı şirket listede yoksa
     * seçim temizlenir ve kullanıcıdan yeni seçim istenir.
     *
     * @param  array<int, array<string, mixed>>  $companies
     */
    private static function selectCompanyFromConnection(array $companies): void
    {
        if ($companies === []) {
            return;
        }

        $ids = array_map(static fn (array $company): int => (int) ($company['id'] ?? 0), $companies);
        $current = Settings::companyId();

        if ($current > 0 && in_array($current, $ids, true)) {
            return;
        }

        Settings::setCompanyId(count($ids) === 1 ? $ids[0] : 0);
    }

    public static function syncProduct(): void
    {
        $productId = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

        if ($productId <= 0 || ! current_user_can('edit_product', $productId)) {
            wp_die(esc_html__('Ürün bulunamadı veya yetkiniz yok.', 'lifetwin-woocommerce'));
        }

        check_admin_referer('lifetwin_wc_sync_product_'.$productId);

        if (! Queue::enqueue($productId, true)) {
            self::redirectWithNotice(
                'error',
                __('Ürün kuyruğa alınamadı. Bağlantı ayarlarını ve ürün SKU/GTIN alanlarını kontrol edin.', 'lifetwin-woocommerce')
            );

            return;
        }

        $url = get_edit_post_link($productId, 'url');

        wp_safe_redirect(add_query_arg('lifetwin_queued', '1', $url ?: admin_url('edit.php?post_type=product')));
        exit;
    }

    public static function clearLog(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Bu işlem için yetkiniz yok.', 'lifetwin-woocommerce'));
        }

        check_admin_referer('lifetwin_wc_clear_log');
        Logger::clear();

        wp_safe_redirect(admin_url('admin.php?page=lifetwin-dpp-log'));
        exit;
    }

    /**
     * @param  array<string, string>  $actions
     * @return array<string, string>
     */
    public static function registerBulkAction(array $actions): array
    {
        if (! Settings::isConfigured()) {
            return $actions;
        }

        $actions['lifetwin_sync'] = __('LifeTwin\'e gönder', 'lifetwin-woocommerce');

        return $actions;
    }

    /**
     * @param  array<int, int|string>  $postIds
     */
    public static function handleBulkAction(string $redirectTo, string $action, array $postIds): string
    {
        if ($action !== 'lifetwin_sync' || ! current_user_can('manage_woocommerce')) {
            return $redirectTo;
        }

        $queued = 0;

        foreach ($postIds as $postId) {
            $postId = (int) $postId;

            if ($postId <= 0 || get_post_type($postId) !== 'product') {
                continue;
            }

            if (Queue::enqueue($postId, true)) {
                $queued++;
            }
        }

        Logger::log('info', 'Toplu senkron kuyruğa alındı.', [
            'requested' => count($postIds),
            'queued' => $queued,
        ]);

        return add_query_arg('lifetwin_bulk_queued', $queued, $redirectTo);
    }

    /**
     * Tüm ürünleri sıraya koyar; her ürün ayrı zamanlanmış görev olarak
     * çalışır, böylece istek zaman aşımına düşmez.
     */
    public static function syncAll(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Bu işlem için yetkiniz yok.', 'lifetwin-woocommerce'));
        }

        check_admin_referer('lifetwin_wc_sync_all');

        if (! Settings::isConfigured()) {
            self::redirectWithNotice(
                'error',
                __('Önce bağlantıyı yapılandırıp test edin.', 'lifetwin-woocommerce')
            );

            return;
        }

        $productIds = get_posts([
            'post_type' => 'product',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'fields' => 'ids',
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);

        $productIds = is_array($productIds) ? array_map('intval', $productIds) : [];
        $scheduled = 0;

        foreach ($productIds as $index => $productId) {
            // 5 saniye sonra başlar; her 5 üründe 1 saniye aralık.
            $when = time() + 5 + intdiv($index, 5);

            if (wp_schedule_single_event($when, Queue::HOOK, [$productId, ''])) {
                $scheduled++;
            }
        }

        Logger::log('info', 'Tüm ürünler senkron için zamanlandı.', [
            'products' => count($productIds),
            'scheduled' => $scheduled,
        ]);

        self::redirectWithNotice(
            'success',
            sprintf(
                /* translators: %d: scheduled product count */
                __('%d ürün senkron kuyruğuna alındı. Sonuçları log ekranından izleyin.', 'lifetwin-woocommerce'),
                $scheduled
            )
        );
    }

    public static function metaBox(): void
    {
        add_meta_box(
            'lifetwin_dpp',
            __('LifeTwin DPP', 'lifetwin-woocommerce'),
            [self::class, 'renderMetaBox'],
            'product',
            'side',
            'default'
        );
    }

    public static function renderMetaBox(\WP_Post $post): void
    {
        if (! current_user_can('edit_product', $post->ID)) {
            return;
        }

        $status = Sync::status((int) $post->ID);
        $isConfigured = Settings::isConfigured();
        $isConnected = Settings::isConnected();
        $queued = isset($_GET['lifetwin_queued']) && $_GET['lifetwin_queued'] === '1';

        include LIFETWIN_WC_PATH.'admin/product-panel.php';
    }

    public static function assets(string $hook): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        $isSettings = str_contains($hook, 'lifetwin-dpp');
        $isProduct = $screen && $screen->post_type === 'product';

        if (! $isSettings && ! $isProduct) {
            return;
        }

        wp_enqueue_style(
            'lifetwin-wc-admin',
            LIFETWIN_WC_URL.'assets/admin.css',
            [],
            LIFETWIN_WC_VERSION
        );
    }

    public static function notices(): void
    {
        $key = 'lifetwin_wc_notice_'.get_current_user_id();
        $notice = get_transient($key);

        if (! is_array($notice)) {
            return;
        }

        delete_transient($key);

        $type = ($notice['type'] ?? '') === 'error' ? 'error' : 'success';

        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr($type),
            esc_html((string) ($notice['message'] ?? ''))
        );
    }

    private static function redirectWithNotice(string $type, string $message): void
    {
        set_transient(
            'lifetwin_wc_notice_'.get_current_user_id(),
            ['type' => $type, 'message' => $message],
            60
        );

        wp_safe_redirect(admin_url('admin.php?page=lifetwin-dpp'));
        exit;
    }
}

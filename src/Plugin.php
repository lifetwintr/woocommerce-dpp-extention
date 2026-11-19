<?php

namespace LifeTwin\WooCommerce;

defined('ABSPATH') || exit;

final class Plugin
{
    private static ?self $instance = null;

    private bool $booted = false;

    public static function instance(): self
    {
        return self::$instance ??= new self;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        load_plugin_textdomain(
            'lifetwin-woocommerce',
            false,
            dirname(plugin_basename(LIFETWIN_WC_FILE)).'/languages'
        );

        if (! $this->isWooCommerceActive()) {
            add_action('admin_notices', [$this, 'renderWooCommerceNotice']);

            return;
        }

        Admin::boot();
        Queue::boot();
        Webhook::boot();

        add_filter(
            'plugin_action_links_'.plugin_basename(LIFETWIN_WC_FILE),
            [$this, 'settingsLink']
        );
    }

    public function isWooCommerceActive(): bool
    {
        return class_exists('WooCommerce');
    }

    public function renderWooCommerceNotice(): void
    {
        if (! current_user_can('activate_plugins')) {
            return;
        }

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__(
                'LifeTwin DPP için WooCommerce etkin olmalıdır.',
                'lifetwin-woocommerce'
            )
        );
    }

    /**
     * @param  array<int, string>  $links
     * @return array<int, string>
     */
    public function settingsLink(array $links): array
    {
        $url = admin_url('admin.php?page=lifetwin-dpp');

        array_unshift(
            $links,
            '<a href="'.esc_url($url).'">'.esc_html__('Ayarlar', 'lifetwin-woocommerce').'</a>'
        );

        return $links;
    }

    public static function activate(): void
    {
        if (get_option(Settings::OPTION) === false) {
            add_option(Settings::OPTION, Settings::defaults(), '', false);
        }
    }

    public static function deactivate(): void
    {
        TokenStore::forget();

        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions('lifetwin_wc_sync_product', [], 'lifetwin');
        }
    }
}

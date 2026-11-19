<?php
/**
 * Plugin Name: LifeTwin DPP for WooCommerce
 * Plugin URI: https://lifetwin.com.tr
 * Description: Ürünleri LifeTwin'e gönderir, DPP pasaport bağlantısını WooCommerce ürününe ekler.
 * Version: 1.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * WC requires at least: 8.0
 * WC tested up to: 10.0
 * Author: LifeTwin
 * Author URI: https://lifetwin.com.tr
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lifetwin-woocommerce
 * Domain Path: /languages
 *
 * @package LifeTwin\WooCommerce
 */

defined('ABSPATH') || exit;

define('LIFETWIN_WC_VERSION', '1.1.0');
define('LIFETWIN_WC_FILE', __FILE__);
define('LIFETWIN_WC_PATH', plugin_dir_path(__FILE__));
define('LIFETWIN_WC_URL', plugin_dir_url(__FILE__));

require_once LIFETWIN_WC_PATH.'src/Autoloader.php';
\LifeTwin\WooCommerce\Autoloader::register();

register_activation_hook(__FILE__, [\LifeTwin\WooCommerce\Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [\LifeTwin\WooCommerce\Plugin::class, 'deactivate']);

/**
 * HPOS (High-Performance Order Storage) uyumluluğu.
 *
 * Eklenti sipariş verisine dokunmaz; yine de WooCommerce'ün uyumluluk
 * bildirimini açıkça taşımak, mağaza sahibinin uyarı görmesini engeller.
 */
add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            LIFETWIN_WC_FILE,
            true
        );
    }
});

add_action('plugins_loaded', static function (): void {
    \LifeTwin\WooCommerce\Plugin::instance()->boot();
}, 20);

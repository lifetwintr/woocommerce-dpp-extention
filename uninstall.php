<?php
/**
 * Eklenti kaldırıldığında ayar, secret ve ürün meta'larını temizler.
 *
 * Ürün kayıtları silinmez; yalnız eklentinin yazdığı meta anahtarları
 * kaldırılır. LifeTwin'deki ürünler etkilenmez.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

require_once __DIR__.'/src/Autoloader.php';
\LifeTwin\WooCommerce\Autoloader::register();

/**
 * Tek site temizliği.
 */
$cleanup = static function (): void {
    \LifeTwin\WooCommerce\TokenStore::forget();
    \LifeTwin\WooCommerce\Settings::deleteAll();

    $metaKeys = [
        '_lifetwin_id',
        '_lifetwin_external_ref',
        '_lifetwin_status',
        '_lifetwin_dpp_url',
        '_lifetwin_dpp_api_url',
        '_lifetwin_vc_url',
        '_lifetwin_last_sync',
        '_lifetwin_last_error',
        '_lifetwin_last_error_at',
        '_lifetwin_content_hash',
        '_lifetwin_last_queued_at',
        '_lifetwin_product_type',
        '_lifetwin_brand',
        '_lifetwin_model_number',
        '_lifetwin_materials',
    ];

    foreach ($metaKeys as $key) {
        delete_metadata('post', 0, $key, '', true);
    }

    if (function_exists('as_unschedule_all_actions')) {
        as_unschedule_all_actions('lifetwin_wc_sync_product', [], 'lifetwin');
    }
};

if (is_multisite()) {
    $siteIds = get_sites(['fields' => 'ids']);

    foreach ($siteIds as $siteId) {
        switch_to_blog((int) $siteId);
        $cleanup();
        restore_current_blog();
    }
} else {
    $cleanup();
}

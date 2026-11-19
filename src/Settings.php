<?php

namespace LifeTwin\WooCommerce;

defined('ABSPATH') || exit;

final class Settings
{
    public const OPTION = 'lifetwin_wc_settings';

    public const SECRET_OPTION = 'lifetwin_wc_secret';

    public const WEBHOOK_SECRET_OPTION = 'lifetwin_wc_webhook_secret';

    public const WEBHOOK_SEEN_OPTION = 'lifetwin_wc_webhook_seen';

    public const CONNECTION_OPTION = 'lifetwin_wc_connection';

    public const LOG_OPTION = 'lifetwin_wc_log';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'api_url' => (string) apply_filters(
                'lifetwin_wc_api_url',
                'https://lifetwin.com.tr/api/v1'
            ),
            'api_key' => '',
            'company_id' => 0,
            'product_type' => 'textile',
            'sync_enabled' => true,
            'push_dpp_fields' => false,
            'secret_last_four' => '',
            'webhook_secret_last_four' => '',
            'debug_log' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(?string $key = null): mixed
    {
        $stored = get_option(self::OPTION, []);

        if (! is_array($stored)) {
            $stored = [];
        }

        $settings = wp_parse_args($stored, self::defaults());

        if ($key === null) {
            return $settings;
        }

        return $settings[$key] ?? null;
    }

    public static function apiUrl(): string
    {
        return untrailingslashit((string) self::get('api_url'));
    }

    public static function apiKey(): string
    {
        return (string) self::get('api_key');
    }

    public static function companyId(): int
    {
        return (int) self::get('company_id');
    }

    public static function setCompanyId(int $companyId): void
    {
        $settings = self::get();
        $settings['company_id'] = max(0, $companyId);

        update_option(self::OPTION, $settings, false);
    }

    public static function productType(): string
    {
        $type = (string) self::get('product_type');

        return in_array($type, array_keys(self::availableProductTypes()), true)
            ? $type
            : 'textile';
    }

    public static function syncEnabled(): bool
    {
        return (bool) self::get('sync_enabled');
    }

    public static function pushDppFields(): bool
    {
        return (bool) self::get('push_dpp_fields');
    }

    public static function secret(): string
    {
        return Crypto::decrypt((string) get_option(self::SECRET_OPTION, ''));
    }

    public static function secretLastFour(): string
    {
        return (string) self::get('secret_last_four');
    }

    /**
     * API anahtarı ve secret hazır mı?
     *
     * Bağlantı testi şirket seçilmeden de çalışabilmelidir; şirket kimliği
     * yalnız ürün gönderimi için gerekir.
     */
    public static function hasCredentials(): bool
    {
        return self::apiKey() !== '' && self::secret() !== '';
    }

    public static function isConfigured(): bool
    {
        return self::hasCredentials() && self::companyId() > 0;
    }

    public static function webhookSecret(): string
    {
        return Crypto::decrypt((string) get_option(self::WEBHOOK_SECRET_OPTION, ''));
    }

    public static function webhookSecretLastFour(): string
    {
        return (string) self::get('webhook_secret_last_four');
    }

    public static function webhookEndpointUrl(): string
    {
        return function_exists('rest_url')
            ? rest_url('lifetwin/v1/webhook')
            : home_url('/wp-json/lifetwin/v1/webhook');
    }

    /**
     * Bağlantı testinde okunan şirket listesi.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function companies(): array
    {
        $companies = self::connection()['companies'] ?? [];

        return is_array($companies) ? array_values(array_filter($companies, 'is_array')) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function connection(): array
    {
        $stored = get_option(self::CONNECTION_OPTION, []);

        return wp_parse_args(is_array($stored) ? $stored : [], [
            'connected' => false,
            'tenant_id' => 0,
            'modules' => [],
            'limits' => [],
            'tested_at' => '',
            'error' => '',
            'companies' => [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    public static function setConnection(array $connection): void
    {
        update_option(self::CONNECTION_OPTION, $connection, false);
    }

    public static function isConnected(): bool
    {
        $connection = self::connection();

        return ! empty($connection['connected']) && (int) $connection['tenant_id'] > 0;
    }

    /**
     * @return array<string, string>
     */
    public static function availableProductTypes(): array
    {
        $types = [
            'textile' => __('Tekstil', 'lifetwin-woocommerce'),
            'battery' => __('Batarya', 'lifetwin-woocommerce'),
            'electronics' => __('Elektronik', 'lifetwin-woocommerce'),
            'furniture' => __('Mobilya', 'lifetwin-woocommerce'),
            'automotive' => __('Otomotiv', 'lifetwin-woocommerce'),
            'construction' => __('İnşaat', 'lifetwin-woocommerce'),
        ];

        /** @var array<string, string> $filtered */
        $filtered = apply_filters('lifetwin_wc_product_types', $types);

        return is_array($filtered) ? $filtered : $types;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{ok: bool, errors: array<int, string>, secret_changed: bool}
     */
    public static function save(array $input): array
    {
        $current = self::get();

        $apiKey = sanitize_text_field((string) wp_unslash($input['api_key'] ?? $current['api_key']));
        $companyId = absint($input['company_id'] ?? $current['company_id']);
        $productType = sanitize_key((string) ($input['product_type'] ?? $current['product_type']));

        if (! array_key_exists($productType, self::availableProductTypes())) {
            $productType = 'textile';
        }

        $settings = [
            'api_url' => self::apiUrl(),
            'api_key' => $apiKey,
            'company_id' => $companyId,
            'product_type' => $productType,
            // Kısmi form gönderimleri (yalnız webhook secret gibi) diğer
            // seçenekleri sıfırlamasın diye anahtar yoksa mevcut değer korunur.
            'sync_enabled' => array_key_exists('sync_enabled', $input)
                ? ! empty($input['sync_enabled'])
                : (bool) $current['sync_enabled'],
            'push_dpp_fields' => array_key_exists('push_dpp_fields', $input)
                ? ! empty($input['push_dpp_fields'])
                : (bool) $current['push_dpp_fields'],
            'secret_last_four' => (string) $current['secret_last_four'],
            'webhook_secret_last_four' => (string) ($current['webhook_secret_last_four'] ?? ''),
            'debug_log' => array_key_exists('debug_log', $input)
                ? ! empty($input['debug_log'])
                : (bool) $current['debug_log'],
        ];

        $errors = [];
        $secretChanged = false;
        $plainSecret = trim((string) wp_unslash($input['api_secret'] ?? ''));

        if ($plainSecret !== '') {
            if (! Crypto::isAvailable()) {
                $errors[] = __('Sunucuda OpenSSL AES-256-GCM desteği yok; API secret kaydedilemez.', 'lifetwin-woocommerce');
            } else {
                $encrypted = Crypto::encrypt($plainSecret);

                if ($encrypted === '') {
                    $errors[] = __('API secret şifrelenemedi.', 'lifetwin-woocommerce');
                } else {
                    update_option(self::SECRET_OPTION, $encrypted, false);
                    $settings['secret_last_four'] = Crypto::lastFour($plainSecret);
                    $secretChanged = true;
                }
            }
        }

        $plainWebhookSecret = trim((string) wp_unslash($input['webhook_secret'] ?? ''));

        if ($plainWebhookSecret !== '') {
            if (! Crypto::isAvailable()) {
                $errors[] = __('Sunucuda OpenSSL AES-256-GCM desteği yok; webhook secret kaydedilemez.', 'lifetwin-woocommerce');
            } else {
                $encrypted = Crypto::encrypt($plainWebhookSecret);

                if ($encrypted === '') {
                    $errors[] = __('Webhook secret şifrelenemedi.', 'lifetwin-woocommerce');
                } else {
                    update_option(self::WEBHOOK_SECRET_OPTION, $encrypted, false);
                    $settings['webhook_secret_last_four'] = Crypto::lastFour($plainWebhookSecret);
                }
            }
        }

        $apiKeyChanged = $apiKey !== (string) $current['api_key'];

        // Bağlantı önbelleğindeki şirket listesinden seçim doğrulanır.
        $knownCompanyIds = array_map(
            static fn (array $company): int => (int) ($company['id'] ?? 0),
            self::companies()
        );

        if ($companyId > 0 && $knownCompanyIds !== [] && ! in_array($companyId, $knownCompanyIds, true)) {
            $errors[] = __('Seçilen şirket bağlantı testinde bulunan şirketler arasında değil.', 'lifetwin-woocommerce');
        }

        // Tek şirket varsa kullanıcıdan seçim istemeyiz.
        if ($companyId === 0 && count($knownCompanyIds) === 1) {
            $companyId = $knownCompanyIds[0];
            $settings['company_id'] = $companyId;
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'secret_changed' => $secretChanged];
        }

        update_option(self::OPTION, $settings, false);

        if ($apiKeyChanged || $secretChanged) {
            delete_option(self::CONNECTION_OPTION);
            TokenStore::forget();
        }

        return ['ok' => true, 'errors' => [], 'secret_changed' => $secretChanged];
    }

    public static function deleteAll(): void
    {
        delete_option(self::OPTION);
        delete_option(self::SECRET_OPTION);
        delete_option(self::WEBHOOK_SECRET_OPTION);
        delete_option(self::WEBHOOK_SEEN_OPTION);
        delete_option(self::CONNECTION_OPTION);
        delete_option(self::LOG_OPTION);
        TokenStore::forget();
    }
}

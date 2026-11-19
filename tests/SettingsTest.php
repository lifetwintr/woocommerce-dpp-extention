<?php

declare(strict_types=1);

namespace LifeTwin\WooCommerce\Tests;

use LifeTwin\WooCommerce\Crypto;
use LifeTwin\WooCommerce\Settings;

final class SettingsTest extends TestCase
{
    public function test_has_credentials_does_not_require_a_company(): void
    {
        update_option(Settings::SECRET_OPTION, Crypto::encrypt('lt_secret'));
        update_option(Settings::OPTION, array_merge(Settings::defaults(), [
            'api_key' => 'lt_test',
            'company_id' => 0,
        ]));

        self::assertTrue(Settings::hasCredentials());
        self::assertFalse(Settings::isConfigured());

        Settings::setCompanyId(7);

        self::assertTrue(Settings::isConfigured());
    }

    public function test_saving_only_the_webhook_secret_keeps_other_flags(): void
    {
        update_option(Settings::OPTION, array_merge(Settings::defaults(), [
            'api_key' => 'lt_test',
            'company_id' => 7,
            'sync_enabled' => true,
            'push_dpp_fields' => true,
            'debug_log' => true,
        ]));

        $result = Settings::save(['webhook_secret' => 'whsec_saved_secret']);

        self::assertTrue($result['ok']);

        $settings = Settings::get();

        self::assertSame('lt_test', $settings['api_key']);
        self::assertSame(7, $settings['company_id']);
        self::assertTrue($settings['sync_enabled']);
        self::assertTrue($settings['push_dpp_fields']);
        self::assertTrue($settings['debug_log']);
        self::assertSame('cret', $settings['webhook_secret_last_four']);
        self::assertSame('whsec_saved_secret', Settings::webhookSecret());
    }

    public function test_auto_selects_the_only_company_from_the_connection_cache(): void
    {
        update_option(Settings::OPTION, array_merge(Settings::defaults(), [
            'api_key' => 'lt_test',
            'company_id' => 0,
        ]));

        update_option(Settings::CONNECTION_OPTION, [
            'connected' => true,
            'tenant_id' => 42,
            'companies' => [
                ['id' => 11, 'name' => 'Tek Şirket'],
            ],
        ]);

        $result = Settings::save([]);

        self::assertTrue($result['ok']);
        self::assertSame(11, Settings::companyId());
    }

    public function test_rejects_a_company_that_is_not_in_the_connection_cache(): void
    {
        update_option(Settings::OPTION, Settings::defaults());
        update_option(Settings::CONNECTION_OPTION, [
            'connected' => true,
            'tenant_id' => 42,
            'companies' => [
                ['id' => 11, 'name' => 'Tek Şirket'],
            ],
        ]);

        $result = Settings::save(['company_id' => 99]);

        self::assertFalse($result['ok']);
        self::assertNotEmpty($result['errors']);
    }
}

<?php

declare(strict_types=1);

namespace LifeTwin\WooCommerce\Tests;

use LifeTwin\WooCommerce\Client;
use LifeTwin\WooCommerce\Crypto;
use LifeTwin\WooCommerce\Settings;
use WP_Error;

final class ClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Crypto::isAvailable()) {
            self::markTestSkipped('OpenSSL AES-256-GCM yok.');
        }

        update_option(Settings::OPTION, array_merge(Settings::defaults(), [
            'api_key' => 'lt_test',
            'company_id' => 7,
        ]));
        update_option(Settings::SECRET_OPTION, Crypto::encrypt('secret'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function response(int $status, array $data): array
    {
        return [
            'response' => ['code' => $status],
            'body' => (string) json_encode($data),
        ];
    }

    public function test_upsert_uses_token_and_authorization_header(): void
    {
        $calls = [];

        $transport = function (string $url, array $args) use (&$calls): array {
            $calls[] = $url;

            if (str_contains($url, '/auth/token')) {
                return self::response(200, [
                    'success' => true,
                    'data' => ['token' => 'tok', 'expires_in' => 3600],
                ]);
            }

            if (str_contains($url, '/products/upsert')) {
                self::assertSame('Bearer tok', $args['headers']['Authorization'] ?? null);

                return self::response(201, [
                    'success' => true,
                    'data' => [
                        'id' => 99,
                        'external_ref' => 'woo:1:42',
                        'dpp_url' => null,
                    ],
                ]);
            }

            return self::response(404, ['success' => false, 'code' => 'NOT_FOUND', 'message' => 'Not found']);
        };

        $client = new Client($transport);
        $result = $client->upsertProduct(['sku' => 'TSH-001']);

        self::assertIsArray($result);
        self::assertSame(99, $result['id']);
        self::assertCount(2, $calls);

        $client->upsertProduct(['sku' => 'TSH-001']);

        // Token cache sayesinde ikinci çağrıda /auth/token tekrar çağrılmaz.
        self::assertCount(3, $calls);
    }

    public function test_retries_once_after_401_with_a_fresh_token(): void
    {
        $tokenCalls = 0;
        $upsertCalls = 0;

        $transport = function (string $url, array $args) use (&$tokenCalls, &$upsertCalls): array {
            if (str_contains($url, '/auth/token')) {
                $tokenCalls++;

                return self::response(200, [
                    'success' => true,
                    'data' => ['token' => 'tok-'.$tokenCalls, 'expires_in' => 3600],
                ]);
            }

            $upsertCalls++;

            if ($upsertCalls === 1) {
                return self::response(401, [
                    'success' => false,
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Unauthenticated.',
                ]);
            }

            return self::response(200, [
                'success' => true,
                'data' => ['id' => 100, 'external_ref' => 'woo:1:42'],
            ]);
        };

        $client = new Client($transport);
        $result = $client->upsertProduct(['sku' => 'TSH-001']);

        self::assertIsArray($result);
        self::assertSame(100, $result['id']);
        self::assertSame(2, $tokenCalls);
        self::assertSame(2, $upsertCalls);
    }

    public function test_maps_api_error_to_wp_error(): void
    {
        $transport = function (string $url): array {
            if (str_contains($url, '/auth/token')) {
                return self::response(200, [
                    'success' => true,
                    'data' => ['token' => 'tok', 'expires_in' => 3600],
                ]);
            }

            return self::response(422, [
                'success' => false,
                'code' => 'VALIDATION_FAILED',
                'message' => 'GTIN 8-14 haneli olmalı.',
                'errors' => ['metadata.productIdentification.gtin' => ['Geçersiz GTIN.']],
            ]);
        };

        $client = new Client($transport);
        $result = $client->upsertProduct(['sku' => 'TSH-001']);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('lifetwin_validation_failed', $result->get_error_code());
        self::assertSame('GTIN 8-14 haneli olmalı.', $result->get_error_message());

        $data = $result->get_error_data();
        self::assertIsArray($data);
        self::assertSame(422, $data['status']);
        self::assertSame('VALIDATION_FAILED', $data['code']);
        self::assertArrayHasKey('request_id', $data);
    }

    public function test_connection_requires_dpp_module(): void
    {
        $transport = function (string $url): array {
            if (str_contains($url, '/auth/token')) {
                return self::response(200, [
                    'success' => true,
                    'data' => ['token' => 'tok', 'expires_in' => 3600],
                ]);
            }

            return self::response(200, [
                'success' => true,
                'data' => [
                    'tenant_id' => 7,
                    'modules' => ['dpp' => false],
                    'limits' => [],
                ],
            ]);
        };

        $client = new Client($transport);
        $result = $client->testConnection();

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('lifetwin_module_missing', $result->get_error_code());
    }
}

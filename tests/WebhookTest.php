<?php

declare(strict_types=1);

namespace LifeTwin\WooCommerce\Tests;

use LifeTwin\WooCommerce\Crypto;
use LifeTwin\WooCommerce\Settings;
use LifeTwin\WooCommerce\Webhook;
use WP_REST_Request;

final class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_webhook_test_secret';

    private function configure(): void
    {
        update_option(Settings::WEBHOOK_SECRET_OPTION, Crypto::encrypt(self::SECRET));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function request(array $payload, string $secret = self::SECRET, ?int $timestamp = null): WP_REST_Request
    {
        $body = (string) json_encode($payload);
        $timestamp ??= time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        $request = new WP_REST_Request($body);
        $request->set_header('x-lifetwin-signature', 't='.$timestamp.',v1='.$signature);

        return $request;
    }

    public function test_verifies_a_valid_signature(): void
    {
        $now = 1_700_000_000;
        $body = '{"event":"dpp.published"}';
        $signature = hash_hmac('sha256', $now.'.'.$body, self::SECRET);

        self::assertTrue(Webhook::verify(self::SECRET, $body, 't='.$now.',v1='.$signature, $now));
        self::assertFalse(Webhook::verify('whsec_other', $body, 't='.$now.',v1='.$signature, $now));
        self::assertFalse(Webhook::verify(self::SECRET, $body, 't='.$now.',v1=deadbeef', $now));
    }

    public function test_rejects_a_stale_timestamp(): void
    {
        $now = 1_700_000_000;
        $body = '{"event":"dpp.published"}';
        $signature = hash_hmac('sha256', $now.'.'.$body, self::SECRET);
        $header = 't='.$now.',v1='.$signature;

        self::assertFalse(Webhook::verify(self::SECRET, $body, $header, $now + 301));
        self::assertTrue(Webhook::verify(self::SECRET, $body, $header, $now + 300));
    }

    public function test_rejects_a_request_without_a_configured_secret(): void
    {
        $response = Webhook::handle($this->request([
            'id' => 'evt_test',
            'event' => 'dpp.published',
            'data' => [],
        ]));

        self::assertSame(503, $response->get_status());
    }

    public function test_rejects_an_invalid_signature(): void
    {
        $this->configure();

        $response = Webhook::handle($this->request([
            'id' => 'evt_test',
            'event' => 'dpp.published',
            'data' => [],
        ], 'whsec_wrong_secret'));

        self::assertSame(401, $response->get_status());
    }

    public function test_applies_dpp_published_to_the_matching_product(): void
    {
        $this->configure();
        $GLOBALS['lifetwin_test_posts'] = [42];

        update_post_meta(42, '_lifetwin_id', 900);
        update_post_meta(42, '_lifetwin_external_ref', 'woo:1:42');

        $response = Webhook::handle($this->request([
            'id' => 'evt_published_1',
            'event' => 'dpp.published',
            'data' => [
                'product_id' => 900,
                'external_ref' => 'woo:1:42',
                'dpp_url' => 'https://lifetwin.test/p/900',
                'dpp_api_url' => 'https://lifetwin.test/api/v1/products/900/dpp',
            ],
        ]));

        self::assertSame(200, $response->get_status());
        self::assertSame('https://lifetwin.test/p/900', get_post_meta(42, '_lifetwin_dpp_url', true));
        self::assertSame(
            'https://lifetwin.test/api/v1/products/900/dpp',
            get_post_meta(42, '_lifetwin_dpp_api_url', true)
        );
        self::assertSame('published', get_post_meta(42, '_lifetwin_status', true));
    }

    public function test_ignores_a_replayed_delivery_id(): void
    {
        $this->configure();
        $GLOBALS['lifetwin_test_posts'] = [42];

        update_post_meta(42, '_lifetwin_id', 900);
        update_post_meta(42, '_lifetwin_dpp_url', 'https://lifetwin.test/p/first');

        $payload = [
            'id' => 'evt_replay',
            'event' => 'dpp.published',
            'data' => ['product_id' => 900, 'dpp_url' => 'https://lifetwin.test/p/first'],
        ];

        Webhook::handle($this->request($payload))->get_status();

        $second = Webhook::handle($this->request([
            'id' => 'evt_replay',
            'event' => 'dpp.published',
            'data' => ['product_id' => 900, 'dpp_url' => 'https://lifetwin.test/p/second'],
        ]));

        self::assertSame(200, $second->get_status());
        self::assertSame(['ok' => true, 'duplicate' => true], $second->get_data());
        self::assertSame('https://lifetwin.test/p/first', get_post_meta(42, '_lifetwin_dpp_url', true));
    }
}

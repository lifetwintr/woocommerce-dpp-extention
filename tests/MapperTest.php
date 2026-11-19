<?php

declare(strict_types=1);

namespace LifeTwin\WooCommerce\Tests;

use LifeTwin\WooCommerce\Mapper;
use LifeTwin\WooCommerce\Settings;
use WP_Error;

final class MapperTest extends TestCase
{
    private function configure(): void
    {
        update_option(Settings::OPTION, array_merge(Settings::defaults(), [
            'api_key' => 'lt_test',
            'company_id' => 7,
            'product_type' => 'textile',
            'push_dpp_fields' => true,
        ]));
    }

    public function test_maps_woocommerce_product_to_connector_payload(): void
    {
        $this->configure();

        $productId = 42;

        $GLOBALS['lifetwin_test_products'][$productId] = new FakeProduct($productId, [
            'sku' => 'TSH-001',
            'name' => 'Organik Tişört',
            'description' => '<p>180 gr</p>',
            'status' => 'publish',
            'price' => '349.90',
            'stock' => 120,
            'weight' => 0.22,
            'length' => 30,
            'width' => 22,
            'height' => 2,
            'attributes' => [
                'gtin' => '8681234567890',
                'marka' => 'Acme',
            ],
        ]);

        $GLOBALS['wp_post_meta'][$productId]['_lifetwin_materials'] = json_encode([
            ['materialName' => 'Organic Cotton', 'percentage' => 95, 'origin' => 'TR'],
            ['materialName' => 'Elastane', 'percentage' => 5, 'origin' => 'TR'],
        ]);

        $GLOBALS['lifetwin_test_terms'][$productId] = ['Giyim', 'Tişört'];

        $payload = Mapper::toPayload($productId);

        self::assertIsArray($payload);
        self::assertSame('woo:1:42', $payload['external_ref']);
        self::assertSame('woocommerce', $payload['source']);
        self::assertSame('TSH-001', $payload['sku']);
        self::assertSame('Organik Tişört', $payload['name']);
        self::assertSame('180 gr', $payload['description']);
        self::assertSame(7, $payload['company_id']);
        self::assertSame('textile', $payload['type']);
        self::assertSame('active', $payload['status']);
        self::assertSame(349.90, $payload['price']);
        self::assertSame(120, $payload['stock']);
        self::assertSame(0.22, $payload['weight_kg']);
        self::assertSame(30.0, $payload['dimensions']['length']);
        self::assertSame(['Giyim', 'Tişört'], $payload['categories']);
        self::assertSame('8681234567890', $payload['metadata']['productIdentification']['gtin']);
        self::assertSame('Acme', $payload['metadata']['productIdentification']['brand']);
        self::assertSame(
            'Organic Cotton',
            $payload['metadata']['materialComposition']['materials'][0]['materialName']
        );
    }

    public function test_missing_sku_returns_error(): void
    {
        $this->configure();

        $GLOBALS['lifetwin_test_products'][43] = new FakeProduct(43, [
            'sku' => '',
            'name' => 'SKU yok',
        ]);

        $result = Mapper::toPayload(43);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('lifetwin_missing_sku', $result->get_error_code());
    }

    public function test_omits_metadata_when_gtin_is_missing(): void
    {
        $this->configure();

        $GLOBALS['lifetwin_test_products'][44] = new FakeProduct(44, [
            'sku' => 'NO-GTIN',
            'name' => 'GTIN yok',
            'price' => '10',
            'stock' => 1,
            'attributes' => [],
        ]);

        $payload = Mapper::toPayload(44);

        self::assertIsArray($payload);
        self::assertArrayNotHasKey('metadata', $payload);
    }

    public function test_product_type_can_be_overridden_per_product(): void
    {
        $this->configure();

        $GLOBALS['lifetwin_test_products'][45] = new FakeProduct(45, [
            'sku' => 'BAT-1',
            'name' => 'Batarya',
            'attributes' => ['gtin' => '8681234567890'],
        ]);

        update_post_meta(45, '_lifetwin_product_type', 'battery');

        $payload = Mapper::toPayload(45);

        self::assertIsArray($payload);
        self::assertSame('battery', $payload['type']);
    }

    public function test_includes_the_parent_reference_for_variations(): void
    {
        $this->configure();

        $GLOBALS['lifetwin_test_products'][46] = new FakeProduct(46, [
            'sku' => 'TSH-001-M',
            'name' => 'Organik Tişört - M',
            'price' => '349.90',
            'stock' => 10,
            'attributes' => [],
        ]);

        $payload = Mapper::toPayload(46, 'woo:1:42');

        self::assertIsArray($payload);
        self::assertSame('woo:1:42', $payload['external_parent_ref']);
    }

    public function test_omits_the_parent_reference_for_simple_products(): void
    {
        $this->configure();

        $GLOBALS['lifetwin_test_products'][47] = new FakeProduct(47, [
            'sku' => 'TSH-002',
            'name' => 'Organik Tişört 2',
            'attributes' => [],
        ]);

        $payload = Mapper::toPayload(47);

        self::assertIsArray($payload);
        self::assertArrayNotHasKey('external_parent_ref', $payload);
    }

    public function test_lists_the_children_of_a_variable_product(): void
    {
        $GLOBALS['lifetwin_test_products'][48] = new FakeProduct(48, [
            'type' => 'variable',
            'children' => ['481', 482, 0],
        ]);

        $GLOBALS['lifetwin_test_products'][49] = new FakeProduct(49, [
            'type' => 'simple',
        ]);

        self::assertSame([481, 482], Mapper::variationIds(48));
        self::assertSame([], Mapper::variationIds(49));
        self::assertSame([], Mapper::variationIds(999));
    }
}

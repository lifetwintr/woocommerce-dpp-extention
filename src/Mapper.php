<?php

namespace LifeTwin\WooCommerce;

defined('ABSPATH') || exit;

/**
 * WooCommerce ürününü LifeTwin Connector v1 payload'ına çevirir.
 *
 * Harici bağımlılık yoktur; WooCommerce fonksiyonları yoksa güvenli
 * varsayılanlara düşer. Çıktı `lifetwin_wc_product_payload` filtresiyle
 * genişletilebilir.
 */
final class Mapper
{
    /**
     * @return array<string, mixed>|\WP_Error
     */
    public static function toPayload(int $productId, ?string $parentRef = null): array|\WP_Error
    {
        if (! function_exists('wc_get_product')) {
            return new \WP_Error(
                'lifetwin_woocommerce_missing',
                __('WooCommerce ürün fonksiyonları bulunamadı.', 'lifetwin-woocommerce')
            );
        }

        $product = wc_get_product($productId);

        if (! $product) {
            return new \WP_Error(
                'lifetwin_product_not_found',
                __('Ürün bulunamadı.', 'lifetwin-woocommerce')
            );
        }

        $sku = trim((string) $product->get_sku());

        if ($sku === '') {
            return new \WP_Error(
                'lifetwin_missing_sku',
                __('Ürün SKU alanı boş. LifeTwin senkronu için SKU zorunludur.', 'lifetwin-woocommerce')
            );
        }

        $companyId = Settings::companyId();

        if ($companyId <= 0) {
            return new \WP_Error(
                'lifetwin_missing_company',
                __('LifeTwin şirket ID ayarlanmamış.', 'lifetwin-woocommerce')
            );
        }

        $gtin = self::gtin($product);
        $brand = self::brand($product);
        $metadata = self::metadata($product, $gtin, $brand);

        $payload = [
            'mode' => 'create_or_update',
            'source' => 'woocommerce',
            'external_ref' => self::externalRef($productId),
            'source_updated_at' => self::sourceUpdatedAt($product),
            'company_id' => $companyId,
            'sku' => $sku,
            'name' => (string) $product->get_name(),
            'description' => wp_strip_all_tags((string) $product->get_description()),
            'type' => self::productType($productId),
            'status' => self::status($product),
            'price' => (float) $product->get_price(),
            'stock' => self::stock($product),
            'currency_code' => function_exists('get_woocommerce_currency')
                ? (string) get_woocommerce_currency()
                : 'TRY',
            'weight_kg' => self::weightKg($product),
            'dimensions' => self::dimensions($product),
            'unit_of_measure' => 'piece',
            'categories' => self::categoryPath($productId),
        ];

        if ($parentRef !== null && $parentRef !== '') {
            $payload['external_parent_ref'] = $parentRef;
        }

        if ($metadata !== []) {
            $payload['metadata'] = $metadata;
        }

        /** @var array<string, mixed> $filtered */
        $filtered = apply_filters('lifetwin_wc_product_payload', $payload, $product, $productId);

        return is_array($filtered) ? $filtered : $payload;
    }

    /**
     * Değişken ürünün varyasyon kimlikleri.
     *
     * @return array<int, int>
     */
    public static function variationIds(int $productId): array
    {
        if (! function_exists('wc_get_product')) {
            return [];
        }

        $product = wc_get_product($productId);

        if (! $product
            || ! method_exists($product, 'get_type')
            || $product->get_type() !== 'variable'
            || ! method_exists($product, 'get_children')) {
            return [];
        }

        $children = $product->get_children();

        if (! is_array($children)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $children), static fn (int $id): bool => $id > 0));
    }

    public static function externalRef(int $productId): string
    {
        $blogId = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        $ref = sprintf('woo:%d:%d', $blogId, $productId);

        /** @var string $filtered */
        $filtered = apply_filters('lifetwin_wc_external_ref', $ref, $productId);

        return is_string($filtered) && $filtered !== '' ? $filtered : $ref;
    }

    public static function gtin(mixed $product): ?string
    {
        $productId = method_exists($product, 'get_id') ? (int) $product->get_id() : 0;

        $metaKeys = [
            '_global_unique_id',
            '_gtin',
            '_barcode',
            '_wpm_gtin_code',
            '_alg_gtin',
        ];

        foreach ($metaKeys as $key) {
            $value = $productId > 0 ? get_post_meta($productId, $key, true) : '';

            if ($gtin = self::normalizeGtin($value)) {
                return $gtin;
            }
        }

        foreach (['gtin', 'barcode', 'ean', 'upc'] as $attribute) {
            $value = method_exists($product, 'get_attribute') ? $product->get_attribute($attribute) : '';

            if ($gtin = self::normalizeGtin($value)) {
                return $gtin;
            }
        }

        return null;
    }

    public static function normalizeGtin(mixed $value): ?string
    {
        if (! is_scalar($value) || $value === '') {
            return null;
        }

        if (preg_match('/\d{8,14}/', (string) $value, $matches) !== 1) {
            return null;
        }

        return $matches[0];
    }

    public static function productType(int $productId): string
    {
        $types = Settings::availableProductTypes();
        $override = sanitize_key((string) get_post_meta($productId, '_lifetwin_product_type', true));

        if ($override !== '' && array_key_exists($override, $types)) {
            return $override;
        }

        return Settings::productType();
    }

    public static function brand(mixed $product): ?string
    {
        $productId = method_exists($product, 'get_id') ? (int) $product->get_id() : 0;

        foreach (['_lifetwin_brand', '_brand'] as $key) {
            $value = $productId > 0 ? trim((string) get_post_meta($productId, $key, true)) : '';

            if ($value !== '') {
                return $value;
            }
        }

        foreach (['marka', 'brand'] as $attribute) {
            $value = method_exists($product, 'get_attribute') ? trim((string) $product->get_attribute($attribute)) : '';

            if ($value !== '') {
                return $value;
            }
        }

        if ($productId > 0 && function_exists('wp_get_post_terms')) {
            $terms = wp_get_post_terms($productId, 'product_brand', ['fields' => 'names']);

            if (! is_wp_error($terms) && is_array($terms) && isset($terms[0])) {
                return (string) $terms[0];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function metadata(mixed $product, ?string $gtin, ?string $brand): array
    {
        // GTIN yoksa metadata göndermiyoruz: LifeTwin metadata varsa GTIN
        // zorunlu tutar. Ürün yine oluşturulur, pasaport alanları boş kalır.
        if ($gtin === null) {
            return [];
        }

        $metadata = [
            'productIdentification' => [
                'gtin' => $gtin,
            ],
        ];

        if ($brand !== null) {
            $metadata['productIdentification']['brand'] = $brand;
        }

        $productId = method_exists($product, 'get_id') ? (int) $product->get_id() : 0;
        $model = $productId > 0
            ? trim((string) get_post_meta($productId, '_lifetwin_model_number', true))
            : '';

        if ($model !== '') {
            $metadata['productIdentification']['modelNumber'] = $model;
        }

        if (Settings::pushDppFields()) {
            $materials = self::materials($productId);

            if ($materials !== []) {
                $metadata['materialComposition'] = ['materials' => $materials];
            }
        }

        return $metadata;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function materials(int $productId): array
    {
        $raw = $productId > 0 ? get_post_meta($productId, '_lifetwin_materials', true) : '';

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($raw)) {
            return [];
        }

        $materials = [];

        foreach ($raw as $material) {
            if (! is_array($material)) {
                continue;
            }

            $name = trim((string) ($material['materialName'] ?? $material['name'] ?? ''));
            $percentage = $material['percentage'] ?? null;

            if ($name === '' || ! is_numeric($percentage)) {
                continue;
            }

            $materials[] = [
                'materialName' => $name,
                'percentage' => (float) $percentage,
                'recycledContentPercentage' => is_numeric($material['recycledContentPercentage'] ?? null)
                    ? (float) $material['recycledContentPercentage']
                    : 0,
                'origin' => strtoupper((string) ($material['origin'] ?? 'TR')),
            ];
        }

        return $materials;
    }

    public static function status(mixed $product): string
    {
        $status = method_exists($product, 'get_status') ? (string) $product->get_status() : 'publish';

        return match ($status) {
            'publish' => 'active',
            'draft', 'pending' => 'draft',
            default => 'inactive',
        };
    }

    public static function stock(mixed $product): ?int
    {
        if (! method_exists($product, 'get_stock_quantity')) {
            return null;
        }

        $stock = $product->get_stock_quantity();

        return is_numeric($stock) ? (int) $stock : null;
    }

    public static function weightKg(mixed $product): ?float
    {
        $weight = method_exists($product, 'get_weight') ? $product->get_weight() : null;

        if (! is_numeric($weight) || (float) $weight <= 0) {
            return null;
        }

        if (function_exists('wc_get_weight')) {
            $converted = wc_get_weight((float) $weight, 'kg');

            return is_numeric($converted) ? round((float) $converted, 3) : null;
        }

        $unit = function_exists('get_option') ? (string) get_option('woocommerce_weight_unit', 'kg') : 'kg';
        $factor = match ($unit) {
            'g' => 0.001,
            'lbs' => 0.45359237,
            'oz' => 0.0283495231,
            default => 1.0,
        };

        return round((float) $weight * $factor, 3);
    }

    /**
     * @return array<string, float|string>|null
     */
    public static function dimensions(mixed $product): ?array
    {
        $length = method_exists($product, 'get_length') ? $product->get_length() : null;
        $width = method_exists($product, 'get_width') ? $product->get_width() : null;
        $height = method_exists($product, 'get_height') ? $product->get_height() : null;

        if (! is_numeric($length) && ! is_numeric($width) && ! is_numeric($height)) {
            return null;
        }

        $dimensions = [
            'length' => self::toCentimeters($length),
            'width' => self::toCentimeters($width),
            'height' => self::toCentimeters($height),
            'unit' => 'cm',
        ];

        return array_filter($dimensions, fn ($value) => $value !== null);
    }

    public static function sourceUpdatedAt(mixed $product): string
    {
        if (method_exists($product, 'get_date_modified')) {
            $modified = $product->get_date_modified();

            if (is_object($modified) && method_exists($modified, 'date')) {
                return $modified->date(DATE_ATOM);
            }
        }

        $productId = method_exists($product, 'get_id') ? (int) $product->get_id() : 0;

        return $productId > 0
            ? (string) get_post_modified_time('c', true, $productId)
            : gmdate(DATE_ATOM);
    }

    /**
     * @return array<int, string>
     */
    public static function categoryPath(int $productId): array
    {
        if ($productId <= 0 || ! function_exists('wp_get_post_terms')) {
            return [];
        }

        $terms = wp_get_post_terms($productId, 'product_cat', ['fields' => 'names']);

        if (is_wp_error($terms) || ! is_array($terms)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $terms)));
    }

    private static function toCentimeters(mixed $value): ?float
    {
        if (! is_numeric($value) || (float) $value <= 0) {
            return null;
        }

        if (function_exists('wc_get_dimension')) {
            $converted = wc_get_dimension((float) $value, 'cm');

            return is_numeric($converted) ? round((float) $converted, 2) : null;
        }

        $unit = function_exists('get_option') ? (string) get_option('woocommerce_dimension_unit', 'cm') : 'cm';
        $factor = match ($unit) {
            'mm' => 0.1,
            'm' => 100.0,
            'in' => 2.54,
            'yd' => 91.44,
            default => 1.0,
        };

        return round((float) $value * $factor, 2);
    }
}

<?php

declare(strict_types=1);

namespace LifeTwin\WooCommerce\Tests;

final class FakeProduct
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private readonly int $id,
        private readonly array $data = [],
    ) {}

    public function get_id(): int
    {
        return $this->id;
    }

    public function get_sku(): string
    {
        return (string) ($this->data['sku'] ?? '');
    }

    public function get_name(): string
    {
        return (string) ($this->data['name'] ?? '');
    }

    public function get_description(): string
    {
        return (string) ($this->data['description'] ?? '');
    }

    public function get_status(): string
    {
        return (string) ($this->data['status'] ?? 'publish');
    }

    public function get_price(): string
    {
        return (string) ($this->data['price'] ?? '0');
    }

    public function get_stock_quantity(): ?int
    {
        return isset($this->data['stock']) ? (int) $this->data['stock'] : null;
    }

    public function get_weight(): string
    {
        return (string) ($this->data['weight'] ?? '');
    }

    public function get_length(): string
    {
        return (string) ($this->data['length'] ?? '');
    }

    public function get_width(): string
    {
        return (string) ($this->data['width'] ?? '');
    }

    public function get_height(): string
    {
        return (string) ($this->data['height'] ?? '');
    }

    public function get_type(): string
    {
        return (string) ($this->data['type'] ?? 'simple');
    }

    /**
     * @return array<int, int>
     */
    public function get_children(): array
    {
        $children = $this->data['children'] ?? [];

        return is_array($children) ? array_map('intval', $children) : [];
    }

    public function get_attribute(string $name): string
    {
        $attributes = $this->data['attributes'] ?? [];

        return is_array($attributes) ? (string) ($attributes[$name] ?? '') : '';
    }

    public function get_date_modified(): object
    {
        return new class
        {
            public function date(string $format): string
            {
                return '2026-11-17T10:00:00+00:00';
            }
        };
    }
}

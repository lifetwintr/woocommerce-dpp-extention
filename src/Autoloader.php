<?php

namespace LifeTwin\WooCommerce;

defined('ABSPATH') || exit;

/**
 * Composer olmadan çalışan PSR-4 autoloader.
 *
 * Üretimde eklenti tek zip olarak kurulabilsin diye vendor/ gerekmez.
 * Geliştirmede composer autoload da kullanılabilir; bu sınıf her iki
 * durumda güvenlidir.
 */
final class Autoloader
{
    private const PREFIX = 'LifeTwin\\WooCommerce\\';

    public static function register(): void
    {
        spl_autoload_register([self::class, 'load']);
    }

    public static function load(string $class): void
    {
        if (! str_starts_with($class, self::PREFIX)) {
            return;
        }

        $relative = substr($class, strlen(self::PREFIX));
        $file = dirname(__DIR__).'/src/'.str_replace('\\', '/', $relative).'.php';

        if (is_readable($file)) {
            require_once $file;
        }
    }
}

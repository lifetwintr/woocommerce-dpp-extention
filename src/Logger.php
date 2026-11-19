<?php

namespace LifeTwin\WooCommerce;

defined('ABSPATH') || exit;

/**
 * WooCommerce loglarına ve panelde gösterilen son 20 kayda yazar.
 *
 * Secret, token ve Authorization değerleri hiçbir kayda girmez.
 */
final class Logger
{
    private const SOURCE = 'lifetwin-dpp';

    private const MAX_ENTRIES = 20;

    /**
     * @param  array<string, mixed>  $context
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        $level = in_array($level, ['debug', 'info', 'notice', 'warning', 'error'], true)
            ? $level
            : 'info';

        $context = self::redact($context);

        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();

            if (is_object($logger) && method_exists($logger, 'log')) {
                $logger->log($level, $message, [
                    'source' => self::SOURCE,
                    'context' => $context,
                ]);
            }
        }

        $entries = get_option(Settings::LOG_OPTION, []);
        $entries = is_array($entries) ? $entries : [];

        array_unshift($entries, [
            'time' => gmdate('c'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ]);

        update_option(Settings::LOG_OPTION, array_slice($entries, 0, self::MAX_ENTRIES), false);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function recent(): array
    {
        $entries = get_option(Settings::LOG_OPTION, []);

        return is_array($entries) ? $entries : [];
    }

    public static function clear(): void
    {
        update_option(Settings::LOG_OPTION, [], false);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function redact(array $context): array
    {
        $blocked = ['api_secret', 'secret', 'token', 'authorization', 'password'];

        foreach ($context as $key => $value) {
            if (in_array(strtolower((string) $key), $blocked, true)) {
                $context[$key] = '***';
            } elseif (is_array($value)) {
                $context[$key] = self::redact($value);
            }
        }

        return $context;
    }
}

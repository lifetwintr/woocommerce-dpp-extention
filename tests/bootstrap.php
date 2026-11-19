<?php
/**
 * WordPress ve WooCommerce fonksiyonlarının test stub'ları.
 *
 * Gerçek WordPress kurulumu gerekmez; yalnız eklentinin kullandığı
 * fonksiyonlar taklit edilir. Üretim kodunda bu dosya yüklenmez.
 */

declare(strict_types=1);

define('ABSPATH', __DIR__.'/');
define('LIFETWIN_WC_VERSION', '1.0.0');
define('LIFETWIN_WC_PATH', dirname(__DIR__).'/');
define('LIFETWIN_WC_URL', 'https://example.test/wp-content/plugins/lifetwin-woocommerce/');
define('DAY_IN_SECONDS', 86400);
define('AUTH_KEY', 'test-auth-key');
define('SECURE_AUTH_KEY', 'test-secure-key');

$GLOBALS['wp_options'] = [];
$GLOBALS['wp_transients'] = [];
$GLOBALS['wp_post_meta'] = [];
$GLOBALS['wp_filters'] = [];
$GLOBALS['lifetwin_test_products'] = [];
$GLOBALS['lifetwin_test_terms'] = [];
$GLOBALS['lifetwin_test_posts'] = [];
$GLOBALS['wp_rest_routes'] = [];
$GLOBALS['wp_actions'] = [];

if (! class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        /** @param mixed $data */
        public function __construct(private $data = null, private int $status = 200) {}

        public function get_data(): mixed
        {
            return $this->data;
        }

        public function get_status(): int
        {
            return $this->status;
        }
    }
}

if (! class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        /** @var array<string, string> */
        private array $headers = [];

        public function __construct(private string $body = '') {}

        public function set_header(string $key, string $value): void
        {
            $this->headers[strtolower($key)] = $value;
        }

        public function get_header(string $key): string
        {
            return $this->headers[strtolower($key)] ?? '';
        }

        public function get_body(): string
        {
            return $this->body;
        }
    }
}

if (! class_exists('WP_Error')) {
    class WP_Error
    {
        /** @var array<string, mixed> */
        private array $errors = [];

        /** @var mixed */
        private $data;

        public function __construct(string $code = '', string $message = '', mixed $data = null)
        {
            if ($code !== '') {
                $this->errors[$code][] = $message;
            }

            $this->data = $data;
        }

        public function get_error_code(): string
        {
            return (string) (array_key_first($this->errors) ?? '');
        }

        public function get_error_message(): string
        {
            $code = $this->get_error_code();

            return (string) ($this->errors[$code][0] ?? '');
        }

        public function get_error_data(): mixed
        {
            return $this->data;
        }
    }
}

if (! function_exists('is_wp_error')) {
    function is_wp_error(mixed $thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

if (! function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (! function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (! function_exists('esc_attr__')) {
    function esc_attr__(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (! function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return $text;
    }
}

if (! function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return $text;
    }
}

if (! function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return $url;
    }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $value, int $flags = 0): string|false
    {
        return json_encode($value, $flags);
    }
}

if (! function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4(): string
    {
        return bin2hex(random_bytes(16));
    }
}

if (! function_exists('get_option')) {
    function get_option(string $key, mixed $default = false): mixed
    {
        if ($key === 'woocommerce_weight_unit') {
            return 'kg';
        }

        if ($key === 'woocommerce_dimension_unit') {
            return 'cm';
        }

        return $GLOBALS['wp_options'][$key] ?? $default;
    }
}

if (! function_exists('update_option')) {
    function update_option(string $key, mixed $value, mixed $autoload = null): bool
    {
        $GLOBALS['wp_options'][$key] = $value;

        return true;
    }
}

if (! function_exists('add_option')) {
    function add_option(string $key, mixed $value = '', string $deprecated = '', mixed $autoload = null): bool
    {
        if (array_key_exists($key, $GLOBALS['wp_options'])) {
            return false;
        }

        $GLOBALS['wp_options'][$key] = $value;

        return true;
    }
}

if (! function_exists('delete_option')) {
    function delete_option(string $key): bool
    {
        unset($GLOBALS['wp_options'][$key]);

        return true;
    }
}

if (! function_exists('get_transient')) {
    function get_transient(string $key): mixed
    {
        return $GLOBALS['wp_transients'][$key] ?? false;
    }
}

if (! function_exists('set_transient')) {
    function set_transient(string $key, mixed $value, int $expiration = 0): bool
    {
        $GLOBALS['wp_transients'][$key] = $value;

        return true;
    }
}

if (! function_exists('delete_transient')) {
    function delete_transient(string $key): bool
    {
        unset($GLOBALS['wp_transients'][$key]);

        return true;
    }
}

if (! function_exists('get_post_meta')) {
    function get_post_meta(int $postId, string $key = '', bool $single = false): mixed
    {
        if ($key === '') {
            return $GLOBALS['wp_post_meta'][$postId] ?? [];
        }

        $value = $GLOBALS['wp_post_meta'][$postId][$key] ?? '';

        return $single ? $value : ($value === '' ? [] : [$value]);
    }
}

if (! function_exists('update_post_meta')) {
    function update_post_meta(int $postId, string $key, mixed $value): bool
    {
        $GLOBALS['wp_post_meta'][$postId][$key] = $value;

        return true;
    }
}

if (! function_exists('delete_post_meta')) {
    function delete_post_meta(int $postId, string $key): bool
    {
        unset($GLOBALS['wp_post_meta'][$postId][$key]);

        return true;
    }
}

if (! function_exists('apply_filters')) {
    function apply_filters(string $tag, mixed $value, mixed ...$args): mixed
    {
        foreach ($GLOBALS['wp_filters'][$tag] ?? [] as $callback) {
            $value = $callback($value, ...$args);
        }

        return $value;
    }
}

if (! function_exists('add_filter')) {
    function add_filter(string $tag, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        $GLOBALS['wp_filters'][$tag][] = $callback;

        return true;
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(mixed $value): string
    {
        return trim(strip_tags((string) $value));
    }
}

if (! function_exists('sanitize_key')) {
    function sanitize_key(mixed $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', (string) $value));
    }
}

if (! function_exists('absint')) {
    function absint(mixed $value): int
    {
        return abs((int) $value);
    }
}

if (! function_exists('wp_unslash')) {
    function wp_unslash(mixed $value): mixed
    {
        return $value;
    }
}

if (! function_exists('wp_parse_args')) {
    function wp_parse_args(mixed $args, array $defaults = []): array
    {
        if (! is_array($args)) {
            $args = [];
        }

        return array_merge($defaults, $args);
    }
}

if (! function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $text): string
    {
        return trim(strip_tags($text));
    }
}

if (! function_exists('untrailingslashit')) {
    function untrailingslashit(string $value): string
    {
        return rtrim($value, '/\\');
    }
}

if (! function_exists('register_rest_route')) {
    function register_rest_route(string $namespace, string $route, array $args = []): bool
    {
        $GLOBALS['wp_rest_routes'][$namespace.$route] = $args;

        return true;
    }
}

if (! function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        $GLOBALS['wp_actions'][$hook][] = $callback;

        return true;
    }
}

if (! function_exists('get_posts')) {
    function get_posts(array $args = []): array
    {
        return $GLOBALS['lifetwin_test_posts'] ?? [];
    }
}

if (! function_exists('rest_url')) {
    function rest_url(string $path = ''): string
    {
        return 'https://example.test/wp-json/'.ltrim($path, '/');
    }
}

if (! function_exists('home_url')) {
    function home_url(string $path = ''): string
    {
        return 'https://example.test'.($path === '' ? '' : '/'.ltrim($path, '/'));
    }
}

if (! function_exists('esc_js')) {
    function esc_js(string $text): string
    {
        return $text;
    }
}

if (! function_exists('get_current_blog_id')) {
    function get_current_blog_id(): int
    {
        return 1;
    }
}

if (! function_exists('get_woocommerce_currency')) {
    function get_woocommerce_currency(): string
    {
        return 'TRY';
    }
}

if (! function_exists('wc_get_weight')) {
    function wc_get_weight(float $weight, string $toUnit = 'kg'): float
    {
        return $weight;
    }
}

if (! function_exists('wc_get_dimension')) {
    function wc_get_dimension(float $dimension, string $toUnit = 'cm'): float
    {
        return $dimension;
    }
}

if (! function_exists('get_post_modified_time')) {
    function get_post_modified_time(string $format = 'U', bool $gmt = false, int $postId = 0): string
    {
        return '2026-11-17T10:00:00+00:00';
    }
}

if (! function_exists('wp_get_post_terms')) {
    function wp_get_post_terms(int $postId, string $taxonomy, array $args = []): array
    {
        return $GLOBALS['lifetwin_test_terms'][$postId] ?? [];
    }
}

if (! function_exists('wc_get_product')) {
    function wc_get_product(int $productId): mixed
    {
        return $GLOBALS['lifetwin_test_products'][$productId] ?? false;
    }
}

if (! function_exists('wp_salt')) {
    function wp_salt(string $scheme = 'auth'): string
    {
        return 'test-salt-'.$scheme;
    }
}

require_once dirname(__DIR__).'/src/Autoloader.php';
\LifeTwin\WooCommerce\Autoloader::register();

require_once __DIR__.'/Support/TestCase.php';
require_once __DIR__.'/Support/FakeProduct.php';

<?php

namespace LifeTwin\WooCommerce;

defined('ABSPATH') || exit;

/**
 * LifeTwin API v1 istemcisi.
 *
 * Tüm HTTP çağrıları buradan geçer. Token yenileme, hata eşleme ve
 * `X-Request-Id` üretimi tek yerde tutulur. Transport enjekte edilebilir;
 * böylece testlerde gerçek ağ çağrısı yapılmaz.
 */
final class Client
{
    /** @var callable|null */
    private $transport;

    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport;
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function testConnection(): array|\WP_Error
    {
        $token = $this->token();

        if (is_wp_error($token)) {
            return $token;
        }

        $capabilities = $this->request('GET', '/account/capabilities');

        if (is_wp_error($capabilities)) {
            return $capabilities;
        }

        $modules = is_array($capabilities['modules'] ?? null) ? $capabilities['modules'] : [];

        if (empty($modules['dpp'])) {
            return new \WP_Error(
                'lifetwin_module_missing',
                __('LifeTwin paketinizde DPP modülü açık değil.', 'lifetwin-woocommerce'),
                ['status' => 403, 'code' => 'MODULE_NOT_IN_PLAN']
            );
        }

        return [
            'tenant_id' => (int) ($capabilities['tenant_id'] ?? 0),
            'modules' => $modules,
            'limits' => is_array($capabilities['limits'] ?? null) ? $capabilities['limits'] : [],
            'features' => is_array($capabilities['features'] ?? null) ? $capabilities['features'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|\WP_Error
     */
    public function upsertProduct(array $payload): array|\WP_Error
    {
        return $this->request('POST', '/products/upsert', $payload);
    }

    /**
     * Erişilebilir şirketler (`dpp:read`).
     *
     * @return array<int, array<string, mixed>>|\WP_Error
     */
    public function companies(): array|\WP_Error
    {
        return $this->request('GET', '/companies');
    }

    /**
     * @return array<int, array<string, mixed>>|\WP_Error
     */
    public function types(): array|\WP_Error
    {
        return $this->request('GET', '/dpp/types');
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function schema(string $type): array|\WP_Error
    {
        return $this->request('GET', '/dpp/schema/'.rawurlencode($type));
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>|\WP_Error
     */
    public function request(
        string $method,
        string $path,
        ?array $body = null,
        array $query = [],
        bool $retried = false,
    ): array|\WP_Error {
        if (! Settings::hasCredentials()) {
            return new \WP_Error(
                'lifetwin_not_configured',
                __('LifeTwin API anahtarı ve secret girilmemiş.', 'lifetwin-woocommerce')
            );
        }

        $token = $this->token();

        if (is_wp_error($token)) {
            return $token;
        }

        $url = $this->url($path, $query);
        $requestId = wp_generate_uuid4();

        $args = [
            'method' => strtoupper($method),
            'timeout' => 15,
            'redirection' => 3,
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => 'LifeTwin-WooCommerce/'.LIFETWIN_WC_VERSION,
                'X-Request-Id' => $requestId,
            ],
        ];

        if ($body !== null) {
            $args['body'] = (string) wp_json_encode($body);
        }

        $response = $this->send($url, $args);

        if (is_wp_error($response)) {
            return new \WP_Error(
                'lifetwin_network',
                $response->get_error_message(),
                ['request_id' => $requestId]
            );
        }

        $status = $this->responseCode($response);
        $raw = $this->responseBody($response);

        if ($status === 401 && ! $retried) {
            TokenStore::forget();

            return $this->request($method, $path, $body, $query, true);
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return new \WP_Error(
                'lifetwin_invalid_response',
                __('LifeTwin geçersiz bir yanıt döndürdü.', 'lifetwin-woocommerce'),
                ['status' => $status, 'request_id' => $requestId]
            );
        }

        if ($status >= 200 && $status < 300 && ! isset($decoded['success'])) {
            return is_array($decoded['data'] ?? null) ? $decoded['data'] : $decoded;
        }

        if ($status >= 200 && $status < 300 && ($decoded['success'] ?? false) === true) {
            return is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
        }

        $code = (string) ($decoded['code'] ?? 'HTTP_'.$status);
        $message = (string) ($decoded['message'] ?? __('LifeTwin isteği başarısız oldu.', 'lifetwin-woocommerce'));

        return new \WP_Error(
            'lifetwin_'.strtolower($code),
            $message,
            [
                'status' => $status,
                'code' => $code,
                'errors' => is_array($decoded['errors'] ?? null) ? $decoded['errors'] : [],
                'meta' => is_array($decoded['meta'] ?? null) ? $decoded['meta'] : [],
                'request_id' => $requestId,
            ]
        );
    }

    /**
     * @return string|\WP_Error
     */
    public function token(): string|\WP_Error
    {
        $cached = TokenStore::get();

        if ($cached !== null) {
            return $cached;
        }

        if (! Settings::hasCredentials()) {
            return new \WP_Error(
                'lifetwin_not_configured',
                __('LifeTwin API anahtarı ve secret girilmemiş.', 'lifetwin-woocommerce')
            );
        }

        $requestId = wp_generate_uuid4();
        $response = $this->send($this->url('/auth/token'), [
            'method' => 'POST',
            'timeout' => 15,
            'redirection' => 2,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => 'LifeTwin-WooCommerce/'.LIFETWIN_WC_VERSION,
                'X-Request-Id' => $requestId,
            ],
            'body' => (string) wp_json_encode([
                'api_key' => Settings::apiKey(),
                'api_secret' => Settings::secret(),
            ]),
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error(
                'lifetwin_network',
                $response->get_error_message(),
                ['request_id' => $requestId]
            );
        }

        $status = $this->responseCode($response);
        $decoded = json_decode($this->responseBody($response), true);

        $token = is_array($decoded) ? (string) ($decoded['data']['token'] ?? '') : '';

        if ($status < 200 || $status >= 300 || $token === '') {
            $code = is_array($decoded) ? (string) ($decoded['code'] ?? 'HTTP_'.$status) : 'HTTP_'.$status;

            return new \WP_Error(
                'lifetwin_'.strtolower($code),
                is_array($decoded)
                    ? (string) ($decoded['message'] ?? __('LifeTwin kimlik doğrulaması başarısız.', 'lifetwin-woocommerce'))
                    : __('LifeTwin kimlik doğrulaması başarısız.', 'lifetwin-woocommerce'),
                ['status' => $status, 'code' => $code, 'request_id' => $requestId]
            );
        }

        $expiresIn = (int) ($decoded['data']['expires_in'] ?? 3600);
        TokenStore::put($token, $expiresIn > 0 ? $expiresIn : 3600);

        return $token;
    }

    /**
     * @param  array<string, scalar>  $query
     */
    private function url(string $path, array $query = []): string
    {
        $url = Settings::apiUrl().'/'.ltrim($path, '/');

        if ($query === []) {
            return $url;
        }

        return $url.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function send(string $url, array $args): mixed
    {
        $transport = $this->transport ?? 'wp_remote_request';

        return call_user_func($transport, $url, $args);
    }

    private function responseCode(mixed $response): int
    {
        if (is_array($response)) {
            return (int) ($response['response']['code'] ?? 0);
        }

        return function_exists('wp_remote_retrieve_response_code')
            ? (int) wp_remote_retrieve_response_code($response)
            : 0;
    }

    private function responseBody(mixed $response): string
    {
        if (is_array($response)) {
            return (string) ($response['body'] ?? '');
        }

        return function_exists('wp_remote_retrieve_body')
            ? (string) wp_remote_retrieve_body($response)
            : '';
    }
}

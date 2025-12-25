<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Arr;
use GuzzleHttp\TransferStats;
use Throwable;


class OmarinoService
{
    protected string $baseUrl;
    protected string $authUserId;
    protected string $apiKey;

    protected int $timeout;
    protected int $retryAttempts;
    protected int $retryDelayMs; // milliseconds
    protected bool $debug;

    protected string $userAgent;

    protected string $circuitPrefix = 'omarino:circuit:';

    protected array $defaultOptions = [
        'verify' => true,
        'as_form' => true,
        'auth_placement' => 'query', 
        'cache_ttl' => 0,
    ];

    protected string $debugLogPath;

    public function __construct(array $overrides = [])
    {
        $this->baseUrl = rtrim((string) config('services.omarino.url', env('OMARINO_URL', 'https://httpapi.com')), '/');
        $this->authUserId = (string) config('services.omarino.username', env('OMARINO_USERNAME', ''));
        $this->apiKey = (string) config('services.omarino.api_key', env('OMARINO_API_KEY', ''));
        $this->timeout = (int) config('services.omarino.timeout', env('OMARINO_TIMEOUT', 60));
        $this->retryAttempts = (int) config('services.omarino.retry_attempts', env('OMARINO_RETRY_ATTEMPTS', 3));
        $this->retryDelayMs = (int) config('services.omarino.retry_delay_ms', env('OMARINO_RETRY_DELAY_MS', 500));
        $this->debug = (bool) config('services.omarino.debug', false);
        $this->userAgent = 'Mozilla/5.0 (compatible; OmarinoAPI/1.0; +https://pac.com)';
        $this->debugLogPath = storage_path('logs/omarino-debug.log');

        foreach ($overrides as $k => $v) {
            if (property_exists($this, $k)) {
                $this->$k = $v;
            }
        }
    }
protected function detectCloudflareHtml(string $raw, array $headers = []): ?array
{
    $lower = strtolower($raw);
    $signs = ['attention required', 'checking your browser', 'sorry, you have been blocked', 'cloudflare', 'cf_ray', 'cf-ray', 'please enable cookies'];
    foreach ($signs as $s) {
        if (strpos($lower, $s) !== false) {
            // try extract ray id
            preg_match('/Cloudflare Ray ID:\\s*<strong[^>]*>([0-9a-fA-F]+)<\\/strong>/i', $raw, $m);
            $ray = $m[1] ?? null;
            return ['blocked' => true, 'reason' => 'cloudflare_html', 'ray_id' => $ray];
        }
    }
    $hdrs = array_change_key_case($headers, CASE_LOWER);
    if (!empty($hdrs['cf-ray']) || !empty($hdrs['cf-request-id']) || (isset($hdrs['server']) && stripos($hdrs['server'], 'cloudflare') !== false)) {
        return ['blocked' => true, 'reason' => 'cloudflare_headers', 'ray_id' => $hdrs['cf-ray'] ?? null];
    }
    return null;
}



    protected function debugLog(string $title, array $data = []): void
    {
        if (! $this->debug) {
            return;
        }

        $entry = [
            'time' => date('c'),
            'title' => $title,
            'data' => $data,
        ];


        Log::debug('[OMARINO] ' . $title, $data);


        try {
            @file_put_contents($this->debugLogPath, json_encode($entry, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
        }
    }

    protected function maskAuth(array $arr): array
    {
        $copy = $arr;
        $sensitive = ['api-key','apikey','api_key','auth-userid','auth_userid','username','password'];
        array_walk_recursive($copy, function (&$v, $k) use ($sensitive) {
            if (in_array($k, $sensitive, true)) {
                $v = '***';
            }
        });
        foreach ($sensitive as $k) {
            if (array_key_exists($k, $copy)) $copy[$k] = '***';
        }
        return $copy;
    }



    protected function buildUrl(string $endpoint): string
    {
        $endpoint = ltrim($endpoint, '/');

        $noJson = [
            'domains/v5/suggest-names',
        ];

        foreach ($noJson as $e) {
            if (stripos($endpoint, $e) === 0) {
                return $this->baseUrl . '/' . $endpoint;
            }
        }

        if (! str_ends_with($endpoint, '.json')) {
            $endpoint .= '.json';
        }

        return $this->baseUrl . '/' . $endpoint;
    }



    protected function normalizeParams(array $params): array
    {
        $out = [];

        foreach ($params as $k => $v) {
            if (is_array($v)) {
                $isAssoc = $this->isAssoc($v);
                if ($isAssoc) {
                    foreach ($v as $subk => $subv) {
                        if (is_array($subv)) {
                            $out["{$k}[{$subk}]"] = json_encode($subv);
                        } else {
                            $out["{$k}[{$subk}]"] = (string) $subv;
                        }
                    }
                } else {
                    foreach (array_values($v) as $i => $item) {
                        if (is_array($item)) {
                            $out["{$k}[{$i}]"] = json_encode($item);
                        } else {
                            $out["{$k}[{$i}]"] = (string) $item;
                        }
                    }
                }
            } else {
                $out[$k] = (string) $v;
            }
        }

        return $out;
    }

    protected function isAssoc(array $arr): bool
    {
        if ([] === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }



    protected function circuitKey(string $endpoint): string
    {
        return $this->circuitPrefix . md5($endpoint);
    }

    protected function registerFailure(string $endpoint): void
    {
        $key = $this->circuitKey($endpoint);
        $meta = Cache::get($key, ['count' => 0, 'last' => 0]);
        $meta['count'] = ((int) ($meta['count'] ?? 0)) + 1;
        $meta['last'] = time();
        Cache::put($key, $meta, 300);
    }

    protected function resetCircuit(string $endpoint): void
    {
        Cache::forget($this->circuitKey($endpoint));
    }

    protected function isCircuitOpen(string $endpoint): bool
    {
        $meta = Cache::get($this->circuitKey($endpoint));
        if (empty($meta) || !is_array($meta)) return false;
        if ((int) ($meta['count'] ?? 0) >= 3 && (time() - (int) ($meta['last'] ?? 0)) < 60) {
            return true;
        }
        return false;
    }


    protected function parseResponseBody(string $raw)
    {
        $rawTrim = trim($raw);
        if ($rawTrim === '') return null;


        if (stripos($rawTrim, '<!DOCTYPE') === 0 || stripos($rawTrim, '<html') === 0) {
            return ['_raw' => $rawTrim];
        }

        // JSON
        $decoded = @json_decode($rawTrim, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        if (stripos($rawTrim, '<?xml') !== false || stripos($rawTrim, '<') === 0) {
            libxml_use_internal_errors(true);
            $xml = @simplexml_load_string($rawTrim, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($xml !== false) {
                return json_decode(json_encode($xml), true);
            }
        }

        return ['_raw' => $rawTrim];
    }

   public function request(string $endpoint, array $params = [], string $method = 'POST', array $options = []): array
{
    $options = array_merge($this->defaultOptions, $options);
    $method = strtoupper($method);
    $params = $this->normalizeParams($params);
    $url = $this->buildUrl($endpoint);

    if ($this->isCircuitOpen($endpoint)) {
        $this->debugLog('circuit.open', ['endpoint' => $endpoint]);
        return [
            'success' => false,
            'error_origin' => 'circuit',
            'error_code' => 'CIRCUIT_OPEN',
            'error_exact' => 'circuit_open',
        ];
    }

    $auth = [
        'auth-userid' => $this->authUserId,
        'api-key'     => $this->apiKey,
    ];

    $headers = [
        'User-Agent' => $this->userAgent,
        'Accept'     => 'application/json',
    ];

    $queryParams = [];
    $bodyParams  = [];

    switch ($options['auth_placement']) {
        case 'headers':
            $headers    = array_merge($headers, $auth);
            $bodyParams = $params;
            break;

        case 'body':
            $bodyParams = array_merge($auth, $params);
            break;

        default: // query
            if ($method === 'GET') {
                $queryParams = array_merge($auth, $params);
            } else {
                $queryParams = $auth;
                $bodyParams  = $params;
            }
            break;
    }

    $this->debugLog('request.prepare', [
        'endpoint' => $endpoint,
        'method'   => $method,
        'url'      => $url,
        'query'    => $this->maskAuth($queryParams),
        'body'     => $this->maskAuth($bodyParams),
        'headers'  => $this->maskAuth($headers),
    ]);
    if ($method === 'GET' && !empty($options['cache_ttl'])) {
        $cacheKey = 'omarino:cache:' . md5($url . '|' . http_build_query($queryParams));
        if ($cached = Cache::get($cacheKey)) {
            return ['success' => true, 'data' => $cached, 'meta' => ['cache' => true]];
        }
    }

    $statsOut = null;

    for ($attempt = 1; $attempt <= max(1, $this->retryAttempts); $attempt++) {
        try {
            $client = Http::timeout($this->timeout)
                ->withHeaders($headers)
                ->withOptions([
                    'verify' => (bool) $options['verify'],
                    'on_stats' => function (TransferStats $stats) use (&$statsOut) {
                        $statsOut = [
                            'transfer_time' => $stats->getTransferTime(),
                            'has_response'  => $stats->hasResponse(),
                        ];
                    },
                ]);

            if ($method === 'GET') {
                $response = $client->get($url, $queryParams);
            } else {
                $finalUrl = empty($queryParams) ? $url : $url . '?' . http_build_query($queryParams);
                $response = $options['as_form']
                    ? $client->asForm()->post($finalUrl, $bodyParams)
                    : $client->post($finalUrl, $bodyParams);
            }

            $status = $response->status();
            $raw    = (string) $response->body();
            $parsed = $this->parseResponseBody($raw);

           
            $rawLower = strtolower($raw);
            if (
                $status === 403 &&
                str_contains($rawLower, 'cloudflare') &&
                (
                    str_contains($rawLower, 'attention required') ||
                    str_contains($rawLower, 'you have been blocked') ||
                    str_contains($rawLower, 'cf-ray')
                )
            ) {
                preg_match('/Cloudflare Ray ID:\s*<strong[^>]*>([a-zA-Z0-9]+)<\/strong>/i', $raw, $m);

                $this->debugLog('cloudflare.blocked', [
                    'endpoint' => $endpoint,
                    'status'   => $status,
                    'ray_id'   => $m[1] ?? null,
                ]);

                return [
                    'success'       => false,
                    'error_origin'  => 'cloudflare',
                    'error_code'    => 'CLOUDFLARE_BLOCK',
                    'status'        => 403,
                    'error_exact'   => 'Blocked by Cloudflare',
                    'details'       => ['ray_id' => $m[1] ?? null],
                ];
            }

            $this->debugLog('response.received', [
                'endpoint' => $endpoint,
                'status'   => $status,
                'raw_len'  => strlen($raw),
            ]);

            if ($status === 401) {
                $this->registerFailure($endpoint);
                return [
                    'success'      => false,
                    'error_origin' => 'omarino_auth',
                    'error_code'   => 'AUTH_REJECTED',
                    'status'       => 401,
                    'error_exact'  => $parsed['_raw'] ?? $raw,
                ];
            }

            if ($status >= 400) {
                $this->registerFailure($endpoint);
                return [
                    'success'      => false,
                    'error_origin' => 'omarino_http',
                    'error_code'   => 'HTTP_' . $status,
                    'status'       => $status,
                    'error_exact'  => $parsed['_raw'] ?? $raw,
                ];
            }

            if (is_array($parsed) && isset($parsed['status']) && strtoupper($parsed['status']) === 'ERROR') {
                $this->registerFailure($endpoint);
                return [
                    'success'      => false,
                    'error_origin' => 'omarino_logic',
                    'error_code'   => 'API_ERROR',
                    'error_exact'  => $parsed['message'] ?? 'API_ERROR',
                    'data'         => $parsed,
                ];
            }

            if ($method === 'GET' && !empty($options['cache_ttl'])) {
                Cache::put(
                    'omarino:cache:' . md5($url . '|' . http_build_query($queryParams)),
                    $parsed,
                    (int) $options['cache_ttl']
                );
            }

            $this->resetCircuit($endpoint);

            return [
                'success' => true,
                'data'    => $parsed,
                'meta'    => ['transfer_stats' => $statsOut],
            ];
        } catch (Throwable $e) {
            $this->registerFailure($endpoint);

            if ($attempt < $this->retryAttempts) {
                usleep(($this->retryDelayMs * $attempt) * 1000);
                continue;
            }

            return [
                'success'      => false,
                'error_origin' => 'network',
                'error_code'   => 'EXCEPTION',
                'error_exact'  => $e->getMessage(),
            ];
        }
    }

    return [
        'success'      => false,
        'error_origin' => 'unknown',
        'error_code'   => 'UNHANDLED',
    ];
}



    // ---------- Customers ----------
    public function createCustomer(array $data, array $options = []): array
    {
        return $this->request('customers/signup', $data, 'POST', $options);
    }

    public function getCustomerDetails(string $customerId, array $options = []): array
    {
        return $this->request('customers/details', ['customer-id' => $customerId], 'GET', $options);
    }

    public function updateCustomer(string $customerId, array $data, array $options = []): array
    {
        $payload = array_merge(['customer-id' => $customerId], $data);
        return $this->request('customers/update', $payload, 'POST', $options);
    }

    public function deleteCustomer(string $customerId, array $options = []): array
    {
        return $this->request('customers/delete', ['customer-id' => $customerId], 'POST', $options);
    }

    public function listCustomers(array $filters = [], int $page = 1, int $pageSize = 50, array $options = []): array
    {
        $params = array_merge($filters, ['page-no' => $page, 'no-of-records' => $pageSize]);
        return $this->request('customers/list', $params, 'GET', array_merge($options, ['cache_ttl' => $options['cache_ttl'] ?? 30]));
    }

    // ---------- Billing ----------
    public function getBalance(array $options = []): array
    {
        return $this->request('billing/customer-balance', [], 'GET', array_merge($options, ['cache_ttl' => $options['cache_ttl'] ?? 15]));
    }

    public function getResellerPricing(array $options = []): array
    {
        return $this->request('products/reseller-price', [], 'GET', array_merge($options, ['cache_ttl' => $options['cache_ttl'] ?? 3600]));
    }

    public function getCustomerPricing(array $options = []): array
    {
        return $this->request('products/customer-price', [], 'GET', array_merge($options, ['cache_ttl' => $options['cache_ttl'] ?? 3600]));
    }

    // ---------- Domains ----------
    public function checkAvailability(string $name, array $tlds = ['com'], array $options = []): array
    {
        $params = ['domain-name' => $name, 'tlds' => $tlds];
        return $this->request('domains/available', $params, 'GET', array_merge($options, ['cache_ttl' => $options['cache_ttl'] ?? 30]));
    }

public function checkAvailabilityBulk(array $names, array $tlds = ['com'], array $options = []): array
    {
        // LogicBoxes supports bulk but ensure payload formatting
        $payload = ['domain-name' => $names, 'tlds' => $tlds];
        return $this->request('domains/bulk-available', $payload, 'POST', $options);
    }

    public function getSuggestions(string $keyword, array $tlds = ['com'], int $limit = 10, array $options = []): array
    {
        $payload = ['keyword' => $keyword, 'tlds' => $tlds, 'no-of-results' => $limit];
        return $this->request('domains/v5/suggest-names', $payload, 'GET', $options);
    }

    public function registerDomain(array $data, array $options = []): array
    {
        return $this->request('domains/register', $data, 'POST', $options);
    }

    public function transferDomain(array $data, array $options = []): array
    {
        return $this->request('domains/transfer', $data, 'POST', $options);
    }

    public function renewDomain(string $orderIdOrDomain, int $years, ?string $expDate = null, array $options = []): array
    {
        if (strpos($orderIdOrDomain, '.') !== false) {
            $payload = ['domain-name' => $orderIdOrDomain, 'years' => $years];
        } else {
            $payload = ['order-id' => $orderIdOrDomain, 'years' => $years];
        }
        if ($expDate) $payload['exp-date'] = $expDate;
        return $this->request('domains/renew', $payload, 'POST', $options);
    }

    public function restoreDomain(string $orderId, array $options = []): array
    {
        return $this->request('domains/restore', ['order-id' => $orderId], 'POST', $options);
    }

    public function getDomainInfo(string $orderId, array $options = []): array
    {
        return $this->request('domains/details', ['order-id' => $orderId], 'GET', array_merge($options, ['cache_ttl' => 20]));
    }

    public function getOrderIdByDomain(string $domain, array $options = []): array
    {
        return $this->request('domains/orderid', ['domain-name' => $domain], 'GET', $options);
    }

    public function getAuthCode(string $orderId, array $options = []): array
    {
        return $this->request('domains/auth-code', ['order-id' => $orderId], 'GET', $options);
    }

    public function updateNameServers(string $orderId, array $ns, array $options = []): array
    {
        return $this->request('domains/modify-ns', ['order-id' => $orderId, 'ns' => $ns], 'POST', $options);
    }

    public function toggleTransferLock(string $orderId, bool $lock, array $options = []): array
    {
        $action = $lock ? 'enable-the-restriction' : 'disable-the-restriction';
        return $this->request("domains/{$action}", ['order-id' => $orderId], 'POST', $options);
    }

    public function getRegistryWhois(string $domain, array $options = []): array
    {
        return $this->request('domains/whois', ['domain-name' => $domain], 'GET', $options);
    }

    public function togglePrivacyProtection(string $orderId, bool $enable, array $options = []): array
    {
        return $this->request('domains/modify-privacy-protection', ['order-id' => $orderId, 'protect-privacy' => $enable ? 'true' : 'false'], 'POST', $options);
    }

    // ---------- DNS ----------
    public function getDnsRecords(string $domain, array $options = []): array
    {
        return $this->request('dns/manage/list', ['domain-name' => $domain], 'GET', array_merge($options, ['cache_ttl' => $options['cache_ttl'] ?? 30]));
    }

    public function addDnsRecord(array $data, array $options = []): array
    {
        return $this->request('dns/manage/add-record', $data, 'POST', $options);
    }

    public function deleteDnsRecord(array $data, array $options = []): array
    {
        return $this->request('dns/manage/delete-record', $data, 'POST', $options);
    }

public function addChildNs(string $orderId, string $cns, string $ip, array $options = []): array
    {
        return $this->request('domains/add-cns', ['order-id' => $orderId, 'cns' => $cns, 'ip' => $ip], 'POST', $options);
    }

    public function deleteChildNs(string $orderId, string $cns, array $options = []): array
    {
        return $this->request('domains/delete-cns', ['order-id' => $orderId, 'cns' => $cns], 'POST', $options);
    }

    // ---------- Contacts ----------
    public function createContact(array $data, array $options = []): array
    {
        // Standardize phone
        $phoneRaw = $data['phone'] ?? '';
        $phoneDigits = preg_replace('/[^0-9]/', '', $phoneRaw);
        $phoneCc = $data['phone_cc'] ?? (strlen($phoneDigits) > 10 ? substr($phoneDigits, 0, strlen($phoneDigits) - 10) : null);
        $phoneNumber = substr($phoneDigits, -10);

        $payload = [
            'first-name' => $data['first_name'] ?? $data['firstname'] ?? 'First',
            'last-name' => $data['last_name'] ?? $data['lastname'] ?? 'Last',
            'email' => $data['email'] ?? 'user@example.com',
            'address-line-1' => $data['address'] ?? 'Address',
            'city' => $data['city'] ?? 'City',
            'country-code' => strtoupper($data['country'] ?? 'US'),
            'zipcode' => $data['zipcode'] ?? ($data['zip'] ?? ''),
            'phone-cc' => $phoneCc ?? ($data['phone_cc'] ?? '1'),
            'phone' => $phoneNumber ?? $phoneRaw,
        ];

        return $this->request('contacts/add', $payload, 'POST', $options);
    }

    public function getContactDetails(string $contactId, array $options = []): array
    {
        return $this->request('contacts/details', ['contact-id' => $contactId], 'GET', $options);
    }

    // ---------- Templates / DNS Templates ----------
    public function createDnsTemplate(string $name, array $records, array $options = []): array
    {
        return $this->request('dns/templates/add', ['name' => $name, 'records' => $records], 'POST', $options);
    }

    public function applyDnsTemplate(string $domain, string $templateId, array $options = []): array
    {
        return $this->request('dns/templates/apply', ['domain-name' => $domain, 'template-id' => $templateId], 'POST', $options);
    }

    // ---------- Miscellaneous ----------
    public function getOrderDetailsByDomain(string $domain, array $options = []): array
    {
        return $this->request('domains/orderid', ['domain-name' => $domain], 'GET', $options);
    }

    public function getAuthCodeByOrder(string $orderId, array $options = []): array
    {
        return $this->request('domains/auth-code', ['order-id' => $orderId], 'GET', $options);
    }


  
    public function healthCheck(): array
    {
        $res = $this->getBalance();
        if ($res['success'] ?? false) {
            return ['success' => true, 'message' => 'Omarino OK', 'data' => $res['data']];
        }
        return ['success' => false, 'message' => 'Omarino unreachable', 'details' => $res];
    }

 
    public function extractDebugSample(array $response): array
    {
        return [
            'success' => $response['success'] ?? false,
            'status' => $response['status'] ?? null,
            'error' => $response['error'] ?? null,
            'data_sample' => is_array($response['data'] ?? null) ? array_slice($response['data'], 0, 5) : $response['data'] ?? null,
        ];
    }
}

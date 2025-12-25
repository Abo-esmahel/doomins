<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use GuzzleHttp\TransferStats;
use Throwable;

class OmarinoService
{
    protected string $userAgent = 'LogicBoxes-Reseller-API/1.0';
    protected bool $debug = false;
    protected string $baseUrl;
    protected string $username;
    protected string $apiKey;
    protected int $timeout;
    protected int $retryAttempts;
    protected int $retryDelayMs;
    protected string $circuitKeyPrefix = 'omarino:circuit:';
    protected array $defaultOptions = [
        'as_form' => true,
        'verify' => true,
        'auth_placement' => 'query',
        'cache_ttl' => 0,
        'debug_probe' => false,
    ];
    protected string $debugLogPath;

    public function __construct(array $overrides = [])
    {
        $this->baseUrl = rtrim((string) config('services.omarino.url', ''), '/');
        $this->username = (string) config('services.omarino.username', '');
        $this->apiKey = (string) config('services.omarino.api_key', '');
        $this->timeout = (int) config('services.omarino.timeout', 60);
        $this->retryAttempts = (int) config('services.omarino.retry_attempts', 3);
        $this->retryDelayMs = (int) config('services.omarino.retry_delay_ms', 500);
        $this->debug = (bool) config('services.omarino.debug', false);
        $this->debugLogPath = storage_path('logs/omarino-debug.log');

        foreach ($overrides as $k => $v) {
            if (property_exists($this, $k)) $this->$k = $v;
        }
    }

    protected function debugLog(string $title, array $data = []): void
    {
        if (! $this->debug) return;
        $entry = ['time' => date('c'), 'title' => $title, 'data' => $data];
        Log::debug('[OMARINO DEBUG] ' . $title, $data);
        try {
            file_put_contents($this->debugLogPath, json_encode($entry, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
            Log::warning('omarino debug write failed: ' . $e->getMessage());
        }
    }
protected function buildUrl(string $endpoint): string
{
    $endpoint = ltrim($endpoint, '/');

    // Endpoints that must NOT have .json
    $noJson = [
        'system/ping',
        'domains/v5/suggest-names',
    ];

    foreach ($noJson as $skip) {
        if (stripos($endpoint, $skip) === 0) {
            return $this->baseUrl . '/' . $endpoint;
        }
    }

    // Append .json if missing
    if (!str_ends_with($endpoint, '.json')) {
        $endpoint .= '.json';
    }

    return $this->baseUrl . '/' . $endpoint;
}



    protected function normalizeParams(array $params): array
    {
        $out = [];
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                if ($this->isAssoc($v)) {
                    foreach ($v as $subk => $subv) {
                        $out["{$k}[{$subk}]"] = is_array($subv) ? json_encode($subv) : (string)$subv;
                    }
                } else {
                    foreach (array_values($v) as $i => $item) {
                        $out["{$k}[{$i}]"] = is_array($item) ? json_encode($item) : (string)$item;
                    }
                }
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    protected function isAssoc(array $arr): bool
    {
        if ([] === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    protected function registerFailure(string $endpoint): void
    {
        $key = $this->circuitKeyPrefix . md5($endpoint);
        $meta = Cache::get($key, ['count' => 0, 'last' => 0]);
        $meta['count'] = ($meta['count'] ?? 0) + 1;
        $meta['last'] = time();
        Cache::put($key, $meta, 300);
    }

    protected function resetCircuit(string $endpoint): void
    {
        $key = $this->circuitKeyPrefix . md5($endpoint);
        Cache::forget($key);
    }

    protected function isCircuitOpen(string $endpoint): bool
    {
        $key = $this->circuitKeyPrefix . md5($endpoint);
        $meta = Cache::get($key);
        if (empty($meta) || !is_array($meta)) return false;
        if (($meta['count'] ?? 0) >= 3 && (time() - ($meta['last'] ?? 0)) < 60) return true;
        return false;
    }

    protected function parseResponseBody(string $raw)
    {
        if ($raw === '') return null;
        $rawTrim = trim($raw);
        if (stripos($rawTrim, '<?xml') !== false || stripos($rawTrim, '<') === 0) {
            libxml_use_internal_errors(true);
            $xml = @simplexml_load_string($rawTrim, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($xml !== false) return json_decode(json_encode($xml), true);
            return ['_raw' => $rawTrim];
        }
        $decoded = @json_decode($rawTrim, true);
        if (json_last_error() === JSON_ERROR_NONE) return $decoded;
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
            'error_exact' => 'circuit_open'
        ];
    }

    $auth = ['auth-userid' => $this->username, 'api-key' => $this->apiKey];

    $headers = ['User-Agent' => $this->userAgent];
    $bodyParams = $params;
    $queryParams = [];

    switch ($options['auth_placement']) {
        case 'headers':
            $headers = array_merge($headers, $auth);
            break;
        case 'body':
            $bodyParams = array_merge($auth, $bodyParams);
            break;
        default:
            $queryParams = $auth;
            break;
    }

    $this->debugLog('request.prepare', [
        'endpoint' => $endpoint,
        'method' => $method,
        'url' => $url,
        'auth_placement' => $options['auth_placement'],
        'query' => $this->maskAuth($queryParams),
        'body' => $this->maskAuth($bodyParams),
        'headers' => $this->maskAuth($headers),
    ]);

    $lastException = null;
    $statsOut = null;

    for ($attempt = 1; $attempt <= max(1, $this->retryAttempts); $attempt++) {
        try {
            $client = Http::timeout($this->timeout)->withOptions([
                'verify' => $options['verify'],
                'on_stats' => function (TransferStats $stats) use (&$statsOut) {
                    $statsOut = [
                        'transfer_time' => $stats->getTransferTime(),
                        'has_response' => $stats->hasResponse(),
                        'handler_stats' => method_exists($stats, 'getHandlerStats') ? $stats->getHandlerStats() : null,
                    ];
                },
            ]);

            if ($method === 'GET') {
                $response = $client->withHeaders($headers)->get($url, array_merge($queryParams, $bodyParams));
            } else {
                $response = $options['as_form']
                    ? $client->withHeaders($headers)->asForm()->post($url . (empty($queryParams) ? '' : ('?' . http_build_query($queryParams))), $bodyParams)
                    : $client->withHeaders($headers)->post($url . (empty($queryParams) ? '' : ('?' . http_build_query($queryParams))), $bodyParams);
            }

            $status = $response->status();
            $raw = (string)$response->body();
            $parsed = $this->parseResponseBody($raw);

            $this->debugLog('response.received', [
                'status' => $status,
                'raw_len' => strlen($raw),
                'parsed_sample' => is_array($parsed) ? array_slice($parsed,0,5) : $parsed,
                'transfer_stats' => $statsOut
            ]);

            if ($status === 401) {
                $this->registerFailure($endpoint);
                $errorDetails = [
                    'auth_userid' => isset($queryParams['auth-userid']) ? 'sent' : 'missing',
                    'api_key' => isset($queryParams['api-key']) ? 'sent' : 'missing',
                    'auth_placement' => $options['auth_placement'],
                    'response_body' => $parsed,
                ];
                return [
                    'success' => false,
                    'error_origin' => 'omarino_auth',
                    'error_code' => 'AUTH_REJECTED',
                    'error_exact' => $parsed['_raw'] ?? $raw,
                    'status' => 401,
                    'details' => $errorDetails
                ];
            }

            if ($status >= 400) {
                $this->registerFailure($endpoint);
                return [
                    'success' => false,
                    'error_origin' => 'omarino_http',
                    'error_code' => 'HTTP_' . $status,
                    'error_exact' => $parsed['_raw'] ?? $raw,
                    'status' => $status
                ];
            }

            if (is_array($parsed) && isset($parsed['status']) && strtoupper((string)$parsed['status']) === 'ERROR') {
                $this->registerFailure($endpoint);
                return [
                    'success' => false,
                    'error_origin' => 'omarino_logic',
                    'error_code' => 'API_ERROR',
                    'error_exact' => $parsed['message'] ?? ($parsed['_raw'] ?? $raw),
                    'data' => $parsed
                ];
            }

            $this->resetCircuit($endpoint);

            return [
                'success' => true,
                'data' => $parsed,
                'meta' => ['transfer_stats' => $statsOut]
            ];

        } catch (Throwable $e) {
            $lastException = $e;
            $m = $e->getMessage();
            $this->debugLog('request.exception', ['message' => $m, 'class' => get_class($e)]);
            $this->registerFailure($endpoint);

            if ($attempt < $this->retryAttempts) {
                usleep($this->retryDelayMs * 1000 * $attempt);
                continue;
            }

            if (stripos($m, 'ssl') !== false || stripos($m, 'handshake') !== false) {
                return [
                    'success' => false,
                    'error_origin' => 'tls',
                    'error_code' => 'TLS_HANDSHAKE_FAILED',
                    'error_exact' => $m
                ];
            }

            if (stripos($m, 'Could not resolve host') !== false) {
                return [
                    'success' => false,
                    'error_origin' => 'network',
                    'error_code' => 'HOST_UNRESOLVED',
                    'error_exact' => $m,
                    'endpoint' => $endpoint,
                    'url_attempted' => $url
                ];
            }

            return [
                'success' => false,
                'error_origin' => 'network',
                'error_code' => 'CONNECTION_FAILED',
                'error_exact' => $m
            ];
        }
    }

    return [
        'success' => false,
        'error_origin' => 'unknown',
        'error_code' => 'UNHANDLED_EXCEPTION',
        'error_exact' => $lastException ? $lastException->getMessage() : 'unknown_error'
    ];
}




public function ping(): array
{
    return $this->request('system/ping', [], 'GET');
}
    public function healthCheck(): array { $res = $this->ping(); if ($res['success']) return ['success' => true, 'message' => 'Omarino OK', 'data' => $res['data']]; return ['success' => false, 'message' => 'Omarino unreachable', 'details' => $res]; }

    public function createCustomer(array $data, array $options = []): array { return $this->request('customers/signup', $data, 'POST', $options); }
    public function getCustomerDetails(string $customerId, array $options = []): array { return $this->request('customers/details', ['customer-id' => $customerId], 'GET', $options); }
    public function updateCustomer(string $customerId, array $data, array $options = []): array { return $this->request('customers/update', array_merge(['customer-id' => $customerId], $data), 'POST', $options); }
    public function deleteCustomer(string $customerId, array $options = []): array { return $this->request('customers/delete', ['customer-id' => $customerId], 'POST', $options); }
    public function listCustomers(array $filters = [], int $page = 1, int $pageSize = 50, array $options = []): array { $params = array_merge($filters, ['page-no' => $page, 'no-of-records' => $pageSize]); return $this->request('customers/list', $params, 'GET', array_merge($options, ['cache_ttl' => $options['cache_ttl'] ?? 30])); }

    public function getBalance(array $options = []): array { return $this->request('billing/customer-balance', [], 'GET', array_merge($options, ['cache_ttl' => 15])); }
    public function getResellerPricing(array $options = []): array { return $this->request('products/reseller-price', [], 'GET', array_merge($options, ['cache_ttl' => 60])); }
    public function getCustomerPricing(array $options = []): array { return $this->request('products/customer-price', [], 'GET', array_merge($options, ['cache_ttl' => 60])); }

    public function searchNameAnyTldWithSuggestions(string $name, ?array $tlds = null, array $options = []): array { return $this->request('domains/search', ['query' => $name, 'tlds' => $tlds, 'suggestions' => 1], 'GET', $options); }
    public function searchNameWithTld(string $name, string $tld, array $options = []): array { return $this->request('domains/checkAvailability', ['domain-name' => $name, 'tld' => $tld], 'GET', $options); }

    public function searchByPrice(?float $min = null, ?float $max = null, array $tlds = [], array $options = []): array { $params = ['min_price' => $min !== null ? (string)$min : null, 'max_price' => $max !== null ? (string)$max : null, 'tlds' => $tlds]; return $this->request('domains/searchByPrice', $params, 'GET', $options); }
    public function searchByTld(string $tld, string $query = '', int $limit = 50, array $options = []): array { return $this->request('domains/searchByTld', ['tld' => $tld, 'query' => $query, 'limit' => $limit], 'GET', $options); }

    public function checkAvailability(string $name, array $tlds = [], array $options = []): array { return $this->request('domains/available', ['domain-name' => $name, 'tlds' => $tlds], 'GET', array_merge($options, ['cache_ttl' => $options['cache_ttl'] ?? 30])); }
    public function checkAvailabilityBulk(array $names, array $tlds, int $batchSize = 20, array $options = []): array { $results = []; $chunks = array_chunk($names, $batchSize); foreach ($chunks as $chunk) { $payload = ['domain-name' => $chunk, 'tlds' => $tlds]; $results[] = $this->request('domains/bulk-available', $payload, 'GET', $options); } return ['success' => true, 'data' => $results]; }

public function getSuggestions(
    string $keyword,
    array $tlds = ['com', 'net'],
    int $limit = 10,
    array $options = []
): array {
    return $this->request(
        'domains/v5/suggest-names',
        [
            'keyword' => $keyword,
            'tlds' => $tlds,
            'no-of-results' => $limit,
        ],
        'GET',
        $options
    );
}

    public function registerDomain(array $data, array $options = []): array { return $this->request('domains/register', $data, 'POST', $options); }
    public function transferDomain(array $data, array $options = []): array { return $this->request('domains/transfer', $data, 'POST', $options); }

    public function renewDomain(string $idOrDomain, int $years, ?string $expDate = null, array $options = []): array {
        if (strpos($idOrDomain, '.') !== false) {
            return $this->request('domains/renew', ['domain-name' => $idOrDomain, 'years' => $years], 'POST', $options);
        }
        $payload = ['order-id' => $idOrDomain, 'years' => $years];
        if ($expDate) $payload['exp-date'] = $expDate;
        return $this->request('domains/renew', $payload, 'POST', $options);
    }

    public function renewDomainByOrderId(string $orderId, int $years, string $expDate = '', array $options = []): array { $payload = ['order-id' => $orderId, 'years' => $years]; if ($expDate !== '') $payload['exp-date'] = $expDate; return $this->request('domains/renew', $payload, 'POST', $options); }

    public function restoreDomain(string $orderId, array $options = []): array { return $this->request('domains/restore', ['order-id' => $orderId], 'POST', $options); }
    public function getDomainInfo(string $orderId, array $options = []): array { return $this->request('domains/details', ['order-id' => $orderId], 'GET', array_merge($options, ['cache_ttl' => $options['cache_ttl'] ?? 20])); }

    public function getOrderIdByDomain(string $domain, array $options = []): array { return $this->request('domains/orderid', ['domain-name' => $domain], 'GET', $options); }
    public function getAuthCode(string $orderId, array $options = []): array { return $this->request('domains/auth-code', ['order-id' => $orderId], 'GET', $options); }

    public function updateNameservers(string $domain, array $nameservers, array $options = []): array { return $this->request('domains/updateNameservers', ['domain-name' => $domain, 'ns' => $nameservers], 'POST', $options); }

    public function toggleTransferLock(string $orderId, bool $lock, array $options = []): array { $action = $lock ? 'enable-the-restriction' : 'disable-the-restriction'; return $this->request("domains/{$action}", ['order-id' => $orderId], 'POST', $options); }

    public function togglePrivacyProtection(string $orderId, bool $enable, array $options = []): array { return $this->request('domains/modify-privacy-protection', ['order-id' => $orderId, 'protect-privacy' => $enable ? 'true' : 'false'], 'POST', $options); }

    public function deleteDomain(string $domain, array $options = []): array { return $this->request('domains/delete', ['domain-name' => $domain], 'POST', $options); }

    public function toggleDomainLock(string $domain, bool $lock, array $options = []): array { return $this->request('domains/lock', ['domain-name' => $domain, 'lock' => $lock ? 'true' : 'false'], 'POST', $options); }

    public function toggleWhoisPrivacy(string $domain, bool $enable, array $options = []): array { return $this->request('domains/privacy', ['domain-name' => $domain, 'enable' => $enable ? 'true' : 'false'], 'POST', $options); }

    public function getDnsRecords(string $domain, array $options = []): array { return $this->request('dns/list', ['domain-name' => $domain], 'GET', array_merge($options, ['cache_ttl' => $options['cache_ttl'] ?? 30])); }

    public function addDnsRecordForDomain(string $domain, array $record, array $options = []): array { return $this->request('dns/add', array_merge(['domain-name' => $domain], $record), 'POST', $options); }

    public function addDnsRecord(array $data, array $options = []): array { return $this->request('dns/add', $data, 'POST', $options); }

    public function deleteDnsRecord(string $domain, int $recordId, array $options = []): array { return $this->request('dns/delete', ['domain-name' => $domain, 'record-id' => $recordId], 'POST', $options); }

    public function updateDnsRecord(array $data, array $options = []): array { return $this->request('dns/update', $data, 'POST', $options); }

    public function addChildNS(string $orderId, string $cns, string $ip, array $options = []): array { return $this->request('domains/add-cns', ['order-id' => $orderId, 'cns' => $cns, 'ip' => $ip], 'POST', $options); }

    public function deleteChildNS(string $orderId, string $cns, array $options = []): array { return $this->request('domains/delete-cns', ['order-id' => $orderId, 'cns' => $cns], 'POST', $options); }

    public function createDnsTemplate(string $name, array $records, array $options = []): array { return $this->request('dns/templates/add', ['name' => $name, 'records' => $records], 'POST', $options); }

    public function applyDnsTemplate(string $domain, string $templateId, array $options = []): array { return $this->request('dns/templates/apply', ['domain-name' => $domain, 'template-id' => $templateId], 'POST', $options); }

    public function enableDnssec(string $orderId, array $options = []): array { return $this->request('dns/dnssec/enable', ['order-id' => $orderId], 'POST', $options); }

    public function disableDnssec(string $orderId, array $options = []): array { return $this->request('dns/dnssec/disable', ['order-id' => $orderId], 'POST', $options); }


public function createContact(array $data, array $options = []): array
{
    $phoneRaw = $data['phone'] ?? '';
    $phoneDigits = preg_replace('/[^0-9]/', '', $phoneRaw);

    // استخراج كود الدولة من الهاتف إن لم يُرسل
    $phoneCc = $data['phone_cc']
        ?? (strlen($phoneDigits) > 10 ? substr($phoneDigits, 0, strlen($phoneDigits) - 10) : '1');

    $phoneNumber = substr($phoneDigits, -10);

    $payload = [
        'first-name'      => $data['first_name'] ?? $data['firstname'] ?? 'First',
        'last-name'       => $data['last_name']  ?? $data['lastname']  ?? 'Last',
        'email'           => $data['email']       ?? 'user@example.com',
        'address-line-1'  => $data['address']     ?? 'Default Address',
        'city'            => $data['city']        ?? 'City',
        'country-code'    => strtoupper($data['country'] ?? 'US'),
        'zipcode'         => $data['postal_code'] ?? $data['zip'] ?? '00000',
        'phone-cc'        => $phoneCc,
        'phone'           => $phoneNumber,
        'company'         => $data['company']     ?? '',
    ];

    // إزالة القيم الفارغة فقط
    $payload = array_filter($payload, fn ($v) => $v !== null && $v !== '');

    return $this->request(
        'contacts/add',
        $payload,
        'POST',
        $options
    );
}



    public function getContactDetails(string $contactId, array $options = []): array { return $this->request('contacts/details', ['contact-id' => $contactId], 'GET', $options); }
    public function updateContact(string $contactId, array $data, array $options = []): array { return $this->request('contacts/update', array_merge(['contact-id' => $contactId], $data), 'POST', $options); }
    public function deleteContact(string $contactId, array $options = []): array { return $this->request('contacts/delete', ['contact-id' => $contactId], 'POST', $options); }

    public function getTldPricing(string $tld, array $options = []): array { return $this->request('products/tld-price', ['tld' => $tld], 'GET', array_merge($options, ['cache_ttl' => $options['cache_ttl'] ?? 120])); }

    public function checkPremiumPricing(string $domain, array $options = []): array { return $this->request('domains/premium/price', ['domain-name' => $domain], 'GET', $options); }

    public function bulkRegister(array $domains, int $batchSize = 10, array $options = []): array { $results = []; $chunks = array_chunk($domains, $batchSize); foreach ($chunks as $chunk) { $payload = ['domains' => $chunk]; $results[] = $this->request('domains/bulk-register', $payload, 'POST', $options); } return ['success' => true, 'data' => $results]; }

    public function setAutoRenew(string $orderId, bool $autoRenew, array $options = []): array { return $this->request('domains/modify-auto-renewal', ['order-id' => $orderId, 'auto-renew' => $autoRenew ? 'true' : 'false'], 'POST', $options); }

    public function changeRegistrant(string $orderId, array $contactIds, array $options = []): array { return $this->request('domains/change-registrant', array_merge(['order-id' => $orderId], $contactIds), 'POST', $options); }

    public function parkDomain(string $orderId, string $parkingId, array $options = []): array { return $this->request('domains/park', ['order-id' => $orderId, 'parking-id' => $parkingId], 'POST', $options); }

    public function unparkDomain(string $orderId, array $options = []): array { return $this->request('domains/unpark', ['order-id' => $orderId], 'POST', $options); }

    public function rawRequest(string $endpoint, array $params = [], string $method = 'POST', array $options = []): array { return $this->request($endpoint, $params, $method, $options); }

    public function isSuccess(array $response): bool { return !empty($response['success']); }

    public function getData(array $response) { return $response['data'] ?? null; }


    protected function cacheKeyFor(string $method, string $url, array $params): string
{
    return 'omarino:cache:' . md5($method . '|' . $url . '|' . serialize($params));
}


   protected function maskAuth(array $arr): array
   {
    if (empty($arr)) return $arr;
    $masked = $arr;
    $keys = ['api-key','api_key','auth-userid','username','password','auth','apiKey','apikey'];
    array_walk_recursive($masked, function (&$v, $k) use ($keys) {
        if (in_array($k, $keys, true)) $v = '***';
    });
    // also mask any top-level keys if present
    foreach ($keys as $k) {
        if (array_key_exists($k, $masked)) $masked[$k] = '***';
    }
    return $masked;
   }



}

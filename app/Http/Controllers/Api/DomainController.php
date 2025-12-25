<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OmarinoService;
use App\Repositories\DomainRepository;
use App\Jobs\RegisterDomainJob;
use App\Jobs\RenewDomainJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DomainController extends Controller
{
    protected OmarinoService $omarino;
    protected DomainRepository $repo;

    public function __construct(OmarinoService $omarino, DomainRepository $repo)
    {
        $this->omarino = $omarino;
        $this->repo = $repo;
    }

protected function serviceResponse(array $serviceResp)
{
    if (!empty($serviceResp['success'])) {
        return response()->json([
            'success' => true,
            'status' => $serviceResp['status'] ?? 200,
            'data' => $serviceResp['data'] ?? null,
        ], $serviceResp['status'] ?? 200);
    }

    // map known service error fields into a clearer output
    $status = $serviceResp['status'] ?? ($serviceResp['error_origin'] === 'omarino_auth' ? 401 : 422);
    $message = $serviceResp['message'] ?? $serviceResp['error_exact'] ?? 'omarino_error';

    return response()->json([
        'success' => false,
        'status' => $status,
        'message' => $message,
        'error_origin' => $serviceResp['error_origin'] ?? null,
        'error_code' => $serviceResp['error_code'] ?? null,
        'error_details' => $serviceResp['error_details'] ?? $serviceResp['data'] ?? null,
    ], $status >= 100 && $status < 600 ? $status : 422);
}



    protected function findInResponse(array $serviceResp, array $keys)
    {
        if (empty($serviceResp['success'])) return null;
        $data = $serviceResp['data'] ?? $serviceResp;
        if (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }

        $search = function ($arr, $keys) use (&$search) {
            foreach ($keys as $k) {
                if (is_array($arr) && array_key_exists($k, $arr)) {
                    return $arr[$k];
                }
            }
            if (is_array($arr)) {
                foreach ($arr as $v) {
                    if (is_array($v)) {
                        $found = $search($v, $keys);
                        if ($found !== null) return $found;
                    }
                }
            }
            return null;
        };

        return $search($data, $keys);
    }


    protected function extractContactId(array $serviceResp)
    {
        $candidates = ['contact_id', 'contact-id', 'id', 'contactId', 'contactId'];
        $found = $this->findInResponse($serviceResp, $candidates);
        return is_scalar($found) ? (string)$found : null;
    }


    protected function extractOrderId(array $serviceResp)
    {
        $candidates = ['order-id', 'orderid', 'orderId', 'order_id', 'id'];
        $found = $this->findInResponse($serviceResp, $candidates);
        return is_scalar($found) ? (string)$found : null;
    }


    public function searchAnyTld(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'tlds' => 'sometimes|array',
            'limit' => 'sometimes|integer|min:1|max:200',
        ]);

        $tlds = $data['tlds'] ?? null;
        $limit = $data['limit'] ?? 20;

        $res = $this->omarino->getSuggestions($data['name'], $tlds ?? ['com','net'], $limit);

        return $this->serviceResponse($res);
    }


    public function searchWithTld(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'tld' => 'required|string|max:50',
        ]);

        $res = $this->omarino->checkAvailability($data['name'], [$data['tld']]);

        return $this->serviceResponse($res);
    }


    public function searchByPrice(Request $request)
    {
        $data = $request->validate([
            'min' => 'nullable|numeric',
            'max' => 'nullable|numeric',
            'tlds' => 'nullable|array',
        ]);

        $payload = [
            'min_price' => $data['min'] !== null ? (string)$data['min'] : null,
            'max_price' => $data['max'] !== null ? (string)$data['max'] : null,
            'tlds' => isset($data['tlds']) ? implode(',', $data['tlds']) : null,
        ];

        $res = $this->omarino->rawRequest('domains/searchByPrice', $payload, 'GET');

        return $this->serviceResponse($res);
    }


    public function searchByTld(Request $request)
    {
        $data = $request->validate([
            'tld' => 'required|string',
            'query' => 'sometimes|string',
            'limit' => 'sometimes|integer|max:200',
        ]);

        $payload = [
            'tld' => $data['tld'],
            'query' => $data['query'] ?? '',
            'limit' => $data['limit'] ?? 50,
        ];

        $res = $this->omarino->rawRequest('domains/searchByTld', $payload, 'GET');

        return $this->serviceResponse($res);
    }


    public function status(Request $request)
{
    $data = $request->validate([
        'domain' => 'required|string',
    ]);

    $orderIdResp = $this->omarino->getOrderIdByDomain($data['domain']);

    if (!empty($orderIdResp['success'])) {
        $orderId = $this->extractOrderId($orderIdResp);

        if ($orderId) {
            return $this->serviceResponse(
                $this->omarino->getDomainInfo($orderId)
            );
        }
    }

    // 2️⃣ fallback آمن
    return $this->serviceResponse(
        $this->omarino->rawRequest(
            'domains/details',
            ['domain-name' => $data['domain']],
            'GET'
        )
    );
}


    public function details(Request $request)
    {
        $data = $request->validate(['domain' => 'required|string']);
        $orderIdResp = $this->omarino->getOrderIdByDomain($data['domain']);
        if (!empty($orderIdResp['success'])) {
            $orderId = $this->extractOrderId($orderIdResp);
            if ($orderId) {
                $res = $this->omarino->getDomainInfo($orderId);
                return $this->serviceResponse($res);
            }
        }

        $res = $this->omarino->rawRequest('domains/details', ['domain-name' => $data['domain']], 'GET');
        return $this->serviceResponse($res);
    }


public function purchase(Request $request)
{
    $data = $request->validate([
        'domain'  => 'required|string',
        'tld'     => 'required|string',
        'years'   => 'required|integer|min:1',
        'contact' => 'nullable|array',
    ]);

    $user = auth('sanctum')->user();
    if (! $user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized',
        ], 401);
    }

    $domainRecord = $this->repo->createIfNotExists([
        'domain'  => $data['domain'],
        'tld'     => $data['tld'],
        'user_id' => $user->id,
        'status'  => 'queued',
    ]);

    // بيانات الاتصال (إما من الطلب أو من المستخدم)
    $contactData = $data['contact'] ?? [
        'first_name'  => $user->first_name ?? 'First',
        'last_name'   => $user->last_name ?? 'Last',
        'email'       => $user->email ?? 'user@example.com',
        'phone'       => $user->phone ?? '+10000000000',
        'address'     => $user->address ?? 'Default Address',
        'city'        => $user->city ?? 'City',
        'country'     => $user->country ?? 'US',
        'postal_code' => $user->postal_code ?? '00000',
        'company'     => $user->company ?? '',
    ];

    // إنشاء Contact
    $contactResp = $this->omarino->createContact($contactData);

    if (empty($contactResp['success'])) {
        $this->repo->updateFromApiResponse($domainRecord, $contactResp);

        return response()->json([
            'success' => false,
            'message' => 'contact_creation_failed',
            'details' => $contactResp,
        ], 422);
    }

    $contactId = $this->extractContactId($contactResp);

    if (! $contactId) {
        return response()->json([
            'success' => false,
            'message' => 'contact_id_missing',
            'raw'     => $contactResp,
        ], 422);
    }

    // Payload التسجيل
    $payload = [
        'domain-name'           => $data['domain'] . '.' . $data['tld'],
        'years'                 => (int) $data['years'],
        'registrant-contact-id' => $contactId,
        'admin-contact-id'      => $contactId,
        'tech-contact-id'       => $contactId,
        'billing-contact-id'    => $contactId,
    ];

    RegisterDomainJob::dispatch($payload, $domainRecord->id)
        ->onQueue('domains');

    return response()->json([
        'success'          => true,
        'message'          => 'domain_registration_queued',
        'domain_record_id' => $domainRecord->id,
    ], 202);
}




    public function renew(Request $request)
    {
        $data = $request->validate(['domain' => 'required|string', 'years' => 'required|integer|min:1']);

        [$name, $tld] = array_pad(explode('.', $data['domain'], 2), 2, '');

        $domainRecord = $this->repo->createIfNotExists([
            'domain' => $name,
            'tld' => $tld,
            'status' => 'renew-queued',
        ], null);

        RenewDomainJob::dispatch(['domain' => $data['domain'], 'years' => $data['years']], $domainRecord->id)->onQueue('domains');

        return response()->json(['success' => true, 'message' => 'renewal_queued', 'domain_record_id' => $domainRecord->id], 202);
    }


    public function cancel(Request $request)
    {
        $data = $request->validate(['domain' => 'required|string']);

        $orderIdResp = $this->omarino->getOrderIdByDomain($data['domain']);
        if (!empty($orderIdResp['success'])) {
            $orderId = $this->extractOrderId($orderIdResp);
            if ($orderId) {
                $res = $this->omarino->rawRequest('domains/delete', ['order-id' => $orderId], 'POST');
                return $this->serviceResponse($res);
            }
        }

        $res = $this->omarino->rawRequest('domains/delete', ['domain-name' => $data['domain']], 'POST');
        return $this->serviceResponse($res);
    }


  public function balance(OmarinoService $omarino)
{
    return $this->serviceResponse($omarino->getBalance());
}


    public function lock(Request $request)
    {
        $data = $request->validate(['domain' => 'required|string', 'lock' => 'required|boolean']);

        $orderIdResp = $this->omarino->getOrderIdByDomain($data['domain']);
        if (empty($orderIdResp['success'])) {
            return $this->serviceResponse($orderIdResp);
        }
        $orderId = $this->extractOrderId($orderIdResp);
        if (!$orderId) {
            return response()->json(['success' => false, 'message' => 'order_id_not_found'], 422);
        }

        $res = $this->omarino->toggleTransferLock($orderId,
        $data['lock']);
        return $this->serviceResponse($res);
    }

    public function privacy(Request $request)
    {
        $data = $request->validate(['domain' => 'required|string', 'enable' => 'required|boolean']);

        $orderIdResp = $this->omarino->getOrderIdByDomain($data['domain']);
        if (empty($orderIdResp['success'])) {
            return $this->serviceResponse($orderIdResp);
        }
        $orderId = $this->extractOrderId($orderIdResp);
        if (!$orderId) {
            return response()->json(['success' => false, 'message' => 'order_id_not_found'], 422);
        }

        $res = $this->omarino->togglePrivacyProtection($orderId, $data['enable']);
        return $this->serviceResponse($res);
    }


    public function dnsList(Request $request)
    {
        $data = $request->validate(['domain' => 'required|string']);
        $res = $this->omarino->getDnsRecords($data['domain']);
        return $this->serviceResponse($res);
    }

    public function dnsAdd(Request $request)
    {
        $data = $request->validate([
            'domain' => 'required|string',
            'record' => 'required|array',
        ]);

        $payload = array_merge(['domain-name' => $data['domain']], $data['record']);
        $res = $this->omarino->addDnsRecord($payload);

        return $this->serviceResponse($res);
    }

   public function dnsDelete(Request $request)
{
    $data = $request->validate([
        'domain'    => 'required|string',
        'record_id' => 'required|integer',
    ]);

   $res = $this->omarino->deleteDnsRecord(
    $data['domain'],
    $data['record_id']
);


    return $this->serviceResponse($res);
}




public function testLogicboxes()
{
    $res = app(OmarinoService::class)->request(
        'billing/customer-balance',
        [],
        'GET'
    );

    return response()->json($res);
}

}

<?php
namespace App\Repositories;

use App\Models\Domain;

class DomainRepository
{
    public function create(array $data): Domain
    {
        return Domain::create($data);
    }

    public function findByIdempotency(string $domain, string $tld, ?string $idempotencyKey)
    {
        if (!$idempotencyKey) return null;

        return Domain::where('domain', $domain)
            ->where('tld', $tld)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    public function createIfNotExists(array $data, ?string $idempotencyKey = null): Domain
    {
        $found = $this->findByIdempotency($data['domain'], $data['tld'], $idempotencyKey);
        if ($found) return $found;
        $data['idempotency_key'] = $idempotencyKey;
        return $this->create($data);
    }

    public function updateFromApiResponse(Domain $domain, array $apiResponse): Domain
    {
        $domain->order_id = $apiResponse['order_id'] ?? $domain->order_id;
        $domain->status = $apiResponse['status'] ?? $domain->status;
        $domain->raw_response = $apiResponse;
        $domain->save();
        return $domain;
    }
}

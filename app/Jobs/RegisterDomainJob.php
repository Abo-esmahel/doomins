<?php
namespace App\Jobs;

use App\Services\OmarinoService;
use App\Repositories\DomainRepository;
use App\Models\Domain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RegisterDomainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $payload;
    public int $domainId;

    public $tries = 5;

    public function __construct(array $payload, int $domainId)
    {
        $this->payload = $payload;
        $this->domainId = $domainId;
    }

    public function handle(OmarinoService $omarino, DomainRepository $repo)
    {
        Log::info('RegisterDomainJob handling', ['domain' => $this->payload['domain']]);

        $apiResp = $omarino->registerDomain($this->payload);

        $domain = Domain::find($this->domainId);
        if ($domain) {
            $repo->updateFromApiResponse($domain, is_array($apiResp) ? $apiResp : ['raw' => $apiResp]);
        }

        Log::info('RegisterDomainJob finished', ['response' => $apiResp]);
    }

    public function failed(\Throwable $e)
    {
        Log::error('RegisterDomainJob failed', ['error' => $e->getMessage(), 'payload' => $this->payload]);
    }
}

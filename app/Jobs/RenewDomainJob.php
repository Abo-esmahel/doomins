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
use Throwable;

class RenewDomainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // عدد المحاولات قبل الفشل النهائي
    public int $tries = 5;

    // ترتيب فترات الانتظار بين المحاولات (يمكن أن يكون عددياً أو مصفوفة)
    public $backoff = [30, 60, 120];

    // بيانات الطلب التي سترسل للـ API
    protected array $payload;

    // الـ id للسجل المحلي في جدول domains
    protected int $domainId;

    /**
     * @param array $payload   // expects ['domain' => 'example.com', 'years' => 1, ...optional]
     * @param int $domainId
     */
    public function __construct(array $payload, int $domainId)
    {
        $this->payload = $payload;
        $this->domainId = $domainId;
    }

    /**
     * Execute the job.
     *
     * @param OmarinoService $omarino
     * @param DomainRepository $repo
     * @return void
     */
    public function handle(OmarinoService $omarino, DomainRepository $repo): void
    {
        Log::info('RenewDomainJob started', [
            'domain' => $this->payload['domain'] ?? null,
            'years'  => $this->payload['years'] ?? null,
            'domain_record_id' => $this->domainId,
        ]);

        // Call the Omarino API to renew the domain
        $domainName = $this->payload['domain'] ?? null;
        $years = intval($this->payload['years'] ?? 1);

        if (!$domainName) {
            Log::error('RenewDomainJob missing domain in payload', ['payload' => $this->payload]);
            return;
        }

        try {
            $apiResponse = $omarino->renewDomain($domainName, $years);
        } catch (Throwable $e) {
            // أي استثناء غير متوقع يرمى هنا ويجعل الـ job يعاد (subject to $tries)
            Log::error('RenewDomainJob exception calling Omarino', [
                'error' => $e->getMessage(),
                'payload' => $this->payload,
            ]);
            throw $e;
        }

        // سجل النتيجة في الـ log
        Log::info('RenewDomainJob api response', ['response' => $apiResponse]);

        // حدث السجل المحلي بناء على الـ API response
        $domain = Domain::find($this->domainId);

        if (! $domain) {
            Log::warning('RenewDomainJob domain record not found', ['domain_record_id' => $this->domainId]);
            return;
        }

        // إذا الـ API رد بمصفوفة أو object، استخدم الـ Repository لتحديث
        if (is_array($apiResponse)) {
            $repo->updateFromApiResponse($domain, $apiResponse);
        } else {
            // في حال رد خام (string) خزّنه كـ raw_response
            $repo->updateFromApiResponse($domain, ['raw' => $apiResponse]);
        }

        // لو الرد فيه status نهائي مثلاً 'completed' أو 'success' نغير الحالة
        if (is_array($apiResponse) && isset($apiResponse['status'])) {
            $domain->status = $apiResponse['status'];
            $domain->save();
        } else {
            // تعيين حالة عامة بعد محاولة التجديد
            $domain->status = $domain->status ?? 'renew-processed';
            $domain->save();
        }

        Log::info('RenewDomainJob finished', ['domain_record_id' => $this->domainId]);
    }

    /**
     * The job failed to process.
     *
     * @param Throwable $exception
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        // Called when the job has exhausted all retries (or thrown an exception not retried)
        Log::error('RenewDomainJob failed permanently', [
            'domain_record_id' => $this->domainId,
            'error' => $exception->getMessage(),
            'payload' => $this->payload,
        ]);

        // Update local DB to reflect failure
        try {
            $domain = Domain::find($this->domainId);
            if ($domain) {
                $domain->status = 'renew-failed';
                $domain->raw_response = array_merge($domain->raw_response ?? [], [
                    'renew_error' => $exception->getMessage(),
                ]);
                $domain->save();
            }
        } catch (Throwable $e) {
            Log::error('RenewDomainJob failed updating domain record after job failure', ['error' => $e->getMessage()]);
        }

        // Optional: notify admin / user via Notification, Mail, Slack etc.
        // e.g. Notification::route('mail', 'admin@example.com')->notify(new DomainRenewFailedNotification(...));
    }
}

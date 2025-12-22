<?php
// app/Models/Transaction.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'gateway_response' => 'array',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
        'failed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'due_at' => 'datetime',
    ];

    protected $appends = [
        'is_successful',
        'is_failed',
        'is_pending',
        'is_refunded',
        'formatted_amount',
        'transaction_type_text'
    ];

    /* ========== CONSTANTS ========== */
    const TYPE_PAYMENT = 'payment';
    const TYPE_REFUND = 'refund';
    const TYPE_DEPOSIT = 'deposit';
    const TYPE_WITHDRAWAL = 'withdrawal';

    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_CANCELLED = 'cancelled';

    /* ========== RELATIONS ========== */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
    

    /* ========== SCOPES ========== */

    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', self::STATUS_REFUNDED);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
    }

    public function scopeByGateway($query, $gateway)
    {
        return $query->where('gateway', $gateway);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByUserId($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /* ========== ACCESSORS ========== */

    public function getIsSuccessfulAttribute(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function getIsFailedAttribute(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function getIsRefundedAttribute(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2) . ' ' . strtoupper($this->currency);
    }

    public function getTransactionTypeTextAttribute(): string
    {
        $types = [
            self::TYPE_PAYMENT => 'دفعة',
            self::TYPE_REFUND => 'استرداد',
            self::TYPE_DEPOSIT => 'إيداع',
            self::TYPE_WITHDRAWAL => 'سحب'
        ];

        return $types[$this->type] ?? $this->type;
    }

    public function getStatusTextAttribute(): string
    {
        $statuses = [
            self::STATUS_PENDING => 'قيد الانتظار',
            self::STATUS_SUCCESS => 'ناجح',
            self::STATUS_FAILED => 'فشل',
            self::STATUS_REFUNDED => 'تم الاسترداد',
            self::STATUS_CANCELLED => 'ملغي'
        ];

        return $statuses[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        $colors = [
            self::STATUS_PENDING => 'warning',
            self::STATUS_SUCCESS => 'success',
            self::STATUS_FAILED => 'danger',
            self::STATUS_REFUNDED => 'info',
            self::STATUS_CANCELLED => 'secondary'
        ];

        return $colors[$this->status] ?? 'secondary';
    }

    /* ========== METHODS ========== */

    public static function generateTransactionId(): string
    {
        $prefix = 'TXN';
        $date = date('Ymd');

        $latest = self::where('transaction_id', 'like', "{$prefix}-{$date}-%")
                      ->latest('id')
                      ->first();

        $number = $latest ? (int) substr($latest->transaction_id, -6) + 1 : 1;

        return sprintf('%s-%s-%06d', $prefix, $date, $number);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            if (empty($transaction->transaction_id)) {
                $transaction->transaction_id = self::generateTransactionId();
            }
            if (empty($transaction->currency)) {
                $transaction->currency = 'USD';
            }
        });
    }

    public function markAsSuccess(array $gatewayData = []): self
    {
        $this->update([
            'status' => self::STATUS_SUCCESS,
            'gateway_response' => array_merge($this->gateway_response ?? [], $gatewayData),
            'paid_at' => now()
        ]);

        // Update related order if exists
        if ($this->order) {
            $this->order->markAsPaid($this->gateway, $this->transaction_id);
        }

        // Update user balance for deposits
        if ($this->type === self::TYPE_DEPOSIT) {
            $this->user->addToBalance($this->amount);
        }

        return $this;
    }

    public function markAsFailed(?string $reason = null): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failed_at' => now(),
            'description' => $reason ?: $this->description
        ]);

        return $this;
    }

    public function markAsRefunded(): self
    {
        $this->update([
            'status' => self::STATUS_REFUNDED,
            'refunded_at' => now()
        ]);

        // Refund to user balance
        if ($this->user && $this->type === self::TYPE_PAYMENT) {
            $this->user->addToBalance($this->amount);
        }

        return $this;
    }

    public function processRefund(float $amount = null): ?self
    {
        $refundAmount = $amount ?? $this->amount;

        if ($this->is_successful && !$this->is_refunded) {
            $refund = self::create([
                'user_id' => $this->user_id,
                'order_id' => $this->order_id,
                'type' => self::TYPE_REFUND,
                'amount' => $refundAmount,
                'currency' => $this->currency,
                'status' => self::STATUS_SUCCESS,
                'gateway' => $this->gateway,
                'description' => 'Refund for transaction ' . $this->transaction_id,
                'reference' => $this->transaction_id,
                'paid_at' => now()
            ]);

            $this->markAsRefunded();
            return $refund;
        }

        return null;
    }

    public function getGatewayLog(): array
    {
        return $this->gateway_response ?? [];
    }

    public function canBeRefunded(): bool
    {
        return $this->is_successful
            && !$this->is_refunded
            && $this->type === self::TYPE_PAYMENT
            && $this->created_at->gt(now()->subDays(30)); // Within 30 days
    }

    public function getDashboardData(): array
    {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'type' => $this->type,
            'type_text' => $this->transaction_type_text,
            'amount' => $this->amount,
            'formatted_amount' => $this->formatted_amount,
            'status' => $this->status,
            'status_text' => $this->status_text,
            'status_color' => $this->status_color,
            'gateway' => $this->gateway,
            'description' => $this->description,
            'date' => $this->created_at->format('Y-m-d H:i'),
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email
            ] : null,
            'order' => $this->order ? [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number
            ] : null,
            'actions' => [
                'can_refund' => $this->canBeRefunded(),
                'can_view' => true,
                'can_cancel' => $this->is_pending
            ]
        ];
    }

    /* ========== STATIC METHODS FOR DASHBOARD ========== */

    public static function getTotalRevenue(): float
    {
        return self::where('type', self::TYPE_PAYMENT)
            ->where('status', self::STATUS_SUCCESS)
            ->sum('amount');
    }

    public static function getTodayRevenue(): float
    {
        return self::where('type', self::TYPE_PAYMENT)
            ->where('status', self::STATUS_SUCCESS)
            ->whereDate('created_at', today())
            ->sum('amount');
    }

    public static function getMonthlyRevenue(): float
    {
        return self::where('type', self::TYPE_PAYMENT)
            ->where('status', self::STATUS_SUCCESS)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');
    }

    public static function getTransactionsSummary(): array
    {
        $total = self::count();
        $successful = self::where('status', self::STATUS_SUCCESS)->count();
        $failed = self::where('status', self::STATUS_FAILED)->count();
        $pending = self::where('status', self::STATUS_PENDING)->count();

        return [
            'total' => $total,
            'successful' => $successful,
            'failed' => $failed,
            'pending' => $pending,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
            'failure_rate' => $total > 0 ? round(($failed / $total) * 100, 2) : 0
        ];
    }

    public static function getRecentTransactions(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return self::with(['user', 'order'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    public static function getGatewayStats(): array
    {
        return self::where('status', self::STATUS_SUCCESS)
            ->selectRaw('gateway, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('gateway')
            ->orderByDesc('total')
            ->get()
            ->toArray();
    }
}

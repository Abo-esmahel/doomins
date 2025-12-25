<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentController extends Controller
{

 


    // عرض صفحة الدفع

    // معالجة الدفع
    public function processPayment(Request $request, $orderId)
    {
        $request->validate([
            'payment_method' => 'required|in:credit_card,paypal,bank_transfer,wallet'
        ]);

        $user = $this->getUser();

        $order = Order::where('user_id', $user->id)
                     ->where('status', 'pending')
                     ->findOrFail($orderId);

        // إذا كان الدفع من الرصيد، استخدم الدالة المخصصة
        if ($request->payment_method === 'wallet') {
            return $this->payWithBalance($request, $orderId);
        }

        DB::beginTransaction();

        try {
            // محاكاة عملية الدفع
            $paymentResult = $this->processPaymentGateway($request->payment_method, $order->total_amount);

            if ($paymentResult['success']) {
                // تحديث حالة الطلب
                $order->update([
                    'status' => 'paid',
                    'payment_status' => 'paid',
                    'payment_method' => $request->payment_method,
                    'transaction_id' => $paymentResult['transaction_id'],
                    'paid_at' => now()
                ]);

                // تسجيل المعاملة
                Transaction::create([
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'type' => Transaction::TYPE_PAYMENT,
                    'amount' => $order->total_amount,
                    'status' => Transaction::STATUS_SUCCESS,
                    'gateway' => $request->payment_method,
                    'transaction_id' => $paymentResult['transaction_id'],
                    'description' => 'دفع لطلب #' . $order->order_number,
                    'gateway_response' => $paymentResult
                ]);

                // إنشاء الفاتورة
                $invoice = Invoice::create([
                    'invoice_number' => Invoice::generateInvoiceNumber(),
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                    'amount' => $order->total_amount,
                    'status' => 'paid',
                    'due_date' => now(),
                    'paid_date' => now(),
                    'payment_method' => $request->payment_method
                ]);

                // تفعيل عناصر الطلب
                foreach ($order->items as $item) {
                    $item->activate();
                }

                // إرسال إيميل التأكيد
                $this->sendPaymentConfirmationEmail($order, $invoice, $user);

                DB::commit();

                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'تم الدفع بنجاح وسيتم تفعيل الخدمة قريباً',
                        'order' => $order->load('items'),
                        'invoice' => $invoice
                    ]);
                }

                return redirect()->route('orders.show', $order->id)
                               ->with('success', 'تم الدفع بنجاح وسيتم تفعيل الخدمة قريباً');

            } else {
                throw new \Exception('فشل في عملية الدفع: ' . $paymentResult['message']);
            }

        } catch (\Exception $e) {
            DB::rollBack();

            // تسجيل محاولة الدفع الفاشلة
            Transaction::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'type' => Transaction::TYPE_PAYMENT,
                'amount' => $order->total_amount,
                'status' => Transaction::STATUS_FAILED,
                'gateway' => $request->payment_method,
                'description' => 'فشل في الدفع لطلب #' . $order->order_number,
                'gateway_response' => ['error' => $e->getMessage()]
            ]);

            // تحديث حالة الطلب بالفشل
            $order->update([
                'payment_status' => 'failed',
                'notes' => 'فشل الدفع: ' . $e->getMessage()
            ]);

            Log::error('Payment Error: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'order_id' => $orderId,
                'payment_method' => $request->payment_method
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'فشل في عملية الدفع: ' . $e->getMessage()
                ], 400);
            }

            return redirect()->route('payment.show', $order->id)
                           ->with('error', 'فشل في عملية الدفع: ' . $e->getMessage());
        }
    }

    // دفع من الرصيد
    public function payWithBalance(Request $request, $orderId)
    {
        $user = $this->getUser();

        $order = Order::where('user_id', $user->id)
                     ->where('status', 'pending')
                     ->findOrFail($orderId);

        DB::beginTransaction();

        try {
            // التحقق من الرصيد الكافي
            if ($user->balance < $order->total_amount) {
                throw new \Exception('رصيدك غير كافي. الرصيد الحالي: ' . $user->balance);
            }

            // خصم من الرصيد
            $user->decrement('balance', $order->total_amount);

            // تحديث حالة الطلب
            $order->update([
                'status' => 'paid',
                'payment_status' => 'paid',
                'payment_method' => 'wallet',
                'transaction_id' => Transaction::generateTransactionId(),
                'paid_at' => now()
            ]);

            // تسجيل المعاملة
            Transaction::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'type' => Transaction::TYPE_PAYMENT,
                'amount' => $order->total_amount,
                'status' => Transaction::STATUS_SUCCESS,
                'gateway' => 'wallet',
                'transaction_id' => Transaction::generateTransactionId(),
                'description' => 'دفع لطلب #' . $order->order_number
            ]);

            // إنشاء الفاتورة
            $invoice = Invoice::create([
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'order_id' => $order->id,
                'user_id' => $user->id,
                'amount' => $order->total_amount,
                'status' => 'paid',
                'due_date' => now(),
                'paid_date' => now(),
                'payment_method' => 'wallet'
            ]);

            // تفعيل عناصر الطلب
            foreach ($order->items as $item) {
                $item->activate();
            }

            // إرسال إيميل التأكيد
            $this->sendPaymentConfirmationEmail($order, $invoice, $user);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم الدفع من الرصيد بنجاح',
                    'order' => $order->load('items'),
                    'invoice' => $invoice
                ]);
            }

            return redirect()->route('orders.show', $order->id)
                           ->with('success', 'تم الدفع من الرصيد بنجاح');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Wallet Payment Error: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'order_id' => $orderId
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 400);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    // محاكاة بوابة الدفع
    private function processPaymentGateway($method, $amount)
    {
        // في الواقع هنا تتصل ببوابة الدفع الحقيقية
        // هذا مثال مبسط

        $methods = [
            'credit_card' => 'بطاقة ائتمان',
            'paypal' => 'باي بال',
            'bank_transfer' => 'تحويل بنكي'
        ];

        // محاكاة النجاح (90% نجاح)
        $success = rand(1, 100) <= 90;

        if ($success) {
            return [
                'success' => true,
                'transaction_id' => Transaction::generateTransactionId(),
                'method' => $methods[$method] ?? $method,
                'amount' => $amount,
                'timestamp' => now(),
                'gateway_response' => [
                    'status' => 'approved',
                    'auth_code' => strtoupper(bin2hex(random_bytes(8))),
                    'reference' => 'REF-' . time()
                ]
            ];
        } else {
            return [
                'success' => false,
                'message' => 'فشل في المعاملة. يرجى المحاولة مرة أخرى أو استخدام طريقة دفع أخرى.',
                'error_code' => 'PAYMENT_FAILED',
                'gateway_response' => [
                    'status' => 'declined',
                    'reason' => 'insufficient_funds'
                ]
            ];
        }
    }

    // الحصول على طرق الدفع المتاحة


    // إرسال إيميل تأكيد الدفع
    private function sendPaymentConfirmationEmail($order, $invoice, $user)
    {
        try {
            $data = [
                'order' => $order->load('items.product'),
                'invoice' => $invoice,
                'user' => $user
            ];

            // Mail::to($user->email)->send(new PaymentConfirmed($data));

            Log::info('تم إرسال إيميل تأكيد الدفع لطلب #' . $order->order_number . ' إلى ' . $user->email);
        } catch (\Exception $e) {
            Log::error('فشل إرسال إيميل تأكيد الدفع: ' . $e->getMessage());
        }
    }

    /**
     * دالة مساعدة للحصول على المستخدم الحالي
     */
    private function getUser(): User
    {
        $user = auth('sanctum')->user();

        if (!$user) {
            throw new \Illuminate\Auth\AuthenticationException('يجب تسجيل الدخول أولاً');
        }

        return $user;
    }

    /**
     * دالة مساعدة للحصول على user ID
     */
    private function getUserId(): int
    {
        $user = $this->getUser();
        return $user->id;
    }
}

<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{

    public function show()
    {
        $user = auth('sanctum')->user();
        if(!$user){
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
         if($user->status != 'active'){
            return response()->json([
                'success' => false,
                'message' => 'not active user'
            ], 401);
        }
        $cart = Cart::where('user_id', $user->id)
            ->where('is_active', true)
            ->with(['items.product.productable'])
            ->first();

        if (!$cart || $cart->items->isEmpty()) {

            return response()->json([
                'cart' => null,
                'total' => 0,
                'message' => 'عربة التسوق فارغة',
            ]);
        }

        $total = $cart->items->sum(function ($item) {
            return $item->quantity * $item->price;
        });

       return response()->json([
            'cart' => $cart,
            'total' => $total,
        ]);
    }



    public static function process(Order $order)
    {
        $user = $order->user;
        if(!$user){
            return response()->json([
                'success' => false,
                'message' => 'المستخدم غير مصرح له',
            ], 401);
        }
       if(!$order || $order->user_id !== $user->id){
        return response()->json([
            'success' => false,
            'message' => 'الطلب غير موجود أو لا تملك صلاحية الوصول إليه',
        ], 404);
       }


     if(true)
        return response()->json([
            'message' => 'تم معالجة الدفع بنجاح',
            'success' => true,
            'order' => $order,
            'paid_at' => now(),
        ]);
       else return response()->json([
            'message' => 'فشل في معالجة الدفع',
            'success' => false,
        ], 400);



 }





    public function quickPay(Request $request)
    {
        $request->validate([
            'cart_id' => 'required|exists:carts,id',
        ]);

        $user = auth('sanctum')->user();

        DB::beginTransaction();

        try {
            $cart = Cart::where('id', $request->cart_id)
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->with(['items.product'])
                ->firstOrFail();

            if ($cart->items->isEmpty()) {
                throw new \Exception('عربة التسوق فارغة');
            }

            $total = $cart->items->sum(function ($item) {
                return $item->quantity * $item->price;
            });

            if ($user->balance < $total) {
                return response()->json([
                    'success' => false,
                    'message' => 'رصيدك غير كافي. الرصيد المطلوب: ' . $total,
                ], 400);
            }

            $order = Order::create([
                'order_number' => 'ORD-' . strtoupper(uniqid()),
                'user_id' => $user->id,
                'cart_id' => $cart->id,
                'total' => $total,
                'status' => 'paid',
                'payment_method' => 'wallet',
                'paid_at' => now(),
            ]);

            $user->decrement('balance', $total);

          DB::table('transactions')->insert([
                'transaction_id' => 'TXN-' . strtoupper(uniqid()),
                'user_id' => $user->id,
                'order_id' => $order->id,
                'type' => 'payment',
                'status' => 'completed',
                'amount' => $total,
                'currency' => 'SAR',
                'description' => 'دفع طلب #' . $order->order_number . ' باستخدام المحفظة',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $cart->update(['is_active' => false]);
            Cart::create([
                'user_id' => $user->id,
                'is_active' => true,
            ]);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم الدفع بنجاح',
                    'order' => $order->load(['cart.items.product.productable']),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم الدفع بنجاح',
                'order' => $order->load(['cart.items.product.productable']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }

            return back()->with('error', $e->getMessage());
        }
    }


    public function verifyStock(Request $request)
    {
        $user = auth('sanctum')->user();
   if(!$user){
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
         if($user->status != 'active'){
            return response()->json([
                'success' => false,
                'message' => 'not active user'
            ], 401);
        }
        $cart = Cart::where('user_id', $user->id)
            ->where('is_active', true)
            ->with(['items.product.productable'])
            ->first();

        if (!$cart) {
            return response()->json([
                'available' => false,
                'message' => 'لا توجد عربة تسوق نشطة',
            ]);
        }

        $unavailableItems = [];

        foreach ($cart->items as $item) {
            $product = $item->product;

            if (!$product) {
                $unavailableItems[] = [
                    'id' => $item->id,
                    'name' => $item->product_name,
                    'reason' => 'المنتج غير موجود',
                ];
                continue;
            }

            $actualProduct = $product->productable;

            if (!$actualProduct) {
                $unavailableItems[] = [
                    'id' => $item->id,
                    'name' => $item->product_name,
                    'reason' => 'المنتج غير متوفر',
                ];
                continue;
            }

            // التحقق من توفر الدومين
            if ($product->type === 'domain') {
                if (!$actualProduct->available) {
                    $unavailableItems[] = [
                        'id' => $item->id,
                        'name' => $item->product_name,
                        'reason' => 'النطاق غير متوفر',
                    ];
                }
            }

            if ($product->type === 'server') {
                if ($actualProduct->status !== 'available') {
                    $unavailableItems[] = [
                        'id' => $item->id,
                        'name' => $item->product_name,
                        'reason' => 'السيرفر غير متوفر',
                    ];
                }
            }
        }

        return response()->json([
            'available' => empty($unavailableItems),
            'items' => $unavailableItems,
            'cart_id' => $cart->id,
        ]);
    }


    // public function partialPayment(Request $request)
    // {
    //     $request->validate([
    //         'wallet_amount' => 'required|numeric|min:0',
    //         'remaining_method' => 'required|in:cash,credit_card',
    //     ]);

    //     $user = auth('sanctum')->user();

    //     DB::beginTransaction();

    //     try {
    //         $cart = Cart::where('user_id', $user->id)
    //             ->where('is_active', true)
    //             ->with(['items.product'])
    //             ->firstOrFail();

    //         $total = $cart->items->sum(function ($item) {
    //             return $item->quantity * $item->price;
    //         });

    //         $walletAmount = min($request->wallet_amount, $user->balance);
    //         $walletAmount = min($walletAmount, $total);

    //         if ($walletAmount > 0) {
    //             $user->decrement('balance', $walletAmount);
    //         }

    //         $remainingAmount = $total - $walletAmount;

    //         $order = Order::create([
    //             'order_number' => 'ORD-' . strtoupper(uniqid()),
    //             'user_id' => $user->id,
    //             'cart_id' => $cart->id,
    //             'total' => $total,
    //             'status' => $remainingAmount > 0 ? 'pending' : 'paid',
    //             'payment_method' => $request->remaining_method,
    //             'paid_at' => $remainingAmount === 0 ? now() : null,
    //             'billing_info' => [
    //                 'wallet_payment' => $walletAmount,
    //                 'remaining_payment' => $remainingAmount,
    //                 'payment_method' => $request->remaining_method,
    //             ],
    //         ]);

    //         $cart->update(['is_active' => false]);
    //         Cart::create([
    //             'user_id' => $user->id,
    //             'is_active' => true,
    //         ]);

    //         DB::commit();

    //         return redirect()->route('orders.show', $order->id)
    //             ->with('success', sprintf(
    //                 'تم الدفع %s من المحفظة. المبلغ المتبقي: %s',
    //                 $walletAmount,
    //                 $remainingAmount
    //             ));

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return back()->with('error', $e->getMessage());
    //     }
    // }



}

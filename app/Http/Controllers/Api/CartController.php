<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{

    public function index()
    {
        $user = auth('sanctum')->user();

        $cart = Cart::where('user_id', $user->id)
            ->where('is_active', true)
            ->with(['items.product.productable'])
            ->first();

        return response()->json([
            'cart' => $cart,
        ]);}


        public function add(Request $request, $productId)
        {
            $request->validate([
                'quantity' => 'required|integer|min:1|max:10',
                'billing_period' => 'required|in:monthly,yearly',
            ]);

            $product = Product::find($productId);
             if(!$product){
                return response()->json([
                    'error' => 'المنتج غير موجود.',
                ], 404);
            }
            $cart = Cart::firstOrCreate([
                'user_id' => auth('sanctum')->id(),
                'is_active' => true,
            ], [
                'session_id' => null,
            ]);

            $price = $request->billing_period == 'yearly'
                ? $product->price_yearly
                : $product->price_monthly;

            $cart->items()->updateOrCreate([
                'product_id' => $product->id,
                'billing_period' => $request->billing_period,
            ], [
                'quantity' => $request->quantity,
                'price' => $price,
                'product_name' => $product->name,
            ]);
             return response()->json([
                'message' => 'تم إضافة المنتج إلى السلة بنجاح.',
            ]);
        }


    public function update(Request $request, $itemId)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:10',
        ]);

        $item = CartItem::whereHas('cart', function($query) {
            $query->where('user_id', Auth::id())
                  ->where('is_active', true);
        })->findOrFail($itemId);

        $item->update(['quantity' => $request->quantity]);

        return response()->json([
            'message' => 'تم تحديث الكمية',
            'cart_item' => $item,
        ]);
    }


    public function remove($itemId)
    {
        $item = CartItem::whereHas('cart', function($query) {
            $query->where('user_id', Auth::id())
                  ->where('is_active', true);
        })->findOrFail($itemId);

        $item->delete();

      return response()->json([
            'message' => 'تم إزالة العنصر من السلة',
        ]);
    }


    public function clear()
    {
        $cart = Cart::where('user_id', Auth::id())
            ->where('is_active', true)
            ->first();

        if ($cart) {
            $cart->items()->delete();
        }

        return response()->json([
            'message' => 'تم تفريغ السلة',
        ]);
    }

  //شراء السلة بالكامل
    public function purchase(Request $request)
    {
        $request->validate([
            'payment_method' => 'required|in:balance,credit_card',
        ]);

        $user = auth('sanctum')->user();
      if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $cart = Cart::where('user_id', $user->id)
            ->where('is_active', true)
            ->with('items.product.productable')
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'error' => 'السلة فارغة.',
            ], 400);
        }
      $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'user_id' => $user->id,
            'cart_id' => $cart->id,
            'total' => $cart->items->sum(function($item) {
                return $item->price * $item->quantity;
            }),
            'status' => 'pending',
            'transaction_type' => 'new_order',
            'order_type' => 'cart',
            'payment_method' => $request->payment_method,
            'billing_info' => null,
            'description' => 'Purchase of cart items',
            'due_date' => now(),
        ]);
        $result = CheckoutController::process($order);




        if($result['success']){
            
        $cart->is_active = false;
        $cart->save();
        
        foreach($cart->items as $item){
            $product = $item->product;
            $productable = $product->productable;
            $productable->available = false;
            $productable->status = 'sold_out';
            $productable->save();
        }


         $invoice = Invoice::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'total_amount' => $order->total,
            'status' => 'paid',
            'due_date' => now(),
            'paid_date' => $result['paid_at'],
            'payment_method' => $request->payment_method,
            'payment_reference' => 'PAY-' . strtoupper(uniqid()),
            'items' => $cart->items->map(function($item) {
                return [
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                ];})->toArray(),
            'billing_info' => null,
        ]);
         $transaction = Transaction::create([
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'order_id' => $order->id,
            'type' => 'payment',
            'status' => 'completed',
            'amount' => $order->total,
            'fee' => 0,
            'currency' => 'SAR',
            'gateway' => $request->payment_method,
            'gateway_transaction_id' => 'TXN-' . strtoupper(uniqid()),
            'description' => 'Payment for order #' . $order->order_number,
            'metadata' => null,
            'gateway_response' => null,
               ]);

          return response()->json([
            'message' => 'تم شراء السلة بنجاح.',
            'order' => $order,
            'invoice' => $invoice,
            'transaction' => $transaction,
        ]);
        }

        

        return response()->json([
            'message' => 'تم شراء السلة بنجاح.',
        ]);
    }

}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Server;
use App\Models\Product;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use  App\http\Controllers\Api\checkoutController;
use Dotenv\Validator;

class UserController extends Controller
{
    /**
     * Get user profile
     */
    public function profile()
    {
        $user = auth('sanctum')->user();
      if(!$user){
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'balance' => $user->balance,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $user = auth('sanctum')->user();
        if(!$user){
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        $user->update($request->only(['name', 'phone']));

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الملف الشخصي بنجاح',
            'user' => $user
        ]);
    }

    /**
     * Update user password
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = auth('sanctum')->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'كلمة المرور الحالية غير صحيحة'
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث كلمة المرور بنجاح'
        ]);
    }


     public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = auth('sanctum')->user();
      if(!$user){
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'كلمة المرور الحالية غير صحيحة'
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث كلمة المرور بنجاح'
        ]);
    }

    public function userDomains()
{
    $user = auth('sanctum')->user();

    if (!$user) {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $domains = $user->domains()
        ->where('isActive', true)
        ->with(['product'])
        ->paginate(15);

    $active_domains_count = $domains->where('expires_at_in_user', '>', now())->count();
    $expiring_soon_count = $user->domains()
        ->where('expires_at_in_user', '<=', now()->addDays(30))
        ->where('expires_at_in_user', '>', now())
        ->count();

    return response()->json([
        'success' => true,
        'domains' => $domains,
        'total_domains' => $domains->total(),
        'active_domains' => $active_domains_count,
        'expiring_soon' => $expiring_soon_count,
    ]);
}
    /**
     * Get active domains
     */
    public function activeDomains()
    {
        $user = auth('sanctum')->user();
      if(!$user){
      return response()->json(['error'=>'nooooo']);
      }
        if($user->status != 'active'){
            return response()->json([
                'success' => false,
                'message' => 'not active user'
            ], 401);
        }
        $domains = $user->domains()
            ->where('isActive', true)
            ->with('active_in_user',true)

            ->with(['product'])
            ->paginate(15);
          if(!$domains || $domains->isEmpty()) {
                return response()->json([
                    'message' => 'لا توجد دومينات نشطة مرتبطة بحسابك',
                    'success' => true,
                    'domains' => []
                ]);
            }

        return response()->json([
            'success' => true,
            'domains' => $domains,

        ]);
    }

    /**
     * Get expiring domains
     */
//searchUserServers
    public function searchUserServers(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2',
        ]);

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
        $servers = $user->servers()
            ->where('hostname', 'LIKE', "%{$request->query}%")
            ->orWhere('ip_address', 'LIKE', "%{$request->query}%")
            ->with(['product'])
            ->paginate(15);

        return response()->json([
            'success' => true,
            'servers' => $servers,
        ]);
    }

//searchUserDomains
public function searchUserDomains(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2',
        ]);

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
        $domains = $user->domains()
            ->where('name', 'LIKE', "%{$request->query}%")
            ->orWhere('tld', 'LIKE', "%{$request->query}%")
            ->with(['product'])
            ->paginate(15);

        return response()->json([
            'success' => true,
            'domains' => $domains,
        ]);
    }

    //dominds expired

    public function expiredDomains()
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
        $domains = $user->domains()
            ->where('isActive', true)
            ->where('expires_at_in_user', '<=', now())
            ->with(['product'])
            ->paginate(15);
        if(!$domains || $domains->isEmpty()) {
            return response()->json([
                'message' => 'لا توجد دومينات منتهية الصلاحية',
                'success' => true,
                'domains' => []
            ]);
        }
        return response()->json([
            'message' => 'قائمة الدومينات المنتهية الصلاحية',
            'success' => true,
            'domains' => $domains
        ]);
    }

    public function expiringDomains()
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
        $domains = $user->domains()
            ->where('isActive', true)
            ->where('active_in_user',true)
            ->where('expires_at_in_user', '<=', now()->addDays(30))
            ->where('expires_at_in_user', '>', now())
            ->with(['product'])
            ->paginate(15);
        if(!$domains || $domains->isEmpty()) {
            return response()->json([
                'message' => 'لا توجد دومينات على وشك الانتهاء',
                'success' => true,
                'domains' => []
            ]);
        }
        return response()->json([
            'message' => 'قائمة الدومينات التي ستنتهي صلاحيتها قريباً',
            'success' => true,
            'domains' => $domains
        ]);
    }

    /**
     * Show specific domain
     */
    public function showDomain($id)
    {
        $user = auth('sanctum')->user();

      if(!$user){
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

          $domain=Domain::find($id);
        if(!$domain || $domain->user_id != $user->id){
            return response()->json([
                'success' => false,
                'message' => 'الدومين غير موجود أو لا تملك صلاحية الوصول إليه'
            ], 404);
        }
       return response()->json([
            'status' => $domain && $domain->expires_at_in_user && $domain->expires_at_in_user < now() ? 'expired' : 'active',
            'success' => true,
            'domain' => $domain
        ]);}
    /*
     *Renew domain
     */

    public function renewDomain(Request $request, $id)
    {
        $request->validate([
            'period' => 'required|integer|min:1|max:10',
            'billing_period' => 'required|in:monthly,yearly',
            'payment_method' => 'required|in:balance,credit_card,paypal,bank_transfer,other',
        ]);




        $user = auth('sanctum')->user();
        if(!$user){
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        $domain = Domain::find($id);
      if (!$domain || $domain->user_id != $user->id) {
         return response()->json([
                        'success' => false,
                        'message' => 'لا تملك صلاحية تجديد هذا الدومين'
                   ], 403);
        }


       if($domain->expires_at_in_user && $domain->expires_at_in_user > now()){
            return response()->json([
                'success' => false,
                'message' => 'الدومين لا يحتاج إلى تجديد في الوقت الحالي'
            ], 422);
        }

          if($request->billing_period == 'yearly'){
        $total = $domain->price_yearly * $request->period;
       }else{
        $total = $domain->price_monthly * $request->period;
          }


           $order = Order::create([
                'order_number' => 'ORD-' . strtoupper(uniqid()),
                'user_id' => $user->id,
                'cart_id' => null,
                'total' => $total,
                'status' => 'pending',
                'transaction_type' => 'renewal',
                'order_type' => 'domain',
                'payment_method' => $request->payment_method,
                'paid_at' => null,
                'billing_info' => null,
            ]);


        $result= CheckoutController::process($order);


       if($result['success'])

        {
         if($request->billing_period == 'yearly'){
                $domain->expires_at_in_user = $domain->expires_at_in_user->now()->addYears($request->period);
            }else{
                $domain->expires_at_in_user = $domain->expires_at_in_user->now()->addMonths($request->period);
            }
            $domain->active_in_user = true;
            $domain->save();

            $invoice = Invoice::create([
                'invoice_number' => 'INV-' . strtoupper(uniqid()),
                'user_id' => $user->id,
                'order_id' => $order->id,
                'subtotal' => $total,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => $total,
                'payment_method' => $request->payment_method,
               'paid_date' => $result['paid_at'],
                'status' => 'paid',
                'due_date' => now(),
                'description' => "تجديد دومين {$domain->domain_name} لمدة {$request->period} {$request->billing_period}",
            ]);

            $transaction = Transaction::create([
                'user_id' => $user->id,
                'invoice_id' => $invoice->id,
                'order_id' => $order->id,
                'type' => 'payment',
                'status' => 'completed',
                'amount' => $total,
                'fee' => 0,
                'currency' => 'SAR',
                'gateway' => $request->payment_method,
                'gateway_transaction_id' => null,
                'description' => "دفع لتجديد دومين {$domain->domain_name}",
                'metadata' => null,
                'gateway_response' => null,
            ]);


        return response()->json([
                'success' => true,
                'invoice' => $invoice,
                'transaction' => $transaction,
                'message' => 'تم تجديد الدومين بنجاح',
                'new_expiry' => $domain->expires_at->format('Y-m-d')
            ]);


                   }
                  return response()->json($result);

    }
// server المنتهية صلاحيتها
public function expiredServers()
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
        $servers = $user->servers()
            ->where('isActive', true)
            ->where('expires_at_in_user', '<=', now())
            ->with(['product'])
            ->paginate(15);
        if(!$servers || $servers->isEmpty()) {
            return response()->json([
                'message' => 'لا توجد سيرفرات منتهية الصلاحية',
                'success' => true,
                'servers' => []
            ]);
        }
        return response()->json([
            'message' => 'قائمة السيرفرات المنتهية الصلاحية',
            'success' => true,
            'servers' => $servers
        ]);
    }
    /**
     * Get user servers
     */
    public function userServers()
    {
        $user = auth('sanctum')->user();
      if(!$user){
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $servers = $user->servers()
            ->where('isActive', true)

            ->with(['product'])
            ->paginate(15);
          if(!$servers || $servers->isEmpty()) {
                return response()->json([
                    'message' => 'لا توجد سيرفرات مرتبطة بحسابك',
                    'success' => true,
                    'servers' => []
                ]);
            }
        return response()->json([
            'success' => true,
            'servers' => $servers,
            'message' => 'قائمة السيرفرات المرتبطة بحسابك',
        ]);
    }

    /**
     * Get active servers
     */
    public function activeServers()
    {
        $user = auth('sanctum')->user();
        if(!$user){
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        $servers = $user->servers()
            ->where('isActive', true)
            ->with('expires_at_in_user', '>', now())
            ->with(['product'])
            ->paginate(15);
          if(!$servers || $servers->isEmpty()) {
                return response()->json([
                    'message' => 'لا توجد سيرفرات نشطة مرتبطة بحسابك',
                    'success' => true,
                    'servers' => []
                ]);
            }
        return response()->json([
            'count' => $servers->count(),
            'success' => true,
            'servers' => $servers
        ]);
    }


    public function showServer($id)
    {
        $user = auth('sanctum')->user();
         if(!$user){
                return response()->json([
                 'success' => false,
                 'message' => 'Unauthorized'
                ], 401);
          }

        $server = Server::find($id);

        if(!$server || $server->user_id != $user->id){
            return response()->json([
                'success' => false,
                'message' => 'السيرفر غير موجود أو لا تملك صلاحية الوصول إليه'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'server' => $server,
        ]);
    }



    public function expiringServers()
    {
        $user = auth('sanctum')->user();
       if(!$user){
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        $servers = $user->servers()
            ->where('isActive', true)
            ->where('expires_at_in_user', '<=', now()->addDays(30))
            ->where('expires_at_in_user', '>', now())
            ->with(['product'])
            ->paginate(15);
        if(!$servers || $servers->isEmpty()) {
            return response()->json([
                'message' => 'لا توجد سيرفرات على وشك الانتهاء',
                'success' => true,
                'servers' => []
            ]);
        }
        return response()->json([
            'message' => 'قائمة السيرفرات التي ستنتهي صلاحيتها قريباً',
            'success' => true,
            'servers' => $servers,
            'count' => $servers->count(),
        ]);
    }
    /**
     * Get renewServer
     */

    public function renewServer(Request $request, $id)
    {
        $request->validate([
            'period' => 'required|integer|min:1|max:12',
            'billing_period' => 'required|in:monthly,yearly',
            'payment_method' => 'required|in:balance,credit_card,paypal,bank_transfer,other',
        ]);

        $user = auth('sanctum')->user();
        if(!$user){
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $server = Server::find($id);

        if(!$server || $server->user_id != $user->id){
            return response()->json([
                'success' => false,
                'message' => 'السيرفر غير موجود أو لا تملك صلاحية الوصول إليه'
            ], 404);
        }

         if($server->expires_at_in_user && $server->expires_at_in_user > now()){
                return response()->json([
                 'success' => false,
                 'message' => 'السيرفر لا يحتاج إلى تجديد في الوقت الحالي'
                ], 422);
          }
        if($request->billing_period == 'yearly'){
            $total = $server->price_yearly * $request->period;
        }else{
            $total = $server->price_monthly * $request->period;
        }


           $order = Order::create([
                'order_number' => 'ORD-' . strtoupper(uniqid()),
                'user_id' => $user->id,
                'cart_id' => null,
                'total' => $total,
                'status' => 'pending',
                'transaction_type' => 'renewal',
                'order_type' => 'server',
                'payment_method' => $request->payment_method,
                'paid_at' => null,
                'billing_info' => null,
            ]);
           $result= CheckoutController::process($order);

      if(!$result['success']){

          if($request->billing_period == 'yearly'){
                $server->expires_at_in_user = $server->expires_at_in_user->now()->addYears($request->period);
            }else{
                $server->expires_at_in_user = $server->expires_at_in_user->now()->addMonths($request->period);
            }
            $server->active_in_user = true;
            $server->save();

            $invoice = Invoice::create([
                'invoice_number' => 'INV-' . strtoupper(uniqid()),
                'user_id' => $user->id,
                'order_id' => $order->id,
                'subtotal' => $total,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => $total,
                'payment_method' => $request->payment_method,
                'paid_date' => $result['paid_at'],
                'status' => 'paid',
                'due_date' => now(),
                'description' => "تجديد سيرفر {$server->hostname} لمدة {$request->period} {$request->billing_period}",
            ]);

            $transaction = Transaction::create([
                'user_id' => $user->id,
                'invoice_id' => $invoice->id,
                'order_id' => $order->id,
                'type' => 'payment',
                'status' => 'completed',
                'amount' => $total,
                'fee' => 0,
                'currency' => 'SAR',
                'gateway' => $request->payment_method,
                'gateway_transaction_id' => null,
                'description' => "دفع لتجديد سيرفر {$server->hostname}",
                'metadata' => null,
                'gateway_response' => null,
            ]);


        return response()->json([
                'success' => true,
                'invoice' => $invoice,
                'transaction' => $transaction,
                'message' => 'تم تجديد السيرفر بنجاح',
                'new_expiry' => $server->expires_at->format('Y-m-d')
            ]);


       }

       return response()->json($result);
    }

    public function userProducts()
    {
        $user = auth('sanctum')->user();
       if(!$user){
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        $products = Product::whereHas('orders', function($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->where('status', 'paid');
            })
            ->with(['productable'])
            ->paginate(15);
           if(!$products || $products->isEmpty()) {
                return response()->json([
                    'message' => 'لا توجد منتجات مرتبطة بحسابك',
                    'success' => true,
                    'products' => []
                ]);
            }
        return response()->json([
            'success' => true,
            'products' => $products
        ]);
    }


    public function activeProducts()
    {
        $user = auth('sanctum')->user();
       if(!$user){
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        $products['domains'] = $this->activeDomains();
        $products['servers'] = $this->activeServers();


        return response()->json([
            'success' => true,
            'products' => $products
        ]);
    }

    /**
     * Get user invoices
     */
    public function invoices()
    {
        $user = auth('sanctum')->user();
        if(!$user){
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        $invoices = Invoice::where('user_id', $user->id)
            ->orderBy('due_date', 'desc')
            ->paginate(15);
       if(!$invoices || $invoices->isEmpty()) {
            return response()->json([
                'message' => 'لا توجد فواتير مرتبطة بحسابك',
                'success' => true,
                'invoices' => []
            ]);
        }
        return response()->json([
            'success' => true,
            'invoices' => $invoices,
            'total_amount' => $invoices->sum('amount'),
            'paid_amount' => $invoices->where('status', 'paid')->sum('amount'),
            'unpaid_amount' => $invoices->where('status', 'unpaid')->sum('amount'),
        ]);
    }

    /**
     * Get unpaid invoices
     */
    public function unpaidInvoices()
    {
        $user = auth('sanctum')->user();

        $invoices = Invoice::where('user_id', $user->id)
            ->where('status', 'unpaid')
            ->orderBy('due_date', 'asc')
            ->paginate(15);
        if(!$invoices || $invoices->isEmpty()) {
            return response()->json([
                'message' => 'لا توجد فواتير غير مدفوعة مرتبطة بحسابك',
                'success' => true,
                'invoices' => []
            ]);
        }
        return response()->json([
            'success' => true,
            'invoices' => $invoices,
            'total_due' => $invoices->sum('amount'),
        ]);
    }

    /**
     * Get user transactions
     */
    public function transactions()
    {
        $user = auth('sanctum')->user();

        $transactions = Transaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        if(!$transactions || $transactions->isEmpty()) {
            return response()->json([
                'message' => 'لا توجد معاملات مرتبطة بحسابك',
                'success' => true,
                'transactions' => []
            ]);
        }
        return response()->json([
            'success' => true,
            'transactions' => $transactions
        ]);
    }

    /**
     * Get recent transactions
     */
    public function recentTransactions()
    {
        $user = auth('sanctum')->user();

        $transactions = Transaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
     if(!$transactions || $transactions->isEmpty()) {
            return response()->json([
                'message' => 'لا توجد معاملات مرتبطة بحسابك',
                'success' => true,
                'transactions' => []
            ]);
        }
        return response()->json([
            'success' => true,
            'transactions' => $transactions
        ]);



    }


    public function purchaseDomain($productId)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        $product = Product::find($productId);
        if (!$product || $product->productable_type !== Domain::class) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid domain product'
            ], 404);
        }
        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'user_id' => $user->id,
            'cart_id' => null,
            'total' => $product->price,
            'status' => 'pending',
            'transaction_type' => 'new_order',
            'order_type' => 'domain',
            'payment_method' => 'balance',
            'paid_at' => now(),
            'billing_info' => null,
        ]);

        $result = CheckoutController::process($order);
        if (!$result['success']) {
            $domain = $product->productable;
            $domain->available = false;
            $domain->status = 'sold_out';
            $domain->save();
        }

        $domain = $product->productable;
        $domain->user_id = $user->id;
        $domain->save();
      $invoice = Invoice::create([
            'invoice_number' => 'INV-' . strtoupper(uniqid()),
            'user_id' => $user->id,
            'order_id' => $order->id,
            'subtotal' => $product->price,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => $product->price,
            'payment_method' => 'balance',
            'paid_date' => $result['paid_at'],
            'status' => 'paid',
            'due_date' => now(),
            'description' => "Purchase of domain {$domain->domain_name}",
        ]);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'order_id' => $order->id,
            'type' => 'payment',
            'status' => 'completed',
            'amount' => $product->price,
            'fee' => 0,
            'currency' => 'SAR',
            'gateway' => 'balance',
            'gateway_transaction_id' => null,
            'description' => "Payment for domain {$domain->domain_name}",
            'metadata' => null,
            'gateway_response' => null,
        ]);

        return response()->json([
            'transaction' => $transaction,
            'invoice' => $invoice,
            'success' => true,
            'message' => 'Domain purchased successfully',
            'domain' => $domain
        ]);

       return response()->json($result);
    }

    public function purchaseServer($productID)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

      $product = Product::find($productID);
        if (!$product || $product->productable_type !== Server::class) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid server product'
            ], 404);
        }
        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'user_id' => $user->id,
            'cart_id' => null,
            'total' => $product->price,
            'status' => 'pending',
            'transaction_type' => 'new_order',
            'order_type' => 'server',
            'payment_method' => 'balance',
            'billing_info' => null,
        ]);

        $result = CheckoutController::process($order);
        if (!$result['success']) {

            $server = $product->productable;
            $server->available = false;
            $server->status = 'sold_out';
            $server->save();
        $server = $product->productable;
        $server->user_id = $user->id;
        $server->save();
      $invoice = Invoice::create([
            'invoice_number' => 'INV-' . strtoupper(uniqid()),
            'user_id' => $user->id,
            'order_id' => $order->id,
            'subtotal' => $product->price,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => $product->price,
            'payment_method' => 'balance',
            'paid_date' => $result['paid_at'],
            'status' => 'paid',
            'due_date' => now(),
            'description' => "Purchase of server {$server->hostname}",
        ]);
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'order_id' => $order->id,
            'type' => 'payment',
            'status' => 'completed',
            'amount' => $product->price,
            'fee' => 0,
            'currency' => 'SAR',
            'gateway' => 'balance',
            'gateway_transaction_id' => null,
            'description' => "Payment for server {$server->hostname}",
            'metadata' => null,
            'gateway_response' => null,
        ]);

        return response()->json([
            'transaction' => $transaction,
            'invoice' => $invoice,
            'success' => true,
            'message' => 'Server purchased successfully',
            'server' => $server
        ]);

            }
      return response()->json($result);



    }


}

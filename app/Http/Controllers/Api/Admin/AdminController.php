<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\DomainController;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Server;
use App\Models\Product;
use App\Models\User;
use App\Models\Order;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{


    /**
     * لوحة التحكم الرئيسية للادمن
     */
    public function dashboard()
    {
        $stats = [
            'total_users' => User::count(),
            'total_domains' => Domain::count(),
            'total_servers' => Server::count(),
            'total_products' => Product::count(),
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'revenue_today' => Order::whereDate('created_at', today())
                ->where('status', 'paid')
                ->sum('total'),
            'revenue_month' => Order::whereMonth('created_at', date('m'))
                ->where('status', 'paid')
                ->sum('total'),
        ];

        $recent_orders = Order::with('user')
            ->latest()
            ->limit(10)
            ->get();

        $recent_users = User::latest()
            ->limit(10)
            ->get();

        return response()->json([
            'stats' => $stats,
            'recent_orders' => $recent_orders,
            'recent_users' => $recent_users,
        ]);
    }

    // ==================== إدارة الدومينات ====================
//عرض جميع الدومينات
    public function domainsIndex()
    {

        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }

        $domains = Domain::with('product')
            ->latest()
            ->paginate(20);

        DB::table('admin_logs')->insert([
            'admin_id' => $user->id,
            'action' => 'view',
            'table_name' => 'domains',
            'record_id' => null,
            'details' => 'تم عرض جميع الدومينات بنجاح.',
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'domains' => $domains,

        ]);
    }
    /**
     *الغير متاحة عرض جميع الدومينات
     */


    public function unavailableDomains()
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $domains = Domain::where('available', false)
            ->with('product')
            ->latest()
            ->paginate(20)
            ->get();

            DB::table('admin_logs')->insert([
                'admin_id' => $user->id,
                'action' => 'view',
                'table_name' => 'domains',
                'record_id' => null,
                'details' => 'تم عرض الدومينات الغير متاحة بنجاح.',
                'ip_address' => request()->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'domains' => $domains,
            'message' => 'تم جلب الدومينات الغير متاحة بنجاح.',

        ]);
    }

    //عرض الدومينات المنتهية صلاحيتها من الشركة الأم
    public function domainsUnavailable()
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $domains = Domain::where('isActive', false)
            ->with('product')
            ->latest()
            ->paginate(20)
            ->get();

                DB::table('admin_logs')->insert([
                    'admin_id' => $user->id,
                    'action' => 'view',
                    'table_name' => 'domains',
                    'record_id' => null,
                    'details' => 'تم عرض الدومينات المنتهية صلاحيتها بنجاح.',
                    'ip_address' => request()->ip(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        return response()->json([
            'domains' => $domains,
            'message' => 'تم جلب الدومينات المنتهية صلاحيتها بنجاح.',
        ]);
    }

 // الدومينات المنتهية صلاحيتها لدى المستخدمين
    public function domainsExpiredInUser()
   {

    $user = auth('sanctum')->user();
    if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $domains = Domain::where('active_in_user', false)
           ->with('owner')
            ->with('product')
            ->latest()
            ->paginate(20)
            ->get();

              DB::table('admin_logs')->insert([
            'admin_id' => $user->id,
            'action' => 'view',
            'table_name' => 'domains',
            'record_id' => null,
            'details' => 'تم عرض الدومينات المنتهية صلاحيتها لدى المستخدمين.',
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json([
            'domains' => $domains,
            'message' => 'تم جلب الدومينات المنتهية صلاحيتها لدى المستخدمين بنجاح.',
        ]);
 }

    public function domainsStore(Request $request)
    {
        $result = DomainController::store($request);
        return $result;
    }

    /**
     * عرض صفحة تعديل دومين
     */
    public function domainsEdit(Request $request, $id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
       Validator::make($request->all(), [
            'name' => [
                'nullable',
                'string',
                'min:2',
                'max:63',
                'regex:/^[a-z0-9][a-z0-9-]*[a-z0-9]$/i'
            ],
            'tld' => 'nullable|string|in:.com,.net,.org,.sa,.ae,.edu,.gov,.info,.biz',
            'price_monthly' => 'nullable|numeric|min:0',
            'price_yearly' => 'nullable|numeric|min:0',
            'expires_at' => 'nullable|date',
            'available' => 'nullable|boolean',
        ])->validate();
        $domain = Domain::with('product')->findOrFail($id);
        if (!$domain) {
            return response()->json(['error' => 'الدومين غير موجود'], 404);
        }



        $domain->update([
            'name' => $request->name ?? $domain->name,
            'tld' => $request->tld ?? $domain->tld,
            'price_monthly' => $request->price_monthly ?? $domain->price_monthly,
            'price_yearly' => $request->price_yearly ?? $domain->price_yearly,
            'expires_at' => $request->expires_at ?? $domain->expires_at,
            'available' => $request->available ?? $domain->available,
        ]);
        $domain->save();

        DB::table('admin_logs')->insert([
            'admin_id' => $user->id,
            'action' => 'update',
            'table_name' => 'domains',
            'record_id' => $domain->id,
            'details' => 'تم تحديث الدومين: ' . $domain->name . $domain->tld,
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $domain->product->update([
            'name' => $domain->name . '.' . $domain->tld,
            'price_monthly' => $domain->price_monthly,
            'price_yearly' => $domain->price_yearly,
        ]);
        DB::table('admin_logs')->insert([
            'admin_id' => $user->id,
            'action' => 'update',
            'table_name' => 'products',
            'record_id' => $domain->product->id,
            'details' => 'تم تحديث منتج الدومين: ' . $domain->product->name,
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json([
            'message' => 'تم تحديث الدومين بنجاح.',
            'domain' => $domain,
            'product' => $domain->product,
        ]);

    }



    /**
     * حذف دومين
     */
    public function domainsDestroy($id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        DB::beginTransaction();

        try {
            $domain = Domain::with('product')->findOrFail($id);

           if (!$domain) {
                return response()->json(['error' => 'الدومين غير موجود'], 404);
            }
            if ($domain->product) {
                $domain->product->delete();
            }
            if($domain->owner()->exists()){
                return response()->json(['error' => 'لا يمكن حذف الدومين لأنه مرتبط بمستخدم.'], 400);
            }
            $domain->delete();
          DB::table('admin_logs')->insert([
                'admin_id' => $user->id,
                'action' => 'delete',
                'table_name' => 'domains',
                'record_id' => $domain->id,
                'details' => 'تم حذف الدومين: ' . $domain->name . $domain->tld,
                'ip_address' => request()->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::commit();

          return response()->json([
                'message' => 'تم حذف الدومين بنجاح.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'حدث خطأ أثناء حذف الدومين: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * تغيير حالة توفر الدومين
     */
    public function setInactiveDomain($id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $domain = Domain::findOrFail($id);
        $domain->update([
            'isActive' => false,
        ]);

        return response()->json([
            'message' => 'تم تغيير حالة الدومين بنجاح.',
            'domain' => $domain,
        ]);
    }
    public function setActiveDomain($id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $domain = Domain::findOrFail($id);
        $domain->update([
            'isActive' => true,
        ]);

        return response()->json([
            'message' => 'تم تغيير حالة الدومين بنجاح.',
            'domain' => $domain,
        ]);
    }
  public function releaseDomain($id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $domain = Domain::findOrFail($id);
        $domain->update([
            'user_id' => null,
            'active_in_user' => false,
            'expires_at_in_user' => null,
            'status' => 'available',
            'available' => true,
            'added_by' => null,
        ]);
     DB::table('admin_logs')->insert([
            'admin_id' => $user->id,
            'action' => 'delete',
            'table_name' => 'domains',
            'record_id' => $domain->id,
            'details' => 'تم تحرير الدومين من صاحبه: ' . $domain->name . $domain->tld,
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json([
            'message' => 'تم تحرير الدومين من صاحبه بنجاح.',
            'domain' => $domain,
        ]);
    }

   public function domainsAvailable()
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $domains = Domain::where('available', true)
        ->where('isActive', true)
            ->with('product')
            ->latest()
            ->paginate(20);
      DB::table('admin_logs')->insert([
            'admin_id' => $user->id,
            'action' => 'view',
            'table_name' => 'domains',
            'record_id' => null,
            'details' => 'تم عرض الدومينات المتاحة بنجاح.',
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json([
            'domains' => $domains,
        ]);
    }

    //زيادة على عدد أيام تاريخ انتهاء الدومين
    public function extendDomainExpiry(Request $request, $id)
    {
        $request->validate([
            'days' => 'required|integer|min:1',
        ]);

        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }

        $domain = Domain::findOrFail($id);
        if (!$domain) {
            return response()->json(['error' => 'الدومين غير موجود'], 404);
        }
        $domain->expires_at = $domain->expires_at->addDays($request->days);
        $domain->save();

        DB::table('admin_logs')->insert([
            'admin_id' => $user->id,
            'action' => 'update',
            'table_name' => 'domains',
            'record_id' => $domain->id,
            'details' => 'تم تمديد صلاحية الدومين: ' . $domain->name . $domain->tld . ' لعدد أيام: ' . $request->days,
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'تم تمديد صلاحية الدومين بنجاح.',
            'domain' => $domain,
        ]);
    }
    //اعطاء مستخدم دومين
    public function assignDomainToUser(Request $request, $domainId, $userId)
    {
      Validator::make($request->all(), [
            'active_in_user' => 'required|boolean',
            'expires_at_in_user' => 'nullable|date',

        ])->validate();
        $admin = auth('sanctum')->user();
        if (!$admin) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }

        $domain = Domain::findOrFail($domainId);
        $user = User::findOrFail($userId);

        if (!$domain || !$user) {
            return response()->json(['error' => 'الدومين أو المستخدم غير موجود'], 404);
        }
        if($user->status==='blocked'){
            return response()->json(['error' => 'لا يمكن إعطاء الدومين لمستخدم محظور.'], 400);
        }

        $domain->update([
            'user_id' => $user->id,
            'active_in_user' => true,
            'expires_at_in_user' => $request->expires_at_in_user??now()->addMonth(),
            'status' => 'sold_out',
            'available' => false,
            'added_by' => $admin->id,

        ]);

        DB::table('admin_logs')->insert([
            'admin_id' => $admin->id,
            'action' => 'create',
            'table_name' => 'domains',
            'record_id' => $domain->id,
            'details' => 'تم إعطاء الدومين: ' . $domain->name . $domain->tld . ' للمستخدم: ' . $user->email,
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'تم إعطاء الدومين للمستخدم بنجاح.',
            'domain' => $domain,
        ]);
    }
    // ==================== إدارة السيرفرات ====================

    /**
     * عرض جميع السيرفرات
     */
    public function serversIndex()
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $servers = Server::with('product')
            ->latest()
            ->paginate(20);
        DB::table('admin_logs')->insert([
            'admin_id' => $user->id,
            'action' => 'view',
            'table_name' => 'servers',
            'record_id' => null,
            'details' => 'تم عرض جميع السيرفرات بنجاح.',
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json([
            'servers' => $servers,
            'message' => 'تم جلب السيرفرات بنجاح.',

        ]);
    }

//عرض السيرفرات المتاحة
    public function serversAvailable()
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $servers = Server::where('status', 'available')
            ->where('isActive', true)
            ->with('product')
            ->latest()
            ->paginate(20);
            if (!$servers) {
            return response()->json(['error' => 'لا توجد سيرفرات متاحة'], 404);
        }
        DB::table('admin_logs')->insert([
            'admin_id' => $user->id,
            'action' => 'view',
            'table_name' => 'servers',
            'record_id' => null,
            'details' => 'تم عرض السيرفرات المتاحة بنجاح.',
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json([
            'servers' => $servers,
            'message' => 'تم جلب السيرفرات المتاحة بنجاح.',
        ]);
    }
//عرض السيرفرات الغير متاحة
    public function serversUnavailable()
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $servers = Server::where('status', '!=', 'available')
            ->orWhere('isActive', true)
            ->with('product')
            ->latest()
            ->paginate(20);
            if (!$servers) {
            return response()->json(['error' => 'لا توجد سيرفرات غير متاحة'], 404);
        }
        DB::table('admin_logs')->insert([
            'admin_id' => $user->id,
            'action' => 'view',
            'table_name' => 'servers',
            'record_id' => null,
            'details' => 'تم عرض السيرفرات الغير متاحة بنجاح.',
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json([
            'servers' => $servers,
            'message' => 'تم جلب السيرفرات الغير متاحة بنجاح.',
        ]);
    }

    // عرض السيرفرات المنتهية صلاحيتها لدى المستخدمين
    public function serversExpiredInUser()
   {

    $user = auth('sanctum')->user();
    if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $servers = Server::where('active_in_user', false)
           ->with('owner')
            ->with('product')
            ->latest()
            ->paginate(20)
            ->get();
    if (!$servers) {
            return response()->json(['error' => 'لا توجد سيرفرات منتهية صلاحيتها لدى المستخدمين'], 404);
        }
              DB::table('admin_logs')->insert([
            'admin_id' => $user->id,
            'action' => 'view',
            'table_name' => 'servers',
            'record_id' => null,
            'details' => 'تم عرض السيرفرات المنتهية صلاحيتها لدى المستخدمين.',
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json([
            'servers' => $servers,
            'message' => 'تم جلب السيرفرات المنتهية صلاحيتها لدى المستخدمين بنجاح.',
        ]);
 }
 // عرض سيرفرات الصيانة
    public function serversInMaintenance()
   {

    $user = auth('sanctum')->user();
    if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $servers = Server::where('status', 'maintenance')
           ->with('owner')
            ->with('product')
            ->latest()
            ->paginate(20)
            ->get();
    if (!$servers) {
            return response()->json(['error' => 'لا توجد سيرفرات في الصيانة'], 404);
        }
              DB::table('admin_logs')->insert([
            'admin_id' => $user->id,
            'action' => 'view',
            'table_name' => 'servers',
            'record_id' => null,
            'details' => 'تم عرض السيرفرات في الصيانة.',
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json([
            'servers' => $servers,
            'message' => 'تم جلب السيرفرات في الصيانة بنجاح.',
        ]);
 }

 //عرض السيرفرات المنتهية الصلاحية من الشركة الام
     public function serversInactive()
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $servers = Server::where('isActive', false)
            ->with('product')
            ->latest()
            ->paginate(20)
            ->get();
          if (!$servers) {
            return response()->json(['error' => 'لا توجد سيرفرات منتهية الصلاحية من الشركة الأم'], 404);
        }
                DB::table('admin_logs')->insert([
                    'admin_id' => $user->id,
                    'action' => 'view',
                    'table_name' => 'servers',
                    'record_id' => null,
                    'details' => 'تم عرض السيرفرات المنتهية صلاحيتها من الشركة الأم بنجاح.',
                    'ip_address' => request()->ip(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        return response()->json([
            'servers' => $servers,
            'message' => 'تم جلب السيرفرات المنتهية صلاحيتها من الشركة الأم بنجاح.',
        ]);
    }

    // وضع سيرفر في الصيانة
 public function setServerMaintenance($id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $server = Server::findOrFail($id);
        if (!$server) {
            return response()->json(['error' => 'السيرفر غير موجود'], 404);
        }
        $server->update([
            'isActive' => false,
            'status' => 'maintenance',
        ]);
        if($server->owner()->exists()){
            $now = now();
            $expiresAt = $server->expires_at_in_user;
            if ($expiresAt && $expiresAt->greaterThan($now)) {
                $remainingDays = $now->diffInDays($expiresAt);

            }
        }

         DB::table('admin_logs')->insert([
            'admin_id' => $user->id,
            'action' => 'update',
            'table_name' => 'servers',
            'record_id' => $server->id,
            'details' => 'تم وضع السيرفر في الصيانة: ' . $server->name,
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'تم تغيير حالة السيرفر بنجاح.',
            'server' => $server,
            'remaining_days' => $remainingDays. 'of'. $user->email ?? null,
        ]);
    }

    //زيادة ايام على تاريخ الانتهاء
    public function extendServerExpiration(Request $request, $id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $server = Server::findOrFail($id);
        if (!$server) {
            return response()->json(['error' => 'السيرفر غير موجود'], 404);
        }
        Validator::make($request->all(), [
            'additional_days' => 'required|integer|min:1',
        ])->validate();

        $newExpirationDate = $server->expired_at->addDays($request->additional_days);
        $server->update([
            'expired_at' => $newExpirationDate,
        ]);
         DB::table('admin_logs')->insert([
            'admin_id' => $user->id,
            'action' => 'update',
            'table_name' => 'servers',
            'record_id' => $server->id,
            'details' => 'تم تمديد صلاحية السيرفر: ' . $server->name . ' لعدد أيام: ' . $request->additional_days,
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'تم تمديد صلاحية السيرفر بنجاح.',
            'server' => $server,
        ]);
    }
// اضافة سيرفر جديد
   public function serversStore(Request $request)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'cpu_speed' => 'required|string|max:50',
            'cpu_cores' => 'required|integer|min:1',
            'ram' => 'required|string|max:50',
            'category' => 'required|in:VPS,Dedicated,Cloud',
            'description' => 'nullable|string',
            'storage_type' => 'required|in:SSD,HDD,NVMe',
            'storage' => 'required|integer|min:1',
            'bandwidth' => 'nullable|string|max:100',
            'datacenter_location' => 'required|string|max:255',
            'os' => 'required|string|max:255',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'status' => 'required|in:available,sold_out,maintenance',
            'expired_at' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            // إنشاء السيرفر
            $server = Server::create([
                'added_by' => $user->id,
                'expired_at' => $request->expired_at??now()->addyear(),
                'name' => $request->name,
                'cpu_speed' => $request->cpu_speed,
                'cpu_cores' => $request->cpu_cores,
                'ram' => $request->ram,
                'category' => $request->category,
                'description' => $request->description,
                'storage_type' => $request->storage_type,
                'storage' => $request->storage,
                'bandwidth' => $request->bandwidth,
                'datacenter_location' => $request->datacenter_location,
                'os' => $request->os,
                'price_monthly' => $request->price_monthly,
                'price_yearly' => $request->price_yearly,
                'status' => $request->status,
            ]);
           DB::table('admin_logs')->insert([
                'admin_id' => $user->id,
                'action' => 'create',
                'table_name' => 'servers',
                'record_id' => $server->id,
                'details' => 'تم إضافة السيرفر: ' . $server->name,
                'ip_address' => request()->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            // إنشاء المنتج المرتبط
            $product = Product::create([
                'name' => $server->name,
                'type' => 'server',
                'price_monthly' => $server->price_monthly,
                'price_yearly' => $server->price_yearly,
                'description' => $server->description,
                'productable_id' => $server->id,
                'productable_type' => Server::class,
            ]);
              DB::table('admin_logs')->insert([
                'admin_id' => $user->id,
                'action' => 'create',
                'table_name' => 'products',
                'record_id' => $product->id,
                'details' => 'تم إنشاء منتج للسيرفر: ' . $product->name,
                'ip_address' => request()->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::commit();
         return response()->json([
            'message' => 'تم إضافة السيرفر بنجاح.',
            'server' => $server,
            'product' => $product,
        ]);

        } catch (\Exception $e) {
            DB::rollBack();
         return response()->json([
                'error' => 'حدث خطأ أثناء إضافة السيرفر: ' . $e->getMessage(),
            ], 500);
        }
    }

// اريد نفس الدوال الاضافية ع السيرفر فوق

    /**
     * تحديث بيانات السيرفر
     */
    public function serversUpdate(Request $request, $id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $server = Server::with('product')->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'expired_at' => 'required|date',
            'name' => 'required|string|max:255',
            'cpu_speed' => 'required|string|max:50',
            'cpu_cores' => 'required|integer|min:1',
            'ram' => 'required|string|max:50',
            'category' => 'required|in:VPS,Dedicated,Cloud',
            'description' => 'nullable|string',
            'storage_type' => 'required|in:SSD,HDD,NVMe',
            'storage' => 'required|integer|min:1',
            'bandwidth' => 'nullable|string|max:100',
            'datacenter_location' => 'required|string|max:255',
            'os' => 'required|string|max:255',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'status' => 'required|in:available,sold_out,maintenance',
        ]);

        if ($validator->fails()) {
          return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            // تحديث السيرفر
            $server->update([
                'expired_at' => $request->expired_at??now()->addyear(),
                'name' => $request->name,
                'cpu_speed' => $request->cpu_speed,
                'cpu_cores' => $request->cpu_cores,
                'ram' => $request->ram,
                'category' => $request->category,
                'description' => $request->description,
                'storage_type' => $request->storage_type,
                'storage' => $request->storage,
                'bandwidth' => $request->bandwidth,
                'datacenter_location' => $request->datacenter_location,
                'os' => $request->os,
                'price_monthly' => $request->price_monthly,
                'price_yearly' => $request->price_yearly,
                'status' => $request->status,
            ]);
            DB::table('admin_logs')->insert([
                'admin_id' => $user->id,
                'action' => 'update',
                'table_name' => 'servers',
                'record_id' => $server->id,
                'details' => 'تم تحديث السيرفر: ' . $server->name,
                'ip_address' => request()->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            // تحديث المنتج المرتبط
            if ($server->product) {
                $server->product->update([
                    'name' => $server->name,
                    'price_monthly' => $server->price_monthly,
                    'price_yearly' => $server->price_yearly,
                    'description' => $server->description,
                ]);
                DB::table('admin_logs')->insert([
                    'admin_id' => $user->id,
                    'action' => 'update',
                    'table_name' => 'products',
                    'record_id' => $server->product->id,
                    'details' => 'تم تحديث منتج السيرفر: ' . $server->product->name,
                    'ip_address' => request()->ip(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

           return response()->json([
            'message' => 'تم تحديث السيرفر بنجاح.',
            'server' => $server,
            'product' => $server->product,
        ]);
        } catch (\Exception $e) {
            DB::rollBack();
         return response()->json([
                'error' => 'حدث خطأ أثناء تحديث السيرفر: ' . $e->getMessage(),
            ], 500);
        }
    }

    //تحرير سيرفر من مستخدم
    public function releaseServer($id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $server = Server::findOrFail($id);
        if (!$server) {
            return response()->json(['error' => 'السيرفر غير موجود'], 404);
        }
        $server->update([
            'user_id' => null,
            'expired_at_in_user' => null,
            'active_in_user' => false,
            'status' => 'available',
            'added_by' => null,
            'available' => true,

        ]);
       DB::table('admin_logs')->insert([
            'admin_id' => $user->id,
            'action' => 'delete',
            'table_name' => 'servers',
            'record_id' => $server->id,
            'details' => 'تم تحرير السيرفر من صاحبه: ' . $server->name,
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json([
            'message' => 'تم تحرير السيرفر من صاحبه بنجاح.',
            'server' => $server,
        ]);
    }
    /**
     * حذف سيرفر
     */
    public function serversDestroy($id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        DB::beginTransaction();

        try {
            $server = Server::with('product')->findOrFail($id);
           if (!$server) {
                return response()->json(['error' => 'السيرفر غير موجود'], 404);
            }
            // حذف المنتج المرتبط أولاً
            if ($server->product) {
                $server->product->delete();
            }

            if($server->owner()->exists()){
                return response()->json(['error' => 'لا يمكن حذف السيرفر لأنه مرتبط بمستخدم.'], 400);
            }
            // حذف السيرفر
            $server->delete();
           DB::table('admin_logs')->insert([
                'admin_id' => $user->id,
                'action' => 'delete',
                'table_name' => 'servers',
                'record_id' => $server->id,
                'details' => 'تم حذف السيرفر: ' . $server->name,
                'ip_address' => request()->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::commit();

            return response()->json([
                'message' => 'تم حذف السيرفر بنجاح.',
            ]);

        } catch (\Exception $e) {
          return response()->json([
                'error' => 'حدث خطأ أثناء حذف السيرفر: ' . $e->getMessage(),
            ], 500);
        }
    }
//اعطاء سيرفر لمستخدم
    public function assignServerToUser(Request $request, $serverId, $userId)
    {

        $admin = auth('sanctum')->user();
        if (!$admin) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'expired_at_in_user' => 'required|date',
            'active_in_user' => 'required|boolean',
        ]);

       if ($validator->fails()) {
           return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $server = Server::findOrFail($serverId);
        $user = User::findOrFail($userId);

        if (!$server || !$user) {
            return response()->json(['error' => 'السيرفر أو المستخدم غير موجود'], 404);
        }

        $server->update([
            'user_id' => $userId,
            'expired_at_in_user' => $request->expired_at_in_user,
            'active_in_user' => $request->active_in_user,
            'status' => 'sold_out',
            'available' => false,
            'added_by' => $admin->id,
        ]);

        DB::table('admin_logs')->insert([
            'admin_id' => $admin->id,
            'action' => 'assign',
            'table_name' => 'servers',
            'record_id' => $server->id,
            'details' => "تم إعطاء السيرفر {$server->name} للمستخدم {$user->name}",
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => "تم إعطاء السيرفر {$server->name} للمستخدم {$user->name} بنجاح.",
            'server' => $server,
        ]);
    }
    // ==================== إدارة المنتجات ====================


    public function productsIndex()
    {
       
        $products = Product::with('productable')
            ->latest()
            ->paginate(20);

     return response()->json([
            'products' => $products,
            'message' => 'تم جلب المنتجات بنجاح.',
        ]);
    }


    /**
     * حفظ منتج جديد
     */
    public function productsStore(Request $request)
    {

        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:domain,server',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'productable_id' => 'required|integer',
            'productable_type' => 'required|in:App\Models\Domain,App\Models\Server',
        ]);

        if ($validator->fails()) {
           return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // التحقق من وجود المنتج المربوط
            $productableClass = $request->productable_type;
            $productable = $productableClass::find($request->productable_id);

            if (!$productable) {
                return response()->json([
                      'error' => 'المنتج المربوط غير موجود.',
                 ], 404);
            }

            $exists = Product::where('productable_id', $request->productable_id)
                ->where('productable_type', $request->productable_type)
                ->exists();

            if ($exists) {
                return response()->json([
                    'error' => 'هذا المنتج موجود بالفعل.',
                ], 409);
            }

            Product::create([
                'name' => $request->name,
                'type' => $request->type,
                'price_monthly' => $request->price_monthly,
                'price_yearly' => $request->price_yearly,
                'description' => $request->description,
                'productable_id' => $request->productable_id,
                'productable_type' => $request->productable_type,
            ]);
              DB::table('admin_logs')->insert([
                'admin_id' => $user->id,
                'action' => 'create',
                'table_name' => 'products',
                'record_id' => null,
                'details' => 'تم إضافة منتج جديد: ' . $request->name,
                'ip_address' => request()->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return response()->json([
                'message' => 'تم إضافة المنتج بنجاح.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'حدث خطأ أثناء إضافة المنتج: ' . $e->getMessage(),
            ], 500);
        }
    }




    /**
     * تحديث بيانات المنتج
     */
    public function productsUpdate(Request $request, $id)
    {

        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $product = Product::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:domain,server',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'productable_id' => 'required|integer',
            'productable_type' => 'required|in:App\Models\Domain,App\Models\Server',
        ]);

        if ($validator->fails()) {
          return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // التحقق من وجود المنتج المربوط
            $productableClass = $request->productable_type;
            $productable = $productableClass::find($request->productable_id);

            if (!$productable) {
                return response()->json([
                      'error' => 'المنتج المربوط غير موجود.',
                 ], 404);
            }

            $exists = Product::where('productable_id', $request->productable_id)
                ->where('productable_type', $request->productable_type)
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
               return response()->json([
                    'error' => 'هذا المنتج موجود بالفعل.',
                ], 409);
            }
           if($product->owner()->exists()){
                return response()->json(['error' => 'لا يمكن تحديث المنتج لأنه مرتبط بمستخدم.'], 400);
            }
            $product->update([
                'name' => $request->name,
                'type' => $request->type,
                'price_monthly' => $request->price_monthly,
                'price_yearly' => $request->price_yearly,
                'description' => $request->description,
                'productable_id' => $request->productable_id,
                'productable_type' => $request->productable_type,
            ]);
           DB::table('admin_logs')->insert([
                'admin_id' => $user->id,
                'action' => 'update',
                'table_name' => 'products',
                'record_id' => $product->id,
                'details' => 'تم تحديث المنتج: ' . $product->name,
                'ip_address' => request()->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
           return response()->json([
                'message' => 'تم تحديث المنتج بنجاح.',
                'product' => $product,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'حدث خطأ أثناء تحديث المنتج: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * حذف منتج
     */
    public function productsDestroy($id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
         if(Product::findOrFail($id)->owner()->exists()){
                return response()->json(['error' => 'لا يمكن حذف المنتج لأنه مرتبط بمستخدم.'], 400);
            }
         DB::table('admin_logs')->insert([
                'admin_id' => $user->id,
                'action' => 'delete',
                'table_name' => 'products',
                'record_id' => $id,
                'details' => 'تم حذف المنتج ذو المعرف: ' . $id,
                'ip_address' => request()->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        try {
            $product = Product::findOrFail($id);
            $product->delete();
            return response()->json([
                'message' => 'تم حذف المنتج بنجاح.',
            ]);

        } catch (\Exception $e) {
            return back()->with('error', 'حدث خطأ أثناء حذف المنتج: ' . $e->getMessage());
        }
    }

    // ==================== إدارة الطلبات ====================

    /**
     * عرض جميع الطلبات
     */
    public function ordersIndex()
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        //قد يكون للمنتج سلة وقد لا يكون
        $orders = Order::with(['user', 'cart.items.product.productable'])
            ->latest()
            ->paginate(20);
        DB::table('admin_logs')->insert([
            'admin_id' => $user->id,
            'action' => 'view',
            'table_name' => 'orders',
            'record_id' => null,
            'details' => 'تم عرض جميع الطلبات بنجاح.',
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
      return response()->json([
         'message' => 'تم جلب الطلبات بنجاح.',
            'orders' => $orders,
        ]);
    }

    /**
     * عرض تفاصيل طلب
     */
    public function ordersShow($id)
    {$user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $order = Order::with(['user', 'cart.items.product.productable'])
            ->findOrFail($id);
        if (!$order) {
            return response()->json(['error' => 'الطلب غير موجود'], 404);
        }
        DB::table('admin_logs')->insert([
            'admin_id' => $user->id,
            'action' => 'view',
            'table_name' => 'orders',
            'record_id' => $order->id,
            'details' => 'تم عرض تفاصيل الطلب ذو المعرف: ' . $order->id,
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json([
            'order' => $order,
        ]);
    }


    public function ordersUpdateStatus(Request $request, $id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $order = Order::findOrFail($id);
      if (!$order) {
            return response()->json(['error' => 'الطلب غير موجود'], 404);
        }
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,paid,processing,completed,cancelled',
        ]);
       if ($request->status == 'paid' && $order->status != 'paid') {
            // تحقق من أن المبلغ المدفوع يساوي أو يزيد عن إجمالي الطلب
            if ($order->total <= 0) {
                return response()->json([
                    'error' => 'لا يمكن تعيين الحالة إلى مدفوع لأن إجمالي الطلب غير صالح.',
                ], 400);
            }
        }


        if ($validator->fails()) {
              return response()->json([
                 'errors' => $validator->errors(),
                ], 422);
        }

        $order->update([
            'status' => $request->status,
        ]);
       DB::table('admin_logs')->insert([
            'admin_id' => $user->id,
            'action' => 'update',
            'table_name' => 'orders',
            'record_id' => $order->id,
            'details' => 'تم تحديث حالة الطلب ذو المعرف: ' . $order->id . ' إلى الحالة: ' . $request->status,
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'تم تحديث حالة الطلب بنجاح.',
        ]);
    }
 // حذف طلب
    public function ordersDestroy($id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        try {
            $order = Order::findOrFail($id);
            if (!$order) {
                return response()->json(['error' => 'الطلب غير موجود'], 404);
            }
            if($order->cart()->exists()){
                return response()->json(['error' => 'لا يمكن حذف الطلب لأنه مرتبط بسلة مشتريات.'], 400);
            }


            $order->delete();
           DB::table('admin_logs')->insert([
                'admin_id' => $user->id,
                'action' => 'delete',
                'table_name' => 'orders',
                'record_id' => $order->id,
                'details' => 'تم حذف الطلب ذو المعرف: ' . $order->id,
                'ip_address' => request()->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return response()->json([
                'message' => 'تم حذف الطلب بنجاح.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'حدث خطأ أثناء حذف الطلب: ' . $e->getMessage(),
            ], 500);
        }
    }
    // ==================== إدارة المستخدمين ====================

    /**
     * عرض جميع المستخدمين
     */
    public function usersIndex()
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $users = User::latest()
            ->paginate(20);
     DB::table('admin_logs')->insert([
            'admin_id' => $user->id,
            'action' => 'view',
            'table_name' => 'users',
            'record_id' => null,
            'details' => 'تم عرض جميع المستخدمين بنجاح.',
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
       return response()->json([
            'users' => $users,
            'message' => 'تم جلب المستخدمين بنجاح.',
        ]);
    }

    /**
     * عرض تفاصيل مستخدم
     */
    public function usersShow($id)
    {
        $admin = auth('sanctum')->user();
        if (!$admin) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }

        $user = User::with(['orders.cart.items.product.productable', 'domains', 'servers'])
            ->findOrFail($id);
        if (!$user) {
            return response()->json(['error' => 'المستخدم غير موجود'], 404);
        }
        DB::table('admin_logs')->insert([
            'admin_id' => $admin->id,
            'action' => 'view',
            'table_name' => 'users',
            'record_id' => $user->id,
            'details' => 'تم عرض تفاصيل المستخدم ذو المعرف: ' . $user->id,
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
       return response()->json([
            'user' => $user,
            'message' => 'تم جلب تفاصيل المستخدم بنجاح.',
        ]);
        }




    /**
     * حظر مستخدم
     */
    public function BanUser($id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $use = User::findOrFail($id);
        if (!$use) {
            return response()->json(['error' => 'المستخدم غير موجود'], 404);
        }
        $use->update([
         'status' =>'blocked',
        ]);

        DB::table('admin_logs')->insert([
            'admin_id' => $user->id,
            'action' => 'update',
            'table_name' => 'users',
            'record_id' => $use->id,
            'details' => 'تم حظر المستخدم ذو المعرف: ' . $use->id,
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);


       return response()->json([
            'message' => "تم حظر المستخدم بنجاح.",
            'banned_at' => now(),
        ]);
    }
   public function UnbanUser($id)
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $use = User::findOrFail($id);
        if (!$use) {
            return response()->json(['error' => 'المستخدم غير موجود'], 404);
        }
        $use->update([
         'status' =>'active',
        ]);

        DB::table('admin_logs')->insert([
            'admin_id' => $user->id,
            'action' => 'update',
            'table_name' => 'users',
            'record_id' => $use->id,
            'details' => 'تم إلغاء حظر المستخدم ذو المعرف: ' . $use->id,
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

      return response()->json([
            'message' => "تم إلغاء حظر المستخدم بنجاح.",
            'user' => $use,
        ]);

    }

    // ==================== الإحصائيات والتقارير ====================

    /**
     * عرض الإحصائيات
     */
    public function statistics()
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'error' => 'المستخدم غير مصرح له.',
            ], 401);
        }
        $sales_stats = [
            'today' => Order::whereDate('created_at', today())
                ->where('status', 'paid')
                ->sum('total'),
            'week' => Order::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->where('status', 'paid')
                ->sum('total'),
            'month' => Order::whereMonth('created_at', date('m'))
                ->where('status', 'paid')
                ->sum('total'),
            'year' => Order::whereYear('created_at', date('Y'))
                ->where('status', 'paid')
                ->sum('total'),
        ];

        $order_status = Order::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();

        $product_types = Product::select('type', DB::raw('COUNT(*) as count'))
            ->groupBy('type')
            ->get();

        $best_selling = DB::table('cart_items')
            ->join('products', 'cart_items.product_id', '=', 'products.id')
            ->select('products.name', DB::raw('SUM(cart_items.quantity) as total_sold'))
            ->groupBy('cart_items.product_id', 'products.name')
            ->orderByDesc('total_sold')
            ->limit(10)
            ->get();

           DB::table('admin_logs')->insert([
            'admin_id' => auth('sanctum')->user()->id,
            'action' => 'view',
            'table_name' => 'statistics',
            'record_id' => null,
            'details' => 'تم عرض الإحصائيات بنجاح.',
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json([
            'sales_stats' => $sales_stats,
            'order_status' => $order_status,
            'product_types' => $product_types,
            'best_selling' => $best_selling,
        ]);
    }}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DomainController extends Controller
{
    public function __construct()
    {
    }


    public function index(Request $request)
    {
        try {
            $query = Domain::with('product')
                ->latest();

            // البحث
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('tld', 'like', "%{$search}%")
                      ->orWhereRaw("CONCAT(name, '.', tld) LIKE ?", ["%{$search}%"]);
                });
            }

            // التصفية حسب الحالة
            if ($request->has('status')) {
                if ($request->status === 'available') {
                    $query->where('available', true);
                } elseif ($request->status === 'registered') {
                    $query->whereNotNull('registered_at');
                } elseif ($request->status === 'expiring') {
                    $query->whereNotNull('expires_at')
                          ->where('expires_at', '<=', now()->addDays(30))
                          ->where('expires_at', '>', now());
                } elseif ($request->status === 'expired') {
                    $query->whereNotNull('expires_at')
                          ->where('expires_at', '<', now());
                }
            }

            // التصفية حسب TLD
            if ($request->has('tld')) {
                $query->where('tld', $request->tld);
            }

            // التصفية حسب التوفر
            if ($request->has('available')) {
                $query->where('available', $request->boolean('available'));
            }

            // الباجينيت
            $perPage = $request->per_page ?? 20;
            $domains = $query->paginate($perPage);

            // الحصول على إحصائيات
            $stats = Domain::selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN available = 1 THEN 1 ELSE 0 END) as available_count,
                SUM(CASE WHEN registered_at IS NOT NULL THEN 1 ELSE 0 END) as registered_count,
                SUM(CASE WHEN expires_at IS NOT NULL AND expires_at < NOW() THEN 1 ELSE 0 END) as expired_count
            ')->first();

            // جميع TLDs المميزة
            $tlds = Domain::select('tld')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('tld')
                ->orderByDesc('count')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $domains,
                'stats' => $stats,
                'tlds' => $tlds,
                'filters' => $request->only(['search', 'status', 'tld', 'available'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب النطاقات',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function show($id)
    {
        try {
            $domain = Domain::with([
                'product',
                'orderItems.order.user'
            ])->findOrFail($id);

            // الحصول على تفاصيل الطلبات المرتبطة
            $orders = $domain->orderItems()
                ->with('order.user')
                ->latest()
                ->limit(10)
                ->get();

            // الإحصائيات
            $stats = [
                'total_orders' => $domain->orderItems()->count(),
                'active_orders' => $domain->orderItems()->whereHas('order', function($q) {
                    $q->whereIn('status', ['paid', 'completed']);
                })->count(),
                'total_revenue' => $domain->orderItems()->sum('total')
            ];

            return response()->json([
                'success' => true,
                'data' => $domain,
                'orders' => $orders,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على النطاق'
            ], 404);
        }
    }

    /**
     * إنشاء نطاق جديد
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:63',
            'tld' => 'required|string|max:10',
            'price_yearly' => 'required|numeric|min:0',
            'price_monthly' => 'nullable|numeric|min:0',
            'available' => 'boolean',
            'auto_renew' => 'boolean',
            'whois_protected' => 'boolean',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // التحقق من عدم وجود النطاق مسبقاً
            $exists = Domain::where('name', $request->name)
                          ->where('tld', $request->tld)
                          ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'النطاق موجود مسبقاً'
                ], 409);
            }

            // إنشاء النطاق
            $domain = Domain::create([
                'name' => strtolower($request->name),
                'tld' => strtolower($request->tld),
                'available' => $request->available ?? true,
                'auto_renew' => $request->auto_renew ?? false,
                'whois_protected' => $request->whois_protected ?? false,
                'status' => 'available',
                'notes' => $request->notes
            ]);

            // إنشاء المنتج المرتبط
            $domain->createProduct([
                'name' => $domain->full_domain,
                'type' => 'domain',
                'price_monthly' => $request->price_monthly ?? ($request->price_yearly / 12),
                'price_yearly' => $request->price_yearly,
                'description' => $request->description ?? 'نطاق ' . strtoupper($request->tld),
                'status' => 'active'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء النطاق بنجاح',
                'data' => $domain->load('product')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في إنشاء النطاق',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تحديث النطاق
     */
    public function update(Request $request, $id)
    {
        $domain = Domain::with('product')->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:63',
            'tld' => 'sometimes|string|max:10',
            'price_yearly' => 'nullable|numeric|min:0',
            'price_monthly' => 'nullable|numeric|min:0',
            'available' => 'boolean',
            'auto_renew' => 'boolean',
            'whois_protected' => 'boolean',
            'registered_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'status' => 'in:available,registered,expired',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // تحديث بيانات النطاق
            $domain->update([
                'name' => $request->filled('name') ? strtolower($request->name) : $domain->name,
                'tld' => $request->filled('tld') ? strtolower($request->tld) : $domain->tld,
                'available' => $request->has('available') ? $request->available : $domain->available,
                'auto_renew' => $request->has('auto_renew') ? $request->auto_renew : $domain->auto_renew,
                'whois_protected' => $request->has('whois_protected') ? $request->whois_protected : $domain->whois_protected,
                'registered_at' => $request->filled('registered_at') ? $request->registered_at : $domain->registered_at,
                'expires_at' => $request->filled('expires_at') ? $request->expires_at : $domain->expires_at,
                'status' => $request->filled('status') ? $request->status : $domain->status,
                'notes' => $request->filled('notes') ? $request->notes : $domain->notes
            ]);

            // تحديث المنتج المرتبط إذا كان موجوداً
            if ($domain->product) {
                $domain->product->update([
                    'name' => $domain->full_domain,
                    'price_monthly' => $request->price_monthly ?? $domain->product->price_monthly,
                    'price_yearly' => $request->price_yearly ?? $domain->product->price_yearly,
                    'description' => $request->filled('description') ? $request->description : $domain->product->description,
                    'status' => $domain->available ? 'active' : 'inactive'
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث النطاق بنجاح',
                'data' => $domain->load('product')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تحديث النطاق',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * حذف النطاق
     */
    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $domain = Domain::with('product')->findOrFail($id);

            // التحقق من عدم وجود طلبات مرتبطة
            $hasOrders = $domain->orderItems()->exists();
            if ($hasOrders) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن حذف النطاق لأنه مرتبط بطلبات'
                ], 409);
            }

            // حذف المنتج المرتبط أولاً
            if ($domain->product) {
                $domain->product->delete();
            }

            // حذف النطاق
            $domain->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف النطاق بنجاح'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في حذف النطاق',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تسجيل النطاق يدوياً
     */
    public function registerDomain(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'years' => 'required|integer|min:1|max:10',
            'user_id' => 'required|exists:users,id',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $domain = Domain::findOrFail($id);

            if ($domain->registered_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'النطاق مسجل مسبقاً'
                ], 409);
            }

            // تسجيل النطاق
            $domain->update([
                'available' => false,
                'registered_at' => now(),
                'expires_at' => now()->addYears($request->years),
                'registration_period' => $request->years,
                'status' => 'registered',
                'notes' => $request->notes
            ]);

            // تحديث حالة المنتج
            if ($domain->product) {
                $domain->product->update([
                    'status' => 'inactive'
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل النطاق بنجاح',
                'data' => $domain
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تسجيل النطاق',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تجديد النطاق
     */
    public function renewDomain(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'years' => 'required|integer|min:1|max:10'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $domain = Domain::findOrFail($id);

            if (!$domain->registered_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'النطاق غير مسجل'
                ], 409);
            }

            $newExpiry = $domain->expires_at->addYears($request->years);

            $domain->update([
                'expires_at' => $newExpiry,
                'registration_period' => $request->years
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تجديد النطاق بنجاح',
                'data' => [
                    'old_expiry' => $domain->expires_at->format('Y-m-d'),
                    'new_expiry' => $newExpiry->format('Y-m-d'),
                    'years_added' => $request->years
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تجديد النطاق',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تفعيل/تعطيل حماية WHOIS
     */
    public function toggleWhoisProtection($id)
    {
        try {
            $domain = Domain::findOrFail($id);

            $newStatus = !$domain->whois_protected;
            $domain->update(['whois_protected' => $newStatus]);

            return response()->json([
                'success' => true,
                'message' => $newStatus ? 'تم تفعيل حماية WHOIS' : 'تم تعطيل حماية WHOIS',
                'data' => [
                    'whois_protected' => $newStatus
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تغيير إعدادات الحماية',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تفعيل/تعطيل التجديد التلقائي
     */
    public function toggleAutoRenew($id)
    {
        try {
            $domain = Domain::findOrFail($id);

            $newStatus = !$domain->auto_renew;
            $domain->update(['auto_renew' => $newStatus]);

            return response()->json([
                'success' => true,
                'message' => $newStatus ? 'تم تفعيل التجديد التلقائي' : 'تم تعطيل التجديد التلقائي',
                'data' => [
                    'auto_renew' => $newStatus
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تغيير إعدادات التجديد',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * استيراد نطاقات من ملف CSV
     */
    public function importDomains(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt',
            'price_yearly' => 'required|numeric|min:0',
            'price_monthly' => 'nullable|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $file = $request->file('file');
            $path = $file->getRealPath();
            $data = array_map('str_getcsv', file($path));

            $imported = 0;
            $skipped = 0;
            $errors = [];

            // تخطي الصف الأول إذا كان يحتوي على عناوين
            if (count($data) > 0 && strpos($data[0][0], 'domain') !== false) {
                array_shift($data);
            }

            foreach ($data as $index => $row) {
                try {
                    if (count($row) < 1) {
                        $errors[] = "الصف {$index}: بيانات غير كافية";
                        $skipped++;
                        continue;
                    }

                    $domainName = trim($row[0]);

                    // فصل النطاق عن TLD
                    $parts = explode('.', $domainName);
                    if (count($parts) < 2) {
                        $errors[] = "الصف {$index}: تنسيق نطاق غير صحيح - {$domainName}";
                        $skipped++;
                        continue;
                    }

                    $tld = array_pop($parts);
                    $name = implode('.', $parts);

                    // التحقق من عدم وجود النطاق مسبقاً
                    $exists = Domain::where('name', $name)
                                  ->where('tld', $tld)
                                  ->exists();

                    if ($exists) {
                        $errors[] = "الصف {$index}: النطاق موجود مسبقاً - {$domainName}";
                        $skipped++;
                        continue;
                    }

                    // إنشاء النطاق
                    $domain = Domain::create([
                        'name' => strtolower($name),
                        'tld' => strtolower($tld),
                        'available' => true,
                        'status' => 'available'
                    ]);

                    // إنشاء المنتج
                    $domain->createProduct([
                        'name' => $domain->full_domain,
                        'type' => 'domain',
                        'price_monthly' => $request->price_monthly ?? ($request->price_yearly / 12),
                        'price_yearly' => $request->price_yearly,
                        'description' => 'نطاق ' . strtoupper($tld),
                        'status' => 'active'
                    ]);

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "الصف {$index}: " . $e->getMessage();
                    $skipped++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم استيراد النطاقات بنجاح',
                'data' => [
                    'imported' => $imported,
                    'skipped' => $skipped,
                    'errors' => $errors
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في استيراد النطاقات',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * الحصول على إحصائيات النطاقات
     */
    public function getStats()
    {
        try {
            $stats = Domain::selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN available = 1 THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN registered_at IS NOT NULL THEN 1 ELSE 0 END) as registered,
                SUM(CASE WHEN expires_at IS NOT NULL AND expires_at < NOW() THEN 1 ELSE 0 END) as expired,
                SUM(CASE WHEN expires_at IS NOT NULL AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring_soon,
                SUM(CASE WHEN whois_protected = 1 THEN 1 ELSE 0 END) as whois_protected,
                SUM(CASE WHEN auto_renew = 1 THEN 1 ELSE 0 END) as auto_renew
            ')->first();

            // توزيع النطاقات حسب TLD
            $tldDistribution = Domain::select('tld')
                ->selectRaw('COUNT(*) as count,
                           ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM domains), 2) as percentage')
                ->groupBy('tld')
                ->orderByDesc('count')
                ->get();

            // إحصائيات التسجيل خلال الـ 30 يوم الماضية
            $registrationStats = Domain::selectRaw('
                DATE(registered_at) as date,
                COUNT(*) as count
            ')
            ->whereNotNull('registered_at')
            ->where('registered_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

            // النطاقات الأكثر طلباً
            $popularDomains = Domain::withCount('orderItems')
                ->orderByDesc('order_items_count')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'stats' => $stats,
                'tld_distribution' => $tldDistribution,
                'registration_stats' => $registrationStats,
                'popular_domains' => $popularDomains
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب الإحصائيات',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * البحث المتقدم في النطاقات
     */
    public function searchDomains(Request $request)
    {
        try {
            $query = Domain::query();

            // البحث عن طريق الاسم أو TLD
            if ($request->has('q')) {
                $search = $request->q;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('tld', 'like', "%{$search}%")
                      ->orWhereRaw("CONCAT(name, '.', tld) LIKE ?", ["%{$search}%"]);
                });
            }

            // فلترة حسب النطاقات المنتهية
            if ($request->boolean('expired_only')) {
                $query->whereNotNull('expires_at')
                      ->where('expires_at', '<', now());
            }

            // فلترة حسب النطاقات المسجلة
            if ($request->boolean('registered_only')) {
                $query->whereNotNull('registered_at');
            }

            // فلترة حسب النطاقات المتاحة
            if ($request->boolean('available_only')) {
                $query->where('available', true);
            }

            // فلترة حسب حماية WHOIS
            if ($request->has('whois_protected')) {
                $query->where('whois_protected', $request->boolean('whois_protected'));
            }

            // فلترة حسب التجديد التلقائي
            if ($request->has('auto_renew')) {
                $query->where('auto_renew', $request->boolean('auto_renew'));
            }

            // فلترة حسب الفترة الزمنية
            if ($request->has('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('created_at', '<=', $request->date_to);
            }

            // الترتيب
            $orderBy = $request->order_by ?? 'created_at';
            $orderDirection = $request->order_direction ?? 'desc';
            $query->orderBy($orderBy, $orderDirection);

            // الباجينيت
            $perPage = $request->per_page ?? 50;
            $domains = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $domains,
                'filters' => $request->all()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في البحث',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

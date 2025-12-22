<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;


use App\Models\Server;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ServerController extends Controller
{


    /**
     * عرض جميع السيرفرات
     */
    public function index(Request $request)
    {
        try {
            $query = Server::with('product')
                ->withCount('orderItems')
                ->latest();

            // البحث
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('datacenter_location', 'like', "%{$search}%")
                      ->orWhere('os', 'like', "%{$search}%");
                });
            }

            // التصفية حسب الفئة
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            // التصفية حسب الحالة
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // التصفية حسب الموقع
            if ($request->has('location')) {
                $query->where('datacenter_location', $request->location);
            }

            // التصفية حسب نظام التشغيل
            if ($request->has('os')) {
                $query->where('os', $request->os);
            }

            // التصفية حسب نوع التخزين
            if ($request->has('storage_type')) {
                $query->where('storage_type', $request->storage_type);
            }

            // الباجينيت
            $perPage = $request->per_page ?? 20;
            $servers = $query->paginate($perPage);

            // الإحصائيات
            $stats = Server::selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "available" THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN status = "sold_out" THEN 1 ELSE 0 END) as sold_out,
                SUM(CASE WHEN status = "maintenance" THEN 1 ELSE 0 END) as maintenance,
                SUM(stock) as total_stock,
                AVG(price_monthly) as avg_monthly_price,
                AVG(price_yearly) as avg_yearly_price
            ')->first();

            // جميع القيم المميزة
            $filters = [
                'categories' => Server::select('category')->distinct()->pluck('category'),
                'statuses' => Server::select('status')->distinct()->pluck('status'),
                'locations' => Server::select('datacenter_location')->distinct()->pluck('datacenter_location'),
                'os_list' => Server::select('os')->distinct()->pluck('os'),
                'storage_types' => Server::select('storage_type')->distinct()->pluck('storage_type')
            ];

            return response()->json([
                'success' => true,
                'data' => $servers,
                'stats' => $stats,
                'filters' => $filters,
                'applied_filters' => $request->only(['search', 'category', 'status', 'location', 'os', 'storage_type'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب السيرفرات',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * عرض سيرفر معين
     */
    public function show($id)
    {
        try {
            $server = Server::with([
                'product',
                'orderItems.order.user'
            ])->findOrFail($id);

            // الحصول على الطلبات النشطة
            $activeOrders = $server->orderItems()
                ->whereHas('order', function($q) {
                    $q->whereIn('status', ['paid', 'completed']);
                })
                ->with('order.user')
                ->latest()
                ->limit(10)
                ->get();

            // الإحصائيات
            $stats = [
                'total_orders' => $server->orderItems()->count(),
                'active_orders' => $activeOrders->count(),
                'total_revenue' => $server->orderItems()->sum('total'),
                'utilization_rate' => $server->max_stock > 0 ?
                    (($server->max_stock - $server->stock) / $server->max_stock * 100) : 0
            ];

            // خيارات التكوين
            $configurations = [
                'os_options' => ['Ubuntu', 'CentOS', 'Debian', 'Windows Server', 'AlmaLinux'],
                'locations' => ['Dubai', 'Frankfurt', 'London', 'Singapore', 'New York'],
                'storage_types' => ['SSD', 'HDD', 'NVMe'],
                'categories' => ['VPS', 'Dedicated', 'Cloud']
            ];

            return response()->json([
                'success' => true,
                'data' => $server,
                'active_orders' => $activeOrders,
                'stats' => $stats,
                'configurations' => $configurations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على السيرفر'
            ], 404);
        }
    }

    /**
     * إنشاء سيرفر جديد
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'cpu_cores' => 'required|integer|min:1',
            'cpu_speed' => 'required|string',
            'ram' => 'required|string',
            'storage' => 'required|integer|min:1',
            'storage_type' => 'required|in:SSD,HDD,NVMe',
            'bandwidth' => 'required|string',
            'datacenter_location' => 'required|string',
            'os' => 'required|string',
            'category' => 'required|in:VPS,Dedicated,Cloud',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'setup_fee' => 'nullable|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'stock' => 'required|integer|min:0',
            'max_stock' => 'required|integer|min:0',
            'managed' => 'boolean',
            'backup_enabled' => 'boolean',
            'monitoring_enabled' => 'boolean',
            'control_panel' => 'nullable|string',
            'description' => 'nullable|string',
            'status' => 'in:available,sold_out,maintenance'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // إنشاء السيرفر
            $server = Server::create([
                'name' => $request->name,
                'cpu_cores' => $request->cpu_cores,
                'cpu_speed' => $request->cpu_speed,
                'ram' => $request->ram,
                'storage' => $request->storage,
                'storage_type' => $request->storage_type,
                'bandwidth' => $request->bandwidth,
                'datacenter_location' => $request->datacenter_location,
                'os' => $request->os,
                'category' => $request->category,
                'price_monthly' => $request->price_monthly,
                'price_yearly' => $request->price_yearly,
                'setup_fee' => $request->setup_fee ?? 0,
                'discount_percentage' => $request->discount_percentage ?? 0,
                'stock' => $request->stock,
                'max_stock' => $request->max_stock,
                'managed' => $request->managed ?? false,
                'backup_enabled' => $request->backup_enabled ?? false,
                'monitoring_enabled' => $request->monitoring_enabled ?? false,
                'control_panel' => $request->control_panel,
                'description' => $request->description,
                'status' => $request->status ?? ($request->stock > 0 ? 'available' : 'sold_out')
            ]);

            // إنشاء المنتج المرتبط
            $server->createProduct([
                'name' => $server->name,
                'type' => 'server',
                'price_monthly' => $server->price_monthly,
                'price_yearly' => $server->price_yearly,
                'description' => $server->description,
                'status' => $server->status === 'available' ? 'active' : 'inactive'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء السيرفر بنجاح',
                'data' => $server->load('product')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في إنشاء السيرفر',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تحديث السيرفر
     */
    public function update(Request $request, $id)
    {
        $server = Server::with('product')->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'cpu_cores' => 'sometimes|integer|min:1',
            'cpu_speed' => 'sometimes|string',
            'ram' => 'sometimes|string',
            'storage' => 'sometimes|integer|min:1',
            'storage_type' => 'sometimes|in:SSD,HDD,NVMe',
            'bandwidth' => 'sometimes|string',
            'datacenter_location' => 'sometimes|string',
            'os' => 'sometimes|string',
            'category' => 'sometimes|in:VPS,Dedicated,Cloud',
            'price_monthly' => 'sometimes|numeric|min:0',
            'price_yearly' => 'sometimes|numeric|min:0',
            'setup_fee' => 'nullable|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'stock' => 'sometimes|integer|min:0',
            'max_stock' => 'sometimes|integer|min:0',
            'managed' => 'boolean',
            'backup_enabled' => 'boolean',
            'monitoring_enabled' => 'boolean',
            'control_panel' => 'nullable|string',
            'description' => 'nullable|string',
            'status' => 'in:available,sold_out,maintenance'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $oldStock = $server->stock;
            $oldStatus = $server->status;

            // تجميع التحديثات
            $updates = [];

            $fields = [
                'name', 'cpu_cores', 'cpu_speed', 'ram', 'storage', 'storage_type',
                'bandwidth', 'datacenter_location', 'os', 'category',
                'price_monthly', 'price_yearly', 'setup_fee', 'discount_percentage',
                'stock', 'max_stock', 'managed', 'backup_enabled', 'monitoring_enabled',
                'control_panel', 'description', 'status'
            ];

            foreach ($fields as $field) {
                if ($request->has($field)) {
                    $updates[$field] = $request->{$field};
                }
            }

            // تحديث حالة السيرفر بناءً على المخزون
            if ($request->has('stock')) {
                if ($request->stock > 0 && $oldStatus === 'sold_out') {
                    $updates['status'] = 'available';
                } elseif ($request->stock <= 0 && $oldStatus === 'available') {
                    $updates['status'] = 'sold_out';
                }
            }

            // تطبيق التحديثات
            $server->update($updates);

            // تحديث المنتج المرتبط
            if ($server->product) {
                $productUpdates = [
                    'name' => $server->name,
                    'price_monthly' => $server->price_monthly,
                    'price_yearly' => $server->price_yearly,
                    'description' => $server->description,
                    'status' => $server->status === 'available' ? 'active' : 'inactive'
                ];

                if ($request->has('price_monthly') || $request->has('price_yearly')) {
                    $server->product->update($productUpdates);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث السيرفر بنجاح',
                'data' => $server->fresh('product'),
                'changes' => [
                    'stock_change' => $request->has('stock') ? $request->stock - $oldStock : 0,
                    'status_change' => $oldStatus !== $server->status ?
                        ['from' => $oldStatus, 'to' => $server->status] : null
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تحديث السيرفر',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * حذف السيرفر
     */
    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $server = Server::with(['product', 'orderItems'])->findOrFail($id);

            // التحقق من عدم وجود طلبات نشطة
            $hasActiveOrders = $server->orderItems()
                ->whereHas('order', function($q) {
                    $q->whereIn('status', ['paid', 'completed']);
                })
                ->exists();

            if ($hasActiveOrders) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن حذف السيرفر لأنه مرتبط بطلبات نشطة'
                ], 409);
            }

            // حذف المنتج المرتبط
            if ($server->product) {
                $server->product->delete();
            }

            // حذف السيرفر
            $server->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف السيرفر بنجاح'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في حذف السيرفر',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تخصيص سيرفر يدوياً
     */
    public function allocateServer(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'order_id' => 'nullable|exists:orders,id',
            'ip_address' => 'nullable|ip',
            'hostname' => 'nullable|string',
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
            $server = Server::findOrFail($id);

            if (!$server->is_available) {
                return response()->json([
                    'success' => false,
                    'message' => 'السيرفر غير متاح للتخصيص'
                ], 409);
            }

            // تخصيص السيرفر
            $allocation = $server->allocateServer([
                'ip_address' => $request->ip_address,
                'hostname' => $request->hostname,
                'allocated_to' => $request->user_id,
                'allocation_type' => 'manual',
                'notes' => $request->notes
            ]);

            // إنشاء سجل التخصيص
            $server->allocations()->create([
                'user_id' => $request->user_id,
                'order_id' => $request->order_id,
                'ip_address' => $allocation['ip_address'],
                'hostname' => $allocation['hostname'],
                'root_password' => $allocation['root_password'],
                'status' => 'active',
                'allocated_by' => auth('sanctum')->user()->id,
                'notes' => $request->notes
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم تخصيص السيرفر بنجاح',
                'data' => [
                    'server' => $server,
                    'allocation' => $allocation
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تخصيص السيرفر',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تحرير سيرفر
     */
    public function releaseServer($id)
    {
        DB::beginTransaction();

        try {
            $server = Server::findOrFail($id);

            // تحرير السيرفر
            $server->releaseServer();

            // تحديث سجلات التخصيص
            $server->allocations()
                ->where('status', 'active')
                ->update([
                    'status' => 'released',
                    'released_at' => now(),
                    'released_by' => auth('sanctum')->user()->id
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم تحرير السيرفر بنجاح',
                'data' => [
                    'server' => $server,
                    'new_stock' => $server->stock
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تحرير السيرفر',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * إدارة المخزون
     */
    public function manageStock(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:add,remove,set',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $server = Server::findOrFail($id);
            $oldStock = $server->stock;

            switch ($request->action) {
                case 'add':
                    $newStock = $oldStock + $request->quantity;
                    break;

                case 'remove':
                    $newStock = $oldStock - $request->quantity;
                    if ($newStock < 0) {
                        return response()->json([
                            'success' => false,
                            'message' => 'الكمية المراد إزالتها أكبر من المخزون الحالي'
                        ], 409);
                    }
                    break;

                case 'set':
                    $newStock = $request->quantity;
                    break;
            }

            $server->update([
                'stock' => $newStock,
                'status' => $newStock > 0 ? 'available' : 'sold_out'
            ]);

            // تسجيل حركة المخزون
            $server->stockMovements()->create([
                'user_id' => auth('sanctum')->user()->id,
                'action' => $request->action,
                'quantity' => $request->quantity,
                'old_stock' => $oldStock,
                'new_stock' => $newStock,
                'reason' => $request->reason
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث المخزون بنجاح',
                'data' => [
                    'server' => $server,
                    'stock_movement' => [
                        'action' => $request->action,
                        'quantity' => $request->quantity,
                        'old_stock' => $oldStock,
                        'new_stock' => $newStock,
                        'reason' => $request->reason
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في إدارة المخزون',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تطبيق خصم على السيرفر
     */
    public function applyDiscount(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'reason' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $server = Server::findOrFail($id);
            $oldDiscount = $server->discount_percentage;

            $server->update([
                'discount_percentage' => $request->discount_percentage
            ]);

            // تسجيل حركة الخصم
            $server->discountHistory()->create([
                'user_id' => auth('sanctum')->user()->id,
                'old_discount' => $oldDiscount,
                'new_discount' => $request->discount_percentage,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'reason' => $request->reason
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تطبيق الخصم بنجاح',
                'data' => [
                    'server' => $server,
                    'discount_info' => [
                        'percentage' => $server->discount_percentage,
                        'monthly_price' => $server->price_monthly,
                        'yearly_price' => $server->price_yearly,
                        'discounted_monthly' => $server->discounted_price_monthly,
                        'discounted_yearly' => $server->discounted_price_yearly,
                        'saving_monthly' => $server->price_monthly - $server->discounted_price_monthly,
                        'saving_yearly' => $server->price_yearly - $server->discounted_price_yearly
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تطبيق الخصم',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * الحصول على إحصائيات السيرفرات
     */
    public function getStats()
    {
        try {
            // الإحصائيات العامة
            $stats = Server::selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "available" THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN status = "sold_out" THEN 1 ELSE 0 END) as sold_out,
                SUM(CASE WHEN status = "maintenance" THEN 1 ELSE 0 END) as maintenance,
                SUM(stock) as total_stock,
                SUM(max_stock) as total_capacity,
                AVG(price_monthly) as avg_monthly_price,
                AVG(price_yearly) as avg_yearly_price
            ')->first();

            // إحصائيات حسب الفئة
            $categoryStats = Server::selectRaw('
                category,
                COUNT(*) as count,
                SUM(stock) as stock,
                AVG(price_monthly) as avg_monthly_price
            ')
            ->groupBy('category')
            ->get();

            // إحصائيات حسب الموقع
            $locationStats = Server::selectRaw('
                datacenter_location as location,
                COUNT(*) as count,
                SUM(stock) as stock
            ')
            ->groupBy('datacenter_location')
            ->orderByDesc('count')
            ->get();

            // إحصائيات الاستخدام
            $utilizationStats = Server::selectRaw('
                SUM(CASE WHEN stock > 0 THEN 1 ELSE 0 END) as with_stock,
                SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock,
                ROUND(SUM(stock) * 100.0 / NULLIF(SUM(max_stock), 0), 2) as utilization_percentage
            ')->first();

            // أكثر السيرفرات طلباً
            $popularServers = Server::withCount('orderItems')
                ->orderByDesc('order_items_count')
                ->limit(10)
                ->get();

            // السيرفرات التي تنفذ منها الكمية
            $lowStockServers = Server::where('stock', '<=', 5)
                ->where('stock', '>', 0)
                ->orderBy('stock')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'stats' => $stats,
                'category_stats' => $categoryStats,
                'location_stats' => $locationStats,
                'utilization_stats' => $utilizationStats,
                'popular_servers' => $popularServers,
                'low_stock_servers' => $lowStockServers
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
     * إعادة تعيين كلمة مرور السيرفر
     */
    public function resetPassword($id)
    {
        try {
            $server = Server::findOrFail($id);

            $newPassword = bin2hex(random_bytes(8));

            // في الواقع هنا ستقوم بإعادة تعيين كلمة المرور على السيرفر الفعلي
            // هذا مثال رمزي

            // تسجيل عملية إعادة التعيين
            $server->passwordResets()->create([
                'user_id' => auth('sanctum')->user()->id,
                'new_password' => $newPassword,
                'reset_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إعادة تعيين كلمة المرور بنجاح',
                'data' => [
                    'server_id' => $server->id,
                    'server_name' => $server->name,
                    'new_password' => $newPassword,
                    'reset_by' => auth('sanctum')->user()->name,
                    'reset_at' => now()->format('Y-m-d H:i:s')
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في إعادة تعيين كلمة المرور',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تغيير حالة السيرفر (للصيانة)
     */
    public function changeStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:available,sold_out,maintenance',
            'reason' => 'required|string|max:500',
            'estimated_duration' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $server = Server::findOrFail($id);
            $oldStatus = $server->status;

            $server->update([
                'status' => $request->status
            ]);

            // تسجيل تغيير الحالة
            $server->statusChanges()->create([
                'user_id' => auth('sanctum')->user()->id,
                'old_status' => $oldStatus,
                'new_status' => $request->status,
                'reason' => $request->reason,
                'estimated_duration' => $request->estimated_duration
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تغيير حالة السيرفر بنجاح',
                'data' => [
                    'server' => $server,
                    'status_change' => [
                        'from' => $oldStatus,
                        'to' => $request->status,
                        'reason' => $request->reason,
                        'changed_by' => auth('sanctum')->user()->name,
                        'changed_at' => now()
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تغيير حالة السيرفر',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * البحث المتقدم في السيرفرات
     */
    public function searchServers(Request $request)
    {
        try {
            $query = Server::query();

            // فلاتر متقدمة
            if ($request->has('min_cpu_cores')) {
                $query->where('cpu_cores', '>=', $request->min_cpu_cores);
            }

            if ($request->has('max_cpu_cores')) {
                $query->where('cpu_cores', '<=', $request->max_cpu_cores);
            }

            if ($request->has('min_storage')) {
                $query->where('storage', '>=', $request->min_storage);
            }

            if ($request->has('max_storage')) {
                $query->where('storage', '<=', $request->max_storage);
            }

            if ($request->has('min_price_monthly')) {
                $query->where('price_monthly', '>=', $request->min_price_monthly);
            }

            if ($request->has('max_price_monthly')) {
                $query->where('price_monthly', '<=', $request->max_price_monthly);
            }

            if ($request->has('managed')) {
                $query->where('managed', $request->boolean('managed'));
            }

            if ($request->has('backup_enabled')) {
                $query->where('backup_enabled', $request->boolean('backup_enabled'));
            }

            if ($request->has('monitoring_enabled')) {
                $query->where('monitoring_enabled', $request->boolean('monitoring_enabled'));
            }

            if ($request->has('with_discount')) {
                $query->where('discount_percentage', '>', 0);
            }

            // الترتيب
            $orderBy = $request->order_by ?? 'created_at';
            $orderDirection = $request->order_direction ?? 'desc';
            $query->orderBy($orderBy, $orderDirection);

            // الباجينيت
            $perPage = $request->per_page ?? 50;
            $servers = $query->with('product')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $servers,
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

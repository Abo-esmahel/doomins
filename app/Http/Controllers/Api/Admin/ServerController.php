<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\Product;
use Illuminate\Auth\Events\Validated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ServerController extends Controller
{
    /**
     * عرض صفحة السيرفرات الرئيسية
     */
    public function index()
    {
        // الحصول على جميع السيرفرات المتاحة مع منتجاتها
        $servers = Server::with('product')
            ->where('status', 'available')
            ->where('isActive', true)
            ->orderBy('price_monthly', 'asc')
            ->paginate(12);

        $servers->each(function ($server) {
            if (!$server->product) {
                $product = Product::create([
                    'name' => $server->name,
                    'type' => 'server',
                    'price_monthly' => $server->price_monthly,
                    'price_yearly' => $server->price_yearly,
                    'description' => $server->description,
                    'productable_id' => $server->id,
                    'productable_type' => Server::class,
                ]);
                $server->product = $product;
            }
        });

        $categories = Server::select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return response()->json([
            'servers' => $servers,
            'categories' => $categories,
        ]);
    }

    /**
     * تصفية السيرفرات
     */
    public function filter(Request $request)
    {
        $servers = Server::with('product')
            ->when($request->category, function ($query) use ($request) {
                $query->where('category', $request->category);
            })
            ->when($request->storage_type, function ($query) use ($request) {
                $query->where('storage_type', $request->storage_type);
            })
            ->when($request->min_price, function ($query) use ($request) {
                $query->where('price_monthly', '>=', $request->min_price);
            })
            ->when($request->max_price, function ($query) use ($request) {
                $query->where('price_monthly', '<=', $request->max_price);
            })
            ->available()
            ->orderBy('price_monthly', 'asc')
            ->paginate(12);

        $categories = Server::select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $storageTypes = Server::select('storage_type')
            ->distinct()
            ->orderBy('storage_type')
            ->pluck('storage_type');

       return response()->json([
            'servers' => $servers,
            'categories' => $categories,
            'storage_types' => $storageTypes,
        ]);
    }


    public function show($id)
    {
        $server = Server::where('isActive', true)
            ->where('status', 'available')
            ->with('product')
            ->findOrFail($id);

        if (!$server->product) {
            $product = Product::create([
                'name' => $server->name,
                'type' => 'server',
                'price_monthly' => $server->price_monthly,
                'price_yearly' => $server->price_yearly,
                'description' => $server->description,
                'productable_id' => $server->id,
                'productable_type' => Server::class,
            ]);
            $server->load('product');
        }

        $similarServers = Server::where('category', $server->category)
            ->where('id', '!=', $server->id)
            ->with('product')
            ->where('status', 'available')
            ->limit(4)
            ->get();

        return response()->json([
            'server' => $server,
            'similar_servers' => $similarServers,
        ]);
    }

public function search(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'min_price' => 'nullable|numeric|min:0',
        'max_price' => 'nullable|numeric|min:0|gt:min_price',
        'product_id' => 'nullable|exists:products,id',
        'sort_by' => 'nullable|in:price_asc,price_desc,name_asc,name_desc,created_at',
        'per_page' => 'nullable|integer|min:1|max:100',
    ], [
        'name.required' => 'حقل البحث مطلوب',
        'max_price.gt' => 'السعر الأعلى يجب أن يكون أكبر من السعر الأدنى',
    ]);

    $query = $validated['name'];
    $perPage = $validated['per_page'] ?? 12;

    $serversQuery = Server::where('isActive', true)
        ->where('status', 'available');

    if (!empty($query)) {
        $serversQuery->where(function ($q) use ($query) {
            $q->where('name', 'LIKE', "%{$query}%");

        });
    }


    if (isset($validated['min_price'])) {
        $serversQuery->where('price_monthly', '>=', $validated['min_price']);
    }

    if (isset($validated['max_price'])) {
        $serversQuery->where('price_monthly', '<=', $validated['max_price']);
    }

    // فلترة بالمنتج
    if (isset($validated['product_id'])) {
        $serversQuery->where('product_id', $validated['product_id']);
    }

    // التصنيف
    $sortBy = $validated['sort_by'] ?? 'price_asc';
    switch ($sortBy) {
        case 'price_desc':
            $serversQuery->orderBy('price_monthly', 'desc');
            break;
        case 'name_asc':
            $serversQuery->orderBy('name', 'asc');
            break;
        case 'name_desc':
            $serversQuery->orderBy('name', 'desc');
            break;
        case 'created_at':
            $serversQuery->orderBy('created_at', 'desc');
            break;
        default: // price_asc
            $serversQuery->orderBy('price_monthly', 'asc');
    }

    // الحصول على النتائج
    $servers = $serversQuery->with(['product' => function($query) {
            $query->select('id', 'name'); // تحديد الحقول المطلوبة فقط
        }])
        ->paginate($perPage);

    $suggestions = [];
    if (strlen($query) >= 2) { // فقط إذا كان طول البحث 2 أحرف أو أكثر
        $suggestions = Server::where('isActive', true)
            ->where('status', 'available')
            ->where('name', 'LIKE', "{$query}%") // البحث يبدأ بالكلمة
            ->limit(10)
            ->pluck('name')
            ->toArray();
    }

    return response()->json([
        'success' => true,
        'message' => 'تم العثور على ' . $servers->total() . ' نتيجة',
        'query' => $query,
        'servers' => $servers,
        'suggestions' => $suggestions,
        'filters' => [
            'applied' => $validated,
            'available_products' => Product::where('is_active', true)
                ->select('id', 'name')
                ->get()
        ],
        'meta' => [
            'total' => $servers->total(),
            'per_page' => $servers->perPage(),
            'current_page' => $servers->currentPage(),
            'last_page' => $servers->lastPage(),
            'from' => $servers->firstItem(),
            'to' => $servers->lastItem(),
        ]
    ]);
}


}

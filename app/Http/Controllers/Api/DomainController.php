<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\Domain;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DomainController extends Controller
{
    // لكل الناس
    public function index()
    {
        $domains = Domain::where('isActive', true)
            ->where('status', 'available')
            ->with('product')
            ->orderBy('created_at', 'desc')
            ->get();
       
        $domains->each(function ($domain) {
            if (!$domain->product) {
                $product = Product::create([
                    'name' => $domain->name . '.' . $domain->tld,
                    'type' => 'domain',
                    'price_monthly' => $domain->price_monthly,
                    'price_yearly' => $domain->price_yearly,
                    'description' => 'نطاق ' . $domain->tld,
                    'productable_id' => $domain->id,
                    'productable_type' => Domain::class,
                ]);
                $domain->product = $product;
            }
        });

        $tlds = Domain::select('tld')
            ->distinct()
            ->orderBy('tld')
            ->pluck('tld');
         if ($domains->isEmpty()) {
            return response()->json([
                'domains' => [],
                'tlds' => $tlds,
                'success' => true,
                'message' => 'No available domains found',
            ]);
        }

       return response()->json([
            'domains' => $domains,
            'tlds' => $tlds,
            'success' => true,
            'message' => 'Domains retrieved successfully',
        ]);
    }

    public function search(Request $request)
    {
        $request->validate([
            'domain' => 'required|string|min:2',
            'tlds' => 'nullable|string|in:.com,.net,.org,.sa,.ae,.edu,.gov,.info,.biz',
        ]);

        $searchTerm = $request->domain;
        $selectedTlds = $request->tlds ?? ['.com', '.net', '.org', '.sa', '.ae', '.edu', '.gov', '.info', '.biz',];

     
        $suggestions = [];

        $domains = Domain::where('name', 'like', '%' . $searchTerm . '%')
            ->whereIn('tld', $selectedTlds)
            ->where('status', 'available')
            ->with('product')
            ->get();

        $suggestedNames = [
            $searchTerm . 'tech',
            $searchTerm . 'online',
            $searchTerm . 'hub',
            'my' . $searchTerm,
            $searchTerm . 'store',
            $searchTerm . 'blog',
            $searchTerm . 'site',
            'get' . $searchTerm,
            $searchTerm . 'world',
            $searchTerm . 'app',
            $searchTerm . 'cloud',
            $searchTerm . 'digital',
            $searchTerm . 'shop',
            'the' . $searchTerm,
            $searchTerm . 'media',
            'try' . $searchTerm,
            $searchTerm . 'space',
            $searchTerm . 'sites',
            $searchTerm . 'get',
        ];

        foreach ($suggestedNames as $name) {
            foreach ($selectedTlds as $tld) {
                $domainExists = Domain::where('name', $name)
                    ->where('tld', $tld)
                    ->where('status', 'available')
                    ->exists();

                if (!$domainExists) {
                    $suggestions[] = [
                        'name' => $name,
                        'tld' => $tld,
                        'full_name' => $name . '.' . $tld,
                        'price_monthly' => $this->getPriceForTld($tld),
                        'price_yearly' => $this->getPriceForTld($tld) * 10, // سعر سنوي تقريبي
                        'available' => true,
                    ];
                }
            }
        }

        $suggestions = array_slice($suggestions, 0, 6);
       return response()->json([
            'success' => true,
            'results' => $domains,
            'suggestions' => $suggestions,
            'search_term' => $searchTerm,
        ]);
     }

    
    public function show($id)
    {
        $domain = Domain::where('isActive', true)
            ->where('status', 'available')
            ->with('product')
            ->findOrFail($id);

        if (!$domain->product) {
            $product = Product::create([
                'name' => $domain->name . '.' . $domain->tld,
                'type' => 'domain',
                'price_monthly' => $domain->price_monthly,
                'price_yearly' => $domain->price_yearly,
                'description' => 'نطاق ' . $domain->tld,
                'productable_id' => $domain->id,
                'productable_type' => Domain::class,
            ]);
            $domain->load('product');
        }

        $similarDomains = Domain::where('tld', $domain->tld)
            ->where('id', '!=', $domain->id)
            ->with('product')
            ->where('isActive', true)
            ->where('status', 'available')
            ->limit(4)
            ->get();

         return response()->json([
            'domain' => $domain,
            'similar_domains' => $similarDomains,
            'success' => true,
            'message' => 'Domain details retrieved successfully',
        ]);
        }

    

    /**
     *  تسجيل دومين جديد
     *///Admin
    public static function store(Request $request)
    {
        $user = auth('sanctum')->user();
        if(!$user){
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولا.',
            ], 401);
        }

        $request->validate([
            'name' => 'required|string|min:2|max:63|regex:/^[a-z0-9][a-z0-9-]*[a-z0-9]$/i',
            'tld' => 'required|string|in:.com,.net,.org,.sa,.ae,.edu,.gov,.info,.biz',
            'price_monthly' => 'nullable|numeric|min:0',
            'price_yearly' => 'nullable|numeric|min:0',
            'expires_at' => 'required|date',
        ]);

        $name = strtolower($request->name);
        $tld = $request->tld;

        $exists = Domain::where('name', $name)
            ->where('tld', $tld)
            ->exists();

        if ($exists) {
      return response()->json([
            'success' => false,
            'message' => 'الدومين موجود فعلا.',
        ], 409);
        }

        DB::beginTransaction();

        try {
            $domain = Domain::create([
                'added_by' => $user->id,
                'name' => $name,
                'tld' => $tld,
                'price_monthly' => $request->price_monthly,
                'price_yearly' => $request->price_yearly,
                'expires_at' => $request->expires_at,
                'status' => 'available',
                'isActive' => true,
            ]);
         DB::table('admin_logs')->insert([
                'admin_id' => $user->id,
                'action' => 'create',
                'table_name' => 'domains',
                'record_id' => $domain->id,
                'details' => json_encode([
                    'name' => $domain->name,
                    'tld' => $domain->tld,
                    'price_monthly' => $domain->price_monthly,
                    'price_yearly' => $domain->price_yearly,
                    'expires_at' => $domain->expires_at,
                    'status' => $domain->status,
                    'isActive' => $domain->isActive,
                    'description' => 'تسجيل دومين جديد ' . $domain->name . '.' . $domain->tld,
                ]),
                'ip_address' => request()->ip(),
            ]);

            $product = Product::create([
                'name' => $domain->name . '.' . $domain->tld,
                'type' => 'domain',
                'price_monthly' => $domain->price_monthly,
                'price_yearly' => $domain->price_yearly,
                'description' => 'نطاق ' . $domain->tld,
                'productable_id' => $domain->id,
                'productable_type' => Domain::class,
            ]);

               
                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'تم تسجيل الدومين بنجاح.',
                    'domain' => $domain,
                ], 201);
            }

        
           

         catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تسجيل الدومين.',
            ], 500);
        }
    }

    /**
     * البحث الفوري عن الدومينات (للاستخدام في AJAX)
     */
    public function checkAvailability(Request $request)
    {
        $request->validate([
            'domain' => 'required|string|min:2',
            'tlds' => 'nullable|string|in:.com,.net,.org,.sa,.ae,.edu,.gov,.info,.biz',
        ]);

        $searchTerm = strtolower($request->domain);
        $tlds = $request->tlds ?? ['.com', '.net', '.org', '.sa', '.ae', '.edu', '.gov', '.info', '.biz'];

        $results = [];

        foreach ($tlds as $tld) {
            // التحقق من توفر الدومين في قاعدة البيانات
            $exists = Domain::where('name', $searchTerm)
                ->where('tld', $tld)
                ->exists();

            $results[] = [
                'domain' => $searchTerm . $tld,
                'available' => !$exists,
                'price_monthly' => $this->getPriceForTld($tld),
                'price_yearly' => $this->getPriceForTld($tld) * 10,
                'tld' => $tld,
            ];
        }

        return response()->json([
            'success' => true,
            'results' => $results,
            'search_term' => $searchTerm,
        ]);
    }
   

  

    //Admin
    public static function renew(Request $request, $id)
    {
        $request->validate([
            'period' => 'required|in:1,2,3,5,10',
            'billing_period' => 'required|in:monthly,yearly',
        ]);

        $domain = Domain::findOrFail($id);
        if(!Carbon::parse($domain->expires_at)->isPast()){
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تجديد الدومين قبل انتهاء صلاحيته.',
            ], 400);    
        }
        $user = auth('sanctum')->user();
         
         if ($request->billing_period == 'yearly') {
            $domain->expires_at = Carbon::parse($domain->expires_at)->addYears($request->period);
            $domain->save();
        } else {
            $domain->expires_at = Carbon::parse($domain->expires_at)->addMonths($request->period);
            $domain->save();
        }

         DB::table('admin_logs')->insert([
            'admin_id' => $user->id,
            'action' => 'update',
            'table_name' => 'domains',
            'record_id' => $domain->id,
            'details' => json_encode([
                'period' => $request->period,
                'billing_period' => $request->billing_period,
                'description' => 'تجديد الدومين ' . $domain->name . '.' . $domain->tld,
            ]),

            'ip_address' => request()->ip(),
      
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تجديد الدومين بنجاح.',
            'domain' => $domain,
        ]);

        
    }

    /**
     * دالة مساعدة: الحصول على سعر TLD
     */
    private function getPriceForTld($tld)
    {
        $prices = [
            '.com' => 3,
            '.net' => 2,
            '.org' => 2,
            '.sa' => 2,
            '.ae' => 2,
            '.edu' => 3,
            '.gov' => 5,
            '.info' => 3,
            '.biz' => 2,
        ];

        return $prices[$tld] ?? 50;
    }
}

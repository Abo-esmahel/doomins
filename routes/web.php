<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\AdminController;

// الصفحة الرئيسية
Route::get('/', function () {
    return view('welcome');
})->name('home');

// ============ روابط الدومينات ============
Route::prefix('domains')->group(function () {
    Route::get('/', [DomainController::class, 'index'])->name('domains.index');
    Route::get('/search', [DomainController::class, 'search'])->name('domains.search');
    Route::post('/check-availability', [DomainController::class, 'checkAvailability'])->name('domains.check-availability');
    Route::get('/{domain}', [DomainController::class, 'show'])->name('domains.show');

    // روابط تتطلب تسجيل الدخول
    Route::middleware(['auth'])->group(function () {
        Route::get('/create', [DomainController::class, 'create'])->name('domains.create');
        Route::post('/', [DomainController::class, 'store'])->name('domains.store');
        Route::get('/my/domains', [DomainController::class, 'myDomains'])->name('domains.my-domains');
        Route::get('/{domain}/manage', [DomainController::class, 'manage'])->name('domains.manage');
        Route::post('/{domain}/renew', [DomainController::class, 'renew'])->name('domains.renew');
        Route::post('/{domain}/transfer', [DomainController::class, 'transfer'])->name('domains.transfer');
        Route::post('/{domain}/settings', [DomainController::class, 'updateSettings'])->name('domains.update-settings');
    });
});

// ============ روابط السيرفرات ============
Route::prefix('servers')->group(function () {
    Route::get('/', [ServerController::class, 'index'])->name('servers.index');
    Route::get('/filter', [ServerController::class, 'filter'])->name('servers.filter');
    Route::get('/custom', [ServerController::class, 'custom'])->name('servers.custom');
    Route::get('/{server}', [ServerController::class, 'show'])->name('servers.show');

    // روابط تتطلب تسجيل الدخول
    Route::middleware(['auth'])->group(function () {
        Route::post('/custom', [ServerController::class, 'storeCustom'])->name('servers.store-custom');
        Route::get('/my/servers', [ServerController::class, 'myServers'])->name('servers.my-servers');
    });
});

// ============ إدارة السلة والطلبات والدفع ============
Route::middleware(['auth'])->group(function () {
    // إدارة السلة
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index'])->name('cart.index');
        Route::post('/add/{product}', [CartController::class, 'add'])->name('cart.add');
        Route::put('/update/{item}', [CartController::class, 'update'])->name('cart.update');
        Route::delete('/remove/{item}', [CartController::class, 'remove'])->name('cart.remove');
        Route::post('/clear', [CartController::class, 'clear'])->name('cart.clear');
    });

    // إدارة الطلبات
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/{order}', [OrderController::class, 'show'])->name('orders.show');
    });

    // إتمام الشراء
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout/process', [CheckoutController::class, 'process'])->name('checkout.process');
    Route::post('/checkout/quick-pay', [CheckoutController::class, 'quickPay'])->name('checkout.quick-pay');
    Route::post('/checkout/partial', [CheckoutController::class, 'partialPayment'])->name('checkout.partial');
    Route::get('/checkout/verify-stock', [CheckoutController::class, 'verifyStock'])->name('checkout.verify-stock');
    Route::post('/orders/{order}/cancel-payment', [CheckoutController::class, 'cancelPayment'])->name('orders.cancel-payment');
});

// ============ لوحة التحكم للمستخدم ============
Route::middleware(['auth'])->get('/dashboard', function () {
    $user = auth()->user();
    $activeCart = \App\Models\Cart::where('user_id', $user->id)
        ->where('is_active', true)
        ->withCount('items')
        ->first();

    $orders = \App\Models\Order::where('user_id', $user->id)
        ->latest()
        ->limit(5)
        ->get();

    return view('dashboard', compact('user', 'activeCart', 'orders'));
})->name('dashboard');

// ============ روابط الإدارة (Admin) ============
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {

    // لوحة التحكم الرئيسية للإدارة
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

    // إدارة الدومينات
    Route::prefix('domains')->name('domains.')->group(function () {
        Route::get('/', [AdminController::class, 'domainsIndex'])->name('index');
        Route::get('/create', [AdminController::class, 'domainsCreate'])->name('create');
        Route::post('/', [AdminController::class, 'domainsStore'])->name('store');
        Route::get('/{domain}/edit', [AdminController::class, 'domainsEdit'])->name('edit');
        Route::put('/{domain}', [AdminController::class, 'domainsUpdate'])->name('update');
        Route::delete('/{domain}', [AdminController::class, 'domainsDestroy'])->name('destroy');
        Route::post('/{domain}/toggle', [AdminController::class, 'domainsToggle'])->name('toggle');
    });

    // إدارة السيرفرات
    Route::prefix('servers')->name('servers.')->group(function () {
        Route::get('/', [AdminController::class, 'serversIndex'])->name('index');
        Route::get('/create', [AdminController::class, 'serversCreate'])->name('create');
        Route::post('/', [AdminController::class, 'serversStore'])->name('store');
        Route::get('/{server}/edit', [AdminController::class, 'serversEdit'])->name('edit');
        Route::put('/{server}', [AdminController::class, 'serversUpdate'])->name('update');
        Route::delete('/{server}', [AdminController::class, 'serversDestroy'])->name('destroy');
    });

    // إدارة المنتجات (مطلوبة لحل المشكلة)
    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/', [AdminController::class, 'productsIndex'])->name('index');
        Route::get('/create', [AdminController::class, 'productsCreate'])->name('create');
        Route::post('/', [AdminController::class, 'productsStore'])->name('store');
        Route::get('/{product}/edit', [AdminController::class, 'productsEdit'])->name('edit');
        Route::put('/{product}', [AdminController::class, 'productsUpdate'])->name('update');
        Route::delete('/{product}', [AdminController::class, 'productsDestroy'])->name('destroy');
    });

    // إدارة الطلبات
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [AdminController::class, 'ordersIndex'])->name('index');
        Route::get('/{order}', [AdminController::class, 'ordersShow'])->name('show');
        Route::post('/{order}/status', [AdminController::class, 'ordersUpdateStatus'])->name('update-status');
    });

    // إدارة المستخدمين
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [AdminController::class, 'usersIndex'])->name('index');
        Route::get('/{user}', [AdminController::class, 'usersShow'])->name('show');
        Route::post('/{user}/balance', [AdminController::class, 'usersUpdateBalance'])->name('update-balance');
        Route::post('/{user}/ban', [AdminController::class, 'usersToggleBan'])->name('toggle-ban');
    });

    // الإحصائيات والتقارير
    Route::get('/statistics', [AdminController::class, 'statistics'])->name('statistics');
});

// ============ روابط للمصادقة ============
Route::get('/login', function () {
    return view('auth.login');
})->name('login');

Route::get('/register', function () {
    return view('auth.register');
})->name('register');

Route::post('/logout', function () {
    auth()->logout();
    return redirect('/');
})->name('logout');

// ============ رابط للوصول السريع للادمن ============
Route::middleware(['auth'])->get('/become-admin', function () {
    $user = auth()->user();
    $user->is_admin = true;
    $user->save();

    return redirect()->route('admin.dashboard')
        ->with('success', 'تم رفعك إلى مدير النظام بنجاح!');
})->name('become.admin');

// ============ رابط إنشاء حساب مدير ============
Route::get('/create-admin', function () {
    return view('auth.create-admin');
})->name('create.admin.view');

Route::post('/create-admin', function (\Illuminate\Http\Request $request) {
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'password' => 'required|string|min:8|confirmed',
    ]);

    $user = \App\Models\User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => bcrypt($request->password),
        'is_admin' => true,
        'balance' => 10000,
    ]);

    auth()->login($user);

    return redirect()->route('admin.dashboard')
        ->with('success', 'تم إنشاء حساب المدير وتسجيل الدخول بنجاح!');
})->name('create.admin');

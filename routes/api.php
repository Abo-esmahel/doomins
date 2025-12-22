<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\ServerController as AdminServerController;
use App\Http\Controllers\Api\DomainController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\ServerController;

/*
|--------------------------------------------------------------------------
| Public Routes (المسارات العامة)
|--------------------------------------------------------------------------
*/

// Authentication (يفترض وجود AuthController بناءً على الملف السابق)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/activation/verify', [AuthController::class, 'ActivateAccount']);
    Route::post('/activation/resend', [AuthController::class, 'sendActivationCode']);
    Route::post('/password/reset', [AuthController::class, 'resetUserPassword']);
    Route::post('/password/sendCode', [AuthController::class, 'sendPasswordResetCode']);
});

// Public Services
/*
|--------------------------------------------------------------------------
| Public Services (الخدمات العامة المتاحة للزوار)
|--------------------------------------------------------------------------
*/

// خدمات الدومينات العامة (من DomainController.php)
Route::prefix('public/domains')->group(function () {
    Route::get('/', [DomainController::class, 'index']); // عرض كل الدومينات المتاحة للبيع
    Route::get('/search', [DomainController::class, 'search']); // البحث عن دومين معين
    Route::get('/{id}', [DomainController::class, 'show']); // تفاصيل دومين محدد (سعر، وصف)
});

// خدمات السيرفرات العامة (لعرض الخطط المتاحة)
Route::prefix('public/servers')->group(function () {
    Route::get('/', [ServerController::class, 'index']); // عرض خطط السيرفرات المتاحة للجمهور
    Route::get('/{id}', [ServerController::class, 'show']); // تفاصيل مواصفات السيرفر
    Route::get('/filter', [ServerController::class, 'filter']); // تصفية السيرفرات حسب الفئة أو السعر
    Route::get('/search', [ServerController::class, 'search']); // بحث عام في السيرفرات
});

Route::get('/public/products', [AdminController::class, 'productsIndex']); // عرض قائمة المنتجات العامة

/*
|--------------------------------------------------------------------------
| Protected User Routes (مسارات المستخدمين المسجلين)
|--------------------------------------------------------------------------
| Middleware: auth:sanctum
*/
Route::middleware(['auth:sanctum'])->group(function () {

    // Auth Logout
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // --- User Profile ---
    Route::prefix('profile')->group(function () {
        Route::get('/', [UserController::class, 'profile']);
        Route::put('/update', [UserController::class, 'updateProfile']);
        Route::put('/change-password', [UserController::class, 'changePassword']);
        Route::post('/update-password', [UserController::class, 'updatePassword']); // Alias defined in controller
    });


    // --- User Domains Management ---
    Route::prefix('user-domains')->group(function () {
        Route::get('/', [UserController::class, 'userDomains']); // كل دومينات المستخدم
        Route::get('/active', [UserController::class, 'activeDomains']); // النشطة فقط
        Route::get('/expiring', [UserController::class, 'expiringDomains']); // التي ستنتهي قريباً
        Route::get('/{id}', [UserController::class, 'showDomain']); // عرض دومين محدد
        Route::post('/{id}/renew', [UserController::class, 'renewDomain']); // تجديد دومين
        Route::post('/purchase/{productId}', [UserController::class, 'purchaseDomain']); // شراء دومين جديد مباشر
        Route::post('/search', [UserController::class, 'searchUserDomains']); // بحث في دومينات المستخدم
    });

    // --- User Servers Management ---
    Route::prefix('user-servers')->group(function () {
        Route::get('/', [UserController::class, 'userServers']);
        Route::get('/active', [UserController::class, 'activeServers']);
        Route::get('/expiring', [UserController::class, 'expiringServers']);
        Route::get('/{id}', [UserController::class, 'showServer']);
        Route::post('/{id}/renew', [UserController::class, 'renewServer']);
        Route::post('/purchase/{productId}', [UserController::class, 'purchaseServer']);
        Route::post('/search', [UserController::class, 'searchUserServers']);

    });

    // --- User Products (General) ---
    Route::get('/user-products', [UserController::class, 'userProducts']);
    Route::get('/active-products', [UserController::class, 'activeProducts']);

    // --- Financials (Invoices & Transactions) ---
    Route::get('/user-invoices', [UserController::class, 'invoices']);
    Route::get('/user-invoices/unpaid', [UserController::class, 'unpaidInvoices']);
    Route::get('/user-transactions', [UserController::class, 'transactions']);
    Route::get('/user-transactions/recent', [UserController::class, 'recentTransactions']);

    // --- Cart Management ---
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/add/{productId}', [CartController::class, 'add']);
        Route::put('/update/{itemId}', [CartController::class, 'update']);
        Route::delete('/remove/{itemId}', [CartController::class, 'remove']);
        Route::post('/clear', [CartController::class, 'clear']);
        Route::post('/purchase', [CartController::class, 'purchase']); // شراء السلة بالكامل
    });

    // --- Checkout & Payment ---
    Route::prefix('checkout')->group(function () {
        Route::get('/', [CheckoutController::class, 'show']);
        Route::post('/process', [CheckoutController::class, 'process']); // Generic process
        Route::get('/verify-stock', [CheckoutController::class, 'verifyStock']); // التحقق من التوفر قبل الدفع
    });

    // --- Orders ---
    // (OrderController wasn't uploaded but is standard, User methods assumed)
    Route::prefix('user-orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/{order}', [OrderController::class, 'show']);
        Route::post('/{order}/cancel-payment', [CheckoutController::class, 'cancelPayment']); // إلغاء واسترداد
    });

});

/*
|--------------------------------------------------------------------------
| Admin Routes (لوحة تحكم الإدارة)
|--------------------------------------------------------------------------
| Middleware: auth:sanctum, admin
*/
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {

    // --- Dashboard & Stats ---
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/statistics', [AdminController::class, 'statistics']);

    // --- Admin: Domains Management ---
    Route::prefix('domains')->group(function () {
        Route::get('/', [AdminController::class, 'domainsIndex']); // عرض الكل
        Route::post('/', [AdminController::class, 'domainsStore']); // إضافة جديد

        // فلاتر الحالة
        Route::get('/unavailable', [AdminController::class, 'unavailableDomains']);
        Route::get('/expired-company', [AdminController::class, 'domainsUnavailable']); // منتهية من الشركة الأم
        Route::get('/expired-users', [AdminController::class, 'domainsExpiredInUser']); // منتهية لدى المستخدم
        Route::get('/available', [AdminController::class, 'domainsAvailable']);

        // عمليات على الدومين
        Route::put('/{id}', [AdminController::class, 'domainsEdit']); // تحديث (Update)
        Route::delete('/{id}', [AdminController::class, 'domainsDestroy']); // حذف

        // عمليات خاصة
        Route::post('/{id}/set-active', [AdminController::class, 'setActiveDomain']);
        Route::post('/{id}/set-inactive', [AdminController::class, 'setInactiveDomain']);
        Route::post('/{id}/release', [AdminController::class, 'releaseDomain']); // تحرير من المستخدم
        Route::post('/{id}/extend', [AdminController::class, 'extendDomainExpiry']); // تمديد الصلاحية
        Route::post('/{id}/assign/{userId}', [AdminController::class, 'assignDomainToUser']); // إعطاء لمستخدم
    });


    Route::prefix('servers')->group(function () {
        Route::get('/', [AdminController::class, 'serversIndex']);
        Route::post('/', [AdminController::class, 'serversStore']);

        // فلاتر الحالة
        Route::get('/available', [AdminController::class, 'serversAvailable']);
        Route::get('/unavailable', [AdminController::class, 'serversUnavailable']);
        Route::get('/expired-users', [AdminController::class, 'serversExpiredInUser']);
        Route::get('/maintenance', [AdminController::class, 'serversInMaintenance']);
        Route::get('/expired-company', [AdminController::class, 'serversInactive']);

        // عمليات أساسية
        Route::put('/{id}', [AdminController::class, 'serversUpdate']);
        Route::delete('/{id}', [AdminController::class, 'serversDestroy']);

        // عمليات خاصة
        Route::post('/{id}/maintenance', [AdminController::class, 'setServerMaintenance']);
        Route::post('/{id}/extend', [AdminController::class, 'extendServerExpiration']);
        Route::post('/{id}/release', [AdminController::class, 'releaseServer']);
        Route::post('/{id}/assign/{userId}', [AdminController::class, 'assignServerToUser']);
    });




    // --- Admin: Products Management ---
    Route::prefix('products')->group(function () {
        Route::get('/', [AdminController::class, 'productsIndex']);
        Route::post('/', [AdminController::class, 'productsStore']);
        Route::put('/{id}', [AdminController::class, 'productsUpdate']);
        Route::delete('/{id}', [AdminController::class, 'productsDestroy']);
    });

    // --- Admin: Orders Management ---
    Route::prefix('orders')->group(function () {
        Route::get('/', [AdminController::class, 'ordersIndex']);
        Route::get('/{id}', [AdminController::class, 'ordersShow']);
        Route::put('/{id}/status', [AdminController::class, 'ordersUpdateStatus']);
        Route::delete('/{id}', [AdminController::class, 'ordersDestroy']);
    });

    // --- Admin: Users Management ---
    Route::prefix('users')->group(function () {
        Route::get('/', [AdminController::class, 'usersIndex']);
        Route::get('/{id}', [AdminController::class, 'usersShow']);
        // حظر وفك حظر
        Route::post('/{id}/ban', [AdminController::class, 'BanUser']);
        Route::post('/{id}/unban', [AdminController::class, 'UnbanUser']);
    });

});

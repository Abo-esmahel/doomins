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

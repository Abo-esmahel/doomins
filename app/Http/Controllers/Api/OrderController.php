<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    /**
     * عرض قائمة طلبات المستخدم
     */
    public function index()
    {
        $orders = Order::where('user_id', Auth::id())
            ->with(['cart.items.product.productable'])
            ->latest()
            ->paginate(10);

        return view('orders.index', compact('orders'));
    }

    /**
     * عرض تفاصيل طلب معين
     */
    public function show($id)
    {
        $order = Order::where('user_id', Auth::id())
            ->with(['cart.items.product.productable', 'user'])
            ->findOrFail($id);

        return view('orders.show', compact('order'));
    }
}

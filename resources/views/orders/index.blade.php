@extends('layouts.app')

@section('title', 'طلباتي')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-list-check"></i> طلباتي</h4>
                </div>

                <div class="card-body">
                    @if($orders->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>رقم الطلب</th>
                                        <th>التاريخ</th>
                                        <th>الإجمالي</th>
                                        <th>طريقة الدفع</th>
                                        <th>الحالة</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($orders as $order)
                                    <tr>
                                        <td>
                                            <strong>#{{ $order->order_number }}</strong><br>
                                            <small class="text-muted">{{ $order->cart->items->count() }} منتج</small>
                                        </td>
                                        <td>{{ $order->created_at->format('Y/m/d') }}</td>
                                        <td>{{ number_format($order->total, 2) }} ر.س</td>
                                        <td>
                                            @if($order->payment_method == 'wallet')
                                                <span class="badge bg-info">المحفظة</span>
                                            @elseif($order->payment_method == 'credit_card')
                                                <span class="badge bg-success">بطاقة ائتمان</span>
                                            @else
                                                <span class="badge bg-warning">نقدي</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($order->status == 'paid')
                                                <span class="badge bg-success">مدفوع</span>
                                            @elseif($order->status == 'pending')
                                                <span class="badge bg-warning">قيد الانتظار</span>
                                            @elseif($order->status == 'cancelled')
                                                <span class="badge bg-danger">ملغى</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('orders.show', $order->id) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> عرض
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- الترقيم -->
                        <div class="d-flex justify-content-center mt-4">
                            {{ $orders->links() }}
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="bi bi-receipt display-1 text-muted"></i>
                            <h4 class="mt-3">لا توجد طلبات</h4>
                            <p class="text-muted">لم تقم بإجراء أي طلبات حتى الآن.</p>
                            <a href="{{ route('domains.index') }}" class="btn btn-primary mt-2">
                                <i class="bi bi-bag-plus"></i> تصفح المنتجات
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'تفاصيل الطلب #' . $order->order_number)

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="bi bi-receipt"></i> تفاصيل الطلب #{{ $order->order_number }}
                        </h4>
                        <span class="badge bg-{{ $order->status == 'paid' ? 'success' : ($order->status == 'pending' ? 'warning' : 'secondary') }}">
                            {{ $order->status == 'paid' ? 'مدفوع' : ($order->status == 'pending' ? 'قيد الانتظار' : $order->status) }}
                        </span>
                    </div>
                </div>

                <div class="card-body">
                    <!-- معلومات الطلب -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6>معلومات الطلب</h6>
                            <table class="table table-sm">
                                <tr>
                                    <th>رقم الطلب:</th>
                                    <td>{{ $order->order_number }}</td>
                                </tr>
                                <tr>
                                    <th>تاريخ الطلب:</th>
                                    <td>{{ $order->created_at->format('Y/m/d H:i') }}</td>
                                </tr>
                                <tr>
                                    <th>طريقة الدفع:</th>
                                    <td>
                                        @if($order->payment_method == 'wallet')
                                            <span class="badge bg-info">المحفظة</span>
                                        @elseif($order->payment_method == 'credit_card')
                                            <span class="badge bg-success">بطاقة ائتمان</span>
                                        @else
                                            <span class="badge bg-warning">نقدي</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>حالة الدفع:</th>
                                    <td>
                                        @if($order->status == 'paid')
                                            <span class="badge bg-success">مدفوع</span>
                                            <small class="text-muted">في {{ $order->paid_at->format('Y/m/d H:i') }}</small>
                                        @else
                                            <span class="badge bg-warning">قيد الانتظار</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="col-md-6">
                            <h6>معلومات الفاتورة</h6>
                            <table class="table table-sm">
                                <tr>
                                    <th>الاسم:</th>
                                    <td>{{ $order->billing_info['name'] ?? $order->user->name }}</td>
                                </tr>
                                <tr>
                                    <th>البريد الإلكتروني:</th>
                                    <td>{{ $order->billing_info['email'] ?? $order->user->email }}</td>
                                </tr>
                                <tr>
                                    <th>رقم الهاتف:</th>
                                    <td>{{ $order->billing_info['phone'] ?? 'غير محدد' }}</td>
                                </tr>
                                <tr>
                                    <th>العنوان:</th>
                                    <td>{{ $order->billing_info['address'] ?? 'غير محدد' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- تفاصيل المنتجات -->
                    <h6>المنتجات المطلوبة</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>المنتج</th>
                                    <th>السعر</th>
                                    <th>الكمية</th>
                                    <th>المجموع</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->cart->items as $item)
                                <tr>
                                    <td>
                                        <strong>{{ $item->product_name }}</strong><br>
                                        <small class="text-muted">
                                            {{ $item->billing_period == 'yearly' ? 'اشتراك سنوي' : 'اشتراك شهري' }}
                                        </small>
                                    </td>
                                    <td>{{ number_format($item->price, 2) }} ر.س</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>{{ number_format($item->subtotal, 2) }} ر.س</td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-end">الإجمالي:</th>
                                    <th class="text-primary">{{ number_format($order->total, 2) }} ر.س</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- أزرار العمل -->
                    <div class="d-flex justify-content-between mt-4">
                        <div>
                            <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-right"></i> العودة إلى قائمة الطلبات
                            </a>

                            @if($order->status == 'paid' && $order->payment_method == 'wallet' && $order->created_at->diffInMinutes(now()) <= 30)
                            <form action="{{ route('orders.cancel-payment', $order->id) }}" method="POST" class="d-inline ms-2">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger" onclick="return confirm('هل تريد إلغاء الطلب واستعادة الرصيد؟')">
                                    <i class="bi bi-x-circle"></i> إلغاء واستعادة الرصيد
                                </button>
                            </form>
                            @endif
                        </div>

                        <div>
                            <button class="btn btn-outline-primary" onclick="window.print()">
                                <i class="bi bi-printer"></i> طباعة الفاتورة
                            </button>

                            @if($order->status == 'pending')
                            <a href="{{ route('payment.show', $order->id) }}" class="btn btn-primary ms-2">
                                <i class="bi bi-credit-card"></i> إتمام الدفع
                            </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

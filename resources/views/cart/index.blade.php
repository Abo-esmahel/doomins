@extends('layouts.app')

@section('title', 'عربة التسوق')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-cart"></i> عربة التسوق</h4>
                </div>

                <div class="card-body">
                    @if($cart && $cart->items->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>المنتج</th>
                                        <th>السعر</th>
                                        <th>الكمية</th>
                                        <th>المجموع</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($cart->items as $item)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="ms-3">
                                                    <h6 class="mb-0">{{ $item->product_name }}</h6>
                                                    <small class="text-muted">
                                                        @if($item->product && $item->product->productable)
                                                            @if($item->product->type == 'domain')
                                                                نوع: نطاق
                                                            @else
                                                                نوع: سيرفر
                                                            @endif
                                                        @endif
                                                    </small>
                                                    <br>
                                                    <small class="text-muted">
                                                        فاتورة: {{ $item->billing_period == 'yearly' ? 'سنوية' : 'شهرية' }}
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ number_format($item->price, 2) }} ر.س</td>
                                        <td>
                                            <form action="{{ route('cart.update', $item->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('PUT')
                                                <input type="number" name="quantity" value="{{ $item->quantity }}" min="1" max="10" class="form-control form-control-sm" style="width: 70px;" onchange="this.form.submit()">
                                            </form>
                                        </td>
                                        <td>{{ number_format($item->subtotal, 2) }} ر.س</td>
                                        <td>
                                            <form action="{{ route('cart.remove', $item->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="bi bi-cart-x display-1 text-muted"></i>
                            <h4 class="mt-3">عربة التسوق فارغة</h4>
                            <p class="text-muted">لم تقم بإضافة أي منتجات إلى سلة التسوق بعد.</p>
                            <a href="{{ route('domains.index') }}" class="btn btn-primary mt-2">
                                <i class="bi bi-bag-plus"></i> تصفح المنتجات
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if($cart && $cart->items->count() > 0)
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">ملخص الطلب</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>عدد المنتجات:</span>
                        <span>{{ $cart->items->count() }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>الإجمالي:</span>
                        <span>{{ number_format($cart->total, 2) }} ر.س</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3 fw-bold">
                        <span>المبلغ الإجمالي:</span>
                        <span class="text-primary">{{ number_format($cart->total, 2) }} ر.س</span>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="{{ route('checkout.show') }}" class="btn btn-primary btn-lg">
                            <i class="bi bi-credit-card"></i> إتمام الشراء
                        </a>

                        @if(auth()->user()->balance >= $cart->total)
                        <form action="{{ route('checkout.quick-pay') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="cart_id" value="{{ $cart->id }}">
                            <button type="submit" class="btn btn-success btn-lg w-100 mt-2" id="quickPayBtn">
                                <i class="bi bi-lightning-charge"></i> دفع سريع بالمحفظة
                            </button>
                        </form>
                        <div class="text-center mt-2">
                            <small class="text-muted">رصيدك الحالي: {{ number_format(auth()->user()->balance, 2) }} ر.س</small>
                        </div>
                        @else
                        <button class="btn btn-secondary btn-lg w-100 mt-2" disabled>
                            رصيدك غير كافي
                        </button>
                        <div class="text-center mt-2">
                            <small class="text-danger">نقص في الرصيد: {{ number_format($cart->total - auth()->user()->balance, 2) }} ر.س</small>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <h6>التحقق من المخزون</h6>
                    <p class="text-muted small">تأكد من توفر جميع المنتجات قبل الشراء</p>
                    <button class="btn btn-outline-primary w-100" onclick="verifyStock()">
                        <i class="bi bi-check-circle"></i> التحقق من التوفر
                    </button>
                    <div id="stockResult" class="mt-2"></div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
function verifyStock() {
    $('#stockResult').html('<div class="alert alert-info">جاري التحقق...</div>');

    $.get('{{ route("checkout.verify-stock") }}', function(response) {
        if (response.available) {
            $('#stockResult').html('<div class="alert alert-success">جميع المنتجات متوفرة</div>');
        } else {
            let html = '<div class="alert alert-danger">';
            html += '<strong>بعض المنتجات غير متوفرة:</strong><ul class="mb-0">';
            response.items.forEach(item => {
                html += `<li>${item.name} - ${item.reason}</li>`;
            });
            html += '</ul></div>';
            $('#stockResult').html(html);
        }
    }).fail(function() {
        $('#stockResult').html('<div class="alert alert-danger">حدث خطأ أثناء التحقق</div>');
    });
}

// تأكيد الدفع السريع
document.getElementById('quickPayBtn')?.addEventListener('click', function(e) {
    if (!confirm('هل تريد تأكيد الدفع الفوري باستخدام رصيد محفظتك؟')) {
        e.preventDefault();
    }
});
</script>
@endpush

@extends('layouts.app')

@section('title', 'إتمام الشراء')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-credit-card"></i> إتمام عملية الشراء</h4>
                </div>

                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> يرجى مراجعة طلبك وإدخال معلومات الدفع
                    </div>

                    <!-- عرض المنتجات -->
                    <h5 class="mb-3">المنتجات المختارة</h5>
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
                                @foreach($cart->items as $item)
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
                                    <th class="text-primary">{{ number_format($total, 2) }} ر.س</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- معلومات المستخدم -->
                    <h5 class="mb-3 mt-4">معلومات الفاتورة</h5>
                    <form id="checkoutForm" action="{{ route('checkout.process') }}" method="POST">
                        @csrf

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">الاسم بالكامل</label>
                                <input type="text" class="form-control" id="name" name="billing_info[name]" value="{{ $user->name }}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">البريد الإلكتروني</label>
                                <input type="email" class="form-control" id="email" name="billing_info[email]" value="{{ $user->email }}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">رقم الهاتف</label>
                                <input type="tel" class="form-control" id="phone" name="billing_info[phone]" required>
                            </div>
                            <div class="col-md-6">
                                <label for="address" class="form-label">العنوان</label>
                                <input type="text" class="form-control" id="address" name="billing_info[address]" required>
                            </div>
                        </div>

                        <!-- طريقة الدفع -->
                        <h5 class="mb-3 mt-4">طريقة الدفع</h5>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="form-check card-radio">
                                    <input class="form-check-input" type="radio" name="payment_method" id="wallet" value="wallet" checked>
                                    <label class="form-check-label w-100" for="wallet">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <i class="bi bi-wallet2 display-6 text-primary"></i>
                                                <h6 class="mt-2">المحفظة</h6>
                                                <small class="text-muted">الرصيد: {{ number_format($user->balance, 2) }} ر.س</small>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-check card-radio">
                                    <input class="form-check-input" type="radio" name="payment_method" id="credit_card" value="credit_card">
                                    <label class="form-check-label w-100" for="credit_card">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <i class="bi bi-credit-card display-6 text-success"></i>
                                                <h6 class="mt-2">بطاقة ائتمان</h6>
                                                <small class="text-muted">مدى / فيزا / ماستركارد</small>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-check card-radio">
                                    <input class="form-check-input" type="radio" name="payment_method" id="cash" value="cash">
                                    <label class="form-check-label w-100" for="cash">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <i class="bi bi-cash display-6 text-warning"></i>
                                                <h6 class="mt-2">الدفع عند الاستلام</h6>
                                                <small class="text-muted">للمنتجات المادية فقط</small>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- خيار الدفع الجزئي -->
                        <div class="card mt-3" id="partialPaymentCard" style="display: none;">
                            <div class="card-body">
                                <h6>الدفع الجزئي</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="wallet_amount" class="form-label">المبلغ من المحفظة</label>
                                        <input type="number" class="form-control" id="wallet_amount" name="wallet_amount" min="0" max="{{ min($user->balance, $total) }}" value="{{ min($user->balance, $total) }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="remaining_method" class="form-label">طريقة دفع الباقي</label>
                                        <select class="form-select" id="remaining_method" name="remaining_method">
                                            <option value="credit_card">بطاقة ائتمان</option>
                                            <option value="cash">الدفع عند الاستلام</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- الشروط -->
                        <div class="form-check mt-4">
                                            <input class="form-check-input" type="checkbox" id="terms" required>
                                            <label class="form-check-label" for="terms">
                                                أوافق على <a href="#">الشروط والأحكام</a> وسياسة الخصوصية
                                            </label>
                                        </div>

                                        <div class="d-grid gap-2 mt-4">
                                            <button type="submit" class="btn btn-primary btn-lg">
                                                <i class="bi bi-check-circle"></i> تأكيد الطلب والدفع
                                            </button>
                                            <a href="{{ route('cart.index') }}" class="btn btn-outline-secondary">
                                                <i class="bi bi-arrow-right"></i> العودة إلى السلة
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
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
                                        <span>الإجمالي قبل الضريبة:</span>
                                        <span>{{ number_format($total, 2) }} ر.س</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>الضريبة:</span>
                                        <span>0.00 ر.س</span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between mb-3 fw-bold fs-5">
                                        <span>المبلغ الإجمالي:</span>
                                        <span class="text-primary">{{ number_format($total, 2) }} ر.س</span>
                                    </div>

                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        <small>بعد تأكيد الطلب، سيتم خصم المبلغ من رصيدك أو تحويلك لبوابة الدفع حسب الطريقة المختارة.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="card mt-3">
                                <div class="card-body">
                                    <h6>معلومات الدعم</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="bi bi-telephone text-primary"></i> الدعم الفني: 8001234567</li>
                                        <li><i class="bi bi-envelope text-primary"></i> البريد: support@domain.com</li>
                                        <li><i class="bi bi-clock text-primary"></i> ساعات العمل: 24/7</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endsection

                @push('styles')
                <style>
                .card-radio .form-check-input {
                    position: absolute;
                    opacity: 0;
                }
                .card-radio .form-check-input:checked + .form-check-label .card {
                    border-color: #0d6efd;
                    background-color: rgba(13, 110, 253, 0.05);
                }
                .card-radio .form-check-label .card {
                    cursor: pointer;
                    transition: all 0.3s;
                }
                .card-radio .form-check-label .card:hover {
                    border-color: #0d6efd;
                }
                </style>
                @endpush

                @push('scripts')
                <script>
                $(document).ready(function() {
                    // التحكم في خيار الدفع الجزئي
                    $('input[name="payment_method"]').change(function() {
                        if ($(this).val() === 'wallet' && {{ $user->balance }} < {{ $total }}) {
                            $('#partialPaymentCard').show();
                        } else {
                            $('#partialPaymentCard').hide();
                        }
                    });

                    // تحديث أقصى مبلغ للمحفظة
                    $('#wallet_amount').attr('max', {{ min($user->balance, $total) }});
                    // تأكيد قبل الإرسال
                    $('#checkoutForm').submit(function(e) {
                        const paymentMethod = $('input[name="payment_method"]:checked').val();
                        const terms = $('#terms').is(':checked');

                        if (!terms) {
                            e.preventDefault();
                            alert('يجب الموافقة على الشروط والأحكام');
                            return false;
                        }

                        if (paymentMethod === 'wallet') {
                            if ({{ $user->balance }} < {{ $total }}) {
                                const walletAmount = $('#wallet_amount').val() || 0;
                                if (walletAmount <= 0) {
                                    e.preventDefault();
                                    alert('يرجى إدخال مبلغ من المحفظة');
                                    return false;
                                }
                            } else {
                                if (!confirm('هل تريد تأكيد الدفع باستخدام رصيد محفظتك؟')) {
                                    e.preventDefault();
                                    return false;
                                }
                            }
                        }

                        return true;
                    });
                });
                </script>
                @endpush

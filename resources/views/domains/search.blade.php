@extends('layouts.app')

@section('title', 'نتائج البحث: ' . $searchTerm)

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <!-- نتائج البحث -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="bi bi-search"></i>
                        نتائج البحث عن: "{{ $searchTerm }}"
                    </h4>
                </div>
                <div class="card-body">
                    @if(count($domains) > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>النطاق</th>
                                    <th>الحالة</th>
                                    <th>السعر الشهري</th>
                                    <th>السعر السنوي</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($domains as $domain)
                                <tr>
                                    <td>
                                        <strong>{{ $domain->name }}.{{ $domain->tld }}</strong><br>
                                        <small class="text-muted">{{ $domain->tld }}</small>
                                    </td>
                                    <td>
                                        @if($domain->available)
                                        <span class="badge bg-success">متاح</span>
                                        @else
                                        <span class="badge bg-danger">محجوز</span>
                                        @endif
                                    </td>
                                    <td>{{ number_format($domain->price_monthly, 2) }} ر.س</td>
                                    <td>{{ number_format($domain->price_yearly, 2) }} ر.س</td>
                                    <td>
                                        @if($domain->available && $domain->product)
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('domains.show', $domain->id) }}" class="btn btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <form action="{{ route('cart.add', $domain->product->id) }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="quantity" value="1">
                                                <input type="hidden" name="billing_period" value="monthly">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-cart-plus"></i> أضف للسلة
                                                </button>
                                            </form>
                                        </div>
                                        @else
                                        <button class="btn btn-sm btn-secondary" disabled>غير متاح</button>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-4">
                        <i class="bi bi-search display-1 text-muted"></i>
                        <h4 class="mt-3">لم يتم العثور على نطاقات مطابقة</h4>
                        <p class="text-muted">لا توجد نطاقات تطابق بحثك.</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- اقتراحات -->
            @if(count($suggestions) > 0)
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-lightbulb"></i>
                        اقتراحات نطاقات بديلة
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($suggestions as $suggestion)
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h6 class="text-primary">{{ $suggestion['full_name'] }}</h6>
                                    <p class="text-muted small">نطاق {{ $suggestion['tld'] }}</p>

                                    <div class="my-2">
                                        <h5 class="text-success">{{ number_format($suggestion['price_monthly'], 2) }} ر.س</h5>
                                        <small>شهرياً</small>
                                    </div>

                                    @if($suggestion['available'])
                                    <div class="d-grid">
                                        <a href="{{ route('domains.create') }}?name={{ explode('.', $suggestion['full_name'])[0] }}&tld={{ $suggestion['tld'] }}"
                                           class="btn btn-success btn-sm">
                                            <i class="bi bi-cart-plus"></i> احجز الآن
                                        </a>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

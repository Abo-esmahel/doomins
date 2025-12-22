@extends('layouts.app')

@section('title', 'شراء النطاقات')

@section('content')
<div class="container">
    <!-- شريط البحث -->
    <div class="card mb-4">
        <div class="card-body">
            <h4 class="mb-3">ابحث عن نطاقك المثالي</h4>
            <form action="{{ route('domains.search') }}" method="GET">
                <div class="row g-3">
                    <div class="col-md-8">
                        <div class="input-group input-group-lg">
                            <input type="text"
                                   class="form-control"
                                   name="domain"
                                   placeholder="أدخل اسم النطاق المطلوب"
                                   required>
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-search"></i> بحث
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select form-select-lg" name="tlds[]" multiple>
                            <option value=".com" selected>.com</option>
                            <option value=".net" selected>.net</option>
                            <option value=".org" selected>.org</option>
                            <option value=".sa" selected>.sa</option>
                            <option value=".ae">.ae</option>
                            <option value=".edu">.edu</option>
                            <option value=".info">.info</option>
                            <option value=".biz">.biz</option>
                        </select>
                        <small class="text-muted">اضغط Ctrl لاختيار أكثر من نطاق</small>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- الدومينات المتاحة -->
    <div class="row">
        <div class="col-md-12">
            <h4 class="mb-3">الدومينات المتاحة</h4>

            @if($domains->count() > 0)
            <div class="row">
                @foreach($domains as $domain)
                <div class="col-md-3 mb-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <h5 class="card-title text-primary">{{ $domain->name }}.{{ $domain->tld }}</h5>
                            <p class="text-muted small">نطاق {{ $domain->tld }}</p>

                            <div class="my-3">
                                <h4 class="text-success">{{ number_format($domain->price_monthly, 2) }} ر.س</h4>
                                <small>شهرياً</small>
                            </div>

                            <div class="mb-3">
                                <del class="text-muted">{{ number_format($domain->price_yearly, 2) }} ر.س</del>
                                <div class="text-success fw-bold">{{ number_format($domain->price_yearly * 0.9, 2) }} ر.س</div>
                                <small>سنوياً (وفر 10%)</small>
                            </div>

                            <div class="d-grid gap-2">
                                <a href="{{ route('domains.show', $domain->id) }}" class="btn btn-outline-primary">
                                    <i class="bi bi-eye"></i> التفاصيل
                                </a>

                                @if($domain->product)
                                <form action="{{ route('cart.add', $domain->product->id) }}" method="POST" class="d-inline">

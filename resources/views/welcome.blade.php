@extends('layouts.app')

@section('title', 'نظام بيع الدومينات والسيرفرات')

@section('content')
<div class="container">
    <!-- الهيرو -->
    <div class="hero-section text-center py-5 mb-5">
        <h1 class="display-4 fw-bold text-primary mb-3">نظام بيع الدومينات والسيرفرات</h1>
        <p class="lead mb-4">احصل على أفضل النطاقات والسيرفرات بأسعار تنافسية وجودة عالية</p>
        <div class="d-flex justify-content-center gap-3">
            <a href="{{ route('domains.index') }}" class="btn btn-primary btn-lg">
                <i class="bi bi-globe"></i> تصفح النطاقات
            </a>
            <a href="{{ route('servers.index') }}" class="btn btn-success btn-lg">
                <i class="bi bi-server"></i> تصفح السيرفرات
            </a>
        </div>
    </div>

    <!-- المميزات -->
    <div class="row mb-5">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center p-4">
                    <i class="bi bi-lightning-charge display-4 text-primary mb-3"></i>
                    <h4 class="card-title">سرعة فائقة</h4>
                    <p class="card-text">سيرفرات عالية السرعة لضمان أداء مثالي لموقعك.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center p-4">
                    <i class="bi bi-shield-check display-4 text-success mb-3"></i>
                    <h4 class="card-title">أمان مضمون</h4>
                    <p class="card-text">أنظمة حماية متقدمة لحماية بياناتك وموقعك.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center p-4">
                    <i class="bi bi-headset display-4 text-warning mb-3"></i>
                    <h4 class="card-title">دعم فني 24/7</h4>
                    <p class="card-text">فريق دعم فني متاح على مدار الساعة لخدمتك.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- آخر النطاقات -->
    <div class="row mb-5">
        <div class="col-md-12">
            <h3 class="mb-4">أحدث النطاقات المتاحة</h3>
            <div class="row">
                @php
                    // سحب بعض الدومينات للعرض (في نظام حقيقي، سيكون هناك استعلام لقاعدة البيانات)
                    $sampleDomains = [
                        ['name' => 'example', 'tld' => '.com', 'price' => 50],
                        ['name' => 'business', 'tld' => '.net', 'price' => 45],
                        ['name' => 'store', 'tld' => '.sa', 'price' => 60],
                        ['name' => 'tech', 'tld' => '.org', 'price' => 40],
                    ];
                @endphp

                @foreach($sampleDomains as $domain)
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <h5 class="text-primary">{{ $domain['name'] }}{{ $domain['tld'] }}</h5>
                            <p class="text-muted">نطاق {{ $domain['tld'] }}</p>
                            <h4 class="text-success">{{ $domain['price'] }} ر.س</h4>
                            <small>شهرياً</small>
                            <div class="d-grid mt-3">
                                <a href="{{ route('domains.create') }}" class="btn btn-outline-primary">
                                    احجز الآن
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="text-center mt-3">
                <a href="{{ route('domains.index') }}" class="btn btn-link">
                    عرض جميع النطاقات <i class="bi bi-arrow-left"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- آخر السيرفرات -->
    <div class="row">
        <div class="col-md-12">
            <h3 class="mb-4">أشهر السيرفرات</h3>
            <div class="row">
                @php
                    $sampleServers = [
                        ['name' => 'سيرفر VPS أساسي', 'specs' => '2CPU - 4GB RAM', 'price' => 100],
                        ['name' => 'سيرفر VPS متقدم', 'specs' => '4CPU - 8GB RAM', 'price' => 180],
                        ['name' => 'سيرفر Cloud', 'specs' => '8CPU - 16GB RAM', 'price' => 300],
                        ['name' => 'سيرفر مخصص', 'specs' => '16CPU - 32GB RAM', 'price' => 550],
                    ];
                @endphp

                @foreach($sampleServers as $server)
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <h5 class="text-primary">{{ $server['name'] }}</h5>
                            <p class="text-muted">{{ $server['specs'] }}</p>
                            <h4 class="text-success">{{ $server['price'] }} ر.س</h4>
                            <small>شهرياً</small>
                            <div class="d-grid mt-3">
                                <a href="{{ route('servers.index') }}" class="btn btn-outline-success">
                                    عرض التفاصيل
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="text-center mt-3">
                <a href="{{ route('servers.index') }}" class="btn btn-link">
                    عرض جميع السيرفرات <i class="bi bi-arrow-left"></i>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

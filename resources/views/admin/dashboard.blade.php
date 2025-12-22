@extends('layouts.app')

@section('title', 'لوحة تحكم الإدارة')

@section('content')
<div class="container-fluid">
    <div class="row">
        <!-- الشريط الجانبي -->
        <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link text-white {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
                           href="{{ route('admin.dashboard') }}">
                            <i class="bi bi-speedometer2"></i> لوحة التحكم
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white {{ request()->routeIs('admin.domains.*') ? 'active' : '' }}"
                           href="{{ route('admin.domains.index') }}">
                            <i class="bi bi-globe"></i> إدارة الدومينات
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white {{ request()->routeIs('admin.servers.*') ? 'active' : '' }}"
                           href="{{ route('admin.servers.index') }}">
                            <i class="bi bi-server"></i> إدارة السيرفرات
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white {{ request()->routeIs('admin.products.*') ? 'active' : '' }}"
                           href="{{ route('admin.products.index') }}">
                            <i class="bi bi-box"></i> إدارة المنتجات
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}"
                           href="{{ route('admin.orders.index') }}">
                            <i class="bi bi-receipt"></i> إدارة الطلبات
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
                           href="{{ route('admin.users.index') }}">
                            <i class="bi bi-people"></i> إدارة المستخدمين
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white {{ request()->routeIs('admin.statistics') ? 'active' : '' }}"
                           href="{{ route('admin.statistics') }}">
                            <i class="bi bi-bar-chart"></i> الإحصائيات
                        </a>
                    </li>
                </ul>

                <hr class="bg-light">

                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="{{ route('dashboard') }}">
                            <i class="bi bi-person-circle"></i> لوحة المستخدم
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="{{ route('home') }}">
                            <i class="bi bi-house-door"></i> الصفحة الرئيسية
                        </a>
                    </li>
                    <li class="nav-item">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="nav-link text-white btn btn-link" style="text-align: right">
                                <i class="bi bi-box-arrow-right"></i> تسجيل الخروج
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- المحتوى الرئيسي -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="bi bi-speedometer2"></i> لوحة تحكم الإدارة
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <span class="badge bg-danger p-2">
                            <i class="bi bi-shield-check"></i> مدير النظام
                        </span>
                    </div>
                    <span class="text-muted">
                        {{ auth()->user()->name }} | {{ auth()->user()->email }}
                    </span>
                </div>
            </div>

            <!-- الإشعارات -->
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <!-- الإحصائيات -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        إجمالي المستخدمين
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        {{ number_format($stats['total_users']) }}
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-people fa-2x text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        إجمالي الطلبات
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        {{ number_format($stats['total_orders']) }}
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-receipt fa-2x text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        إيرادات اليوم
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        {{ number_format($stats['revenue_today'], 2) }} ر.س
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-currency-exchange fa-2x text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        طلبات قيد الانتظار
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        {{ number_format($stats['pending_orders']) }}
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-clock fa-2x text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- المزيد من الإحصائيات -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <i class="bi bi-globe display-6 mb-2"></i>
                            <h5>الدومينات</h5>
                            <h3>{{ number_format($stats['total_domains']) }}</h3>
                            <a href="{{ route('admin.domains.index') }}" class="btn btn-light btn-sm">
                                <i class="bi bi-eye"></i> عرض الكل
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <i class="bi bi-server display-6 mb-2"></i>
                            <h5>السيرفرات</h5>
                            <h3>{{ number_format($stats['total_servers']) }}</h3>
                            <a href="{{ route('admin.servers.index') }}" class="btn btn-light btn-sm">
                                <i class="bi bi-eye"></i> عرض الكل
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <i class="bi bi-box display-6 mb-2"></i>
                            <h5>المنتجات</h5>
                            <h3>{{ number_format($stats['total_products']) }}</h3>
                            <a href="{{ route('admin.products.index') }}" class="btn btn-light btn-sm">
                                <i class="bi bi-eye"></i> عرض الكل
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <i class="bi bi-cash-coin display-6 mb-2"></i>
                            <h5>إيرادات الشهر</h5>
                            <h3>{{ number_format($stats['revenue_month'], 2) }} ر.س</h3>
                            <a href="{{ route('admin.statistics') }}" class="btn btn-light btn-sm">
                                <i class="bi bi-bar-chart"></i> التقارير
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- آخر الطلبات -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-receipt"></i> آخر الطلبات
                        </h5>
                        <a href="{{ route('admin.orders.index') }}" class="btn btn-light btn-sm">
                            عرض الكل <i class="bi bi-arrow-left"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if($recent_orders->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>رقم الطلب</th>
                                        <th>المستخدم</th>
                                        <th>الإجمالي</th>
                                        <th>الحالة</th>
                                        <th>التاريخ</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recent_orders as $order)
                                    <tr>
                                        <td>
                                            <strong>#{{ $order->order_number }}</strong>
                                        </td>
                                        <td>
                                            {{ $order->user->name }}<br>
                                            <small class="text-muted">{{ $order->user->email }}</small>
                                        </td>
                                        <td>
                                            <span class="fw-bold">{{ number_format($order->total, 2) }} ر.س</span>
                                        </td>
                                        <td>
                                            @if($order->status == 'paid')
                                                <span class="badge bg-success">مدفوع</span>
                                            @elseif($order->status == 'pending')
                                                <span class="badge bg-warning">قيد الانتظار</span>
                                            @elseif($order->status == 'processing')
                                                <span class="badge bg-info">قيد المعالجة</span>
                                            @elseif($order->status == 'completed')
                                                <span class="badge bg-primary">مكتمل</span>
                                            @elseif($order->status == 'cancelled')
                                                <span class="badge bg-danger">ملغى</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $order->created_at->format('Y/m/d') }}<br>
                                            <small class="text-muted">{{ $order->created_at->format('H:i') }}</small>
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.orders.show', $order->id) }}"
                                               class="btn btn-sm btn-outline-primary"
                                               title="عرض التفاصيل">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-receipt display-1 text-muted"></i>
                            <h5 class="mt-3">لا توجد طلبات حالياً</h5>
                            <p class="text-muted">لم يتم إنشاء أي طلبات حتى الآن.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- آخر المستخدمين -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-people"></i> آخر المستخدمين المسجلين
                        </h5>
                        <a href="{{ route('admin.users.index') }}" class="btn btn-light btn-sm">
                            عرض الكل <i class="bi bi-arrow-left"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if($recent_users->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>الاسم</th>
                                        <th>البريد الإلكتروني</th>
                                        <th>الرصيد</th>
                                        <th>نوع الحساب</th>
                                        <th>تاريخ التسجيل</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recent_users as $user)
                                    <tr>
                                        <td>
                                            {{ $user->name }}
                                        </td>
                                        <td>
                                            {{ $user->email }}
                                        </td>
                                        <td>
                                            <span class="fw-bold {{ $user->balance > 0 ? 'text-success' : 'text-muted' }}">
                                                {{ number_format($user->balance, 2) }} ر.س
                                            </span>
                                        </td>
                                        <td>
                                            @if($user->is_admin)
                                                <span class="badge bg-danger">مدير</span>
                                            @else
                                                <span class="badge bg-secondary">مستخدم</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $user->created_at->format('Y/m/d') }}<br>
                                            <small class="text-muted">{{ $user->created_at->format('H:i') }}</small>
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.users.show', $user->id) }}"
                                               class="btn btn-sm btn-outline-info"
                                               title="عرض التفاصيل">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-people display-1 text-muted"></i>
                            <h5 class="mt-3">لا توجد مستخدمين</h5>
                            <p class="text-muted">لم يسجل أي مستخدم حتى الآن.</p>
                        </div>
                    @endif
                </div>
            </div>
        </main>
    </div>
</div>

<style>
#sidebar {
    min-height: calc(100vh - 73px);
    box-shadow: 3px 0 10px rgba(0,0,0,.1);
}
#sidebar .nav-link {
    color: #adb5bd;
    padding: 12px 15px;
    margin: 2px 0;
    border-radius: 5px;
    transition: all 0.3s;
    font-size: 14px;
}
#sidebar .nav-link:hover {
    color: #fff;
    background-color: rgba(255,255,255,.1);
}
#sidebar .nav-link.active {
    color: #fff;
    background-color: #0d6efd;
}
.card {
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.border-left-primary {
    border-left: 4px solid #0d6efd !important;
}
.border-left-success {
    border-left: 4px solid #198754 !important;
}
.border-left-info {
    border-left: 4px solid #0dcaf0 !important;
}
.border-left-warning {
    border-left: 4px solid #ffc107 !important;
}
</style>

@push('scripts')
<script>
    // دالة لتحديث الإحصائيات تلقائياً
    function refreshStats() {
        fetch('/admin/statistics/live')
            .then(response => response.json())
            .then(data => {
                // تحديث الإحصائيات في الصفحة
                // يمكنك إضافة كود تحديث هنا
            })
            .catch(error => console.error('Error:', error));
    }

    // تحديث الإحصائيات كل دقيقة
    setInterval(refreshStats, 60000);

    // تأكيد قبل حذف العناصر
    document.addEventListener('DOMContentLoaded', function() {
        const deleteButtons = document.querySelectorAll('.btn-delete');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('هل أنت متأكد من الحذف؟ لا يمكن التراجع عن هذا الإجراء.')) {
                    e.preventDefault();
                }
            });
        });
    });
</script>
@endpush
@endsection

<!-- في resources/views/layouts/app.blade.php -->
<ul class="navbar-nav">
    @auth
        @if(auth()->user()->is_admin)
            <li class="nav-item">
                <a class="nav-link text-danger" href="{{ route('admin.dashboard') }}">
                    <i class="bi bi-shield-lock"></i> لوحة الإدارة
                </a>
            </li>
        @endif
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                <i class="bi bi-person-circle"></i> {{ Auth::user()->name }}
            </a>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="{{ route('dashboard') }}">لوحة التحكم</a></li>
                <li><a class="dropdown-item" href="{{ route('orders.index') }}">طلباتي</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item">تسجيل الخروج</button>
                    </form>
                </li>
            </ul>
        </li>
    @else
        <li class="nav-item">
            <a class="nav-link" href="{{ route('login') }}">تسجيل الدخول</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('register') }}">حساب جديد</a>
        </li>
    @endauth
</ul>

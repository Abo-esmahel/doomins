@extends('adminlte::page')

@section('title', 'إدارة المستخدمين')

@section('content_header')
    <h1>إدارة المستخدمين</h1>
@stop

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">إضافة مستخدم جديد</a>
</div>

<form method="GET" class="mb-3 row g-2">
    <div class="col-auto"><input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="بحث بالاسم أو الإيميل"></div>
    <div class="col-auto">
        <select name="role" class="form-select">
            <option value="">كل الأدوار</option>
            <option value="admin" {{ request('role')=='admin' ? 'selected' : '' }}>Admin</option>
            <option value="doctor" {{ request('role')=='doctor' ? 'selected' : '' }}>Doctor</option>
            <option value="patient" {{ request('role')=='patient' ? 'selected' : '' }}>Patient</option>
            <option value="pharmacist" {{ request('role')=='pharmacist' ? 'selected' : '' }}>Pharmacist</option>
            <option value="lab" {{ request('role')=='lab' ? 'selected' : '' }}>Lab</option>
        </select>
    </div>
    <div class="col-auto">
        <select name="is_active" class="form-select">
            <option value="">كل الحالات</option>
            <option value="1" {{ request('is_active')==='1' ? 'selected':'' }}>نشط</option>
            <option value="0" {{ request('is_active')==='0' ? 'selected':'' }}>غير نشط</option>
        </select>
    </div>
    <div class="col-auto"><button class="btn btn-secondary">فلتر</button></div>
</form>

@include('partials.alerts')

<table class="table table-bordered table-striped">
    <thead>
        <tr>
            <th>#</th>
            <th>الاسم</th>
            <th>البريد</th>
            <th>الرول</th>
            <th>الحالة</th>
            <th>انشئ بتاريخ</th>
            <th>إجراءات</th>
        </tr>
    </thead>
    <tbody>
        @foreach($users as $u)
            <tr>
                <td>{{ $u->id }}</td>
                <td>{{ $u->name }}</td>
                <td>{{ $u->email }}</td>
                <td>{{ $u->role }}</td>
                <td>{{ $u->is_active ? 'نشط' : 'غير نشط' }}<br>{{ $u->status ?? '' }}</td>
                <td>{{ $u->created_at->format('Y-m-d') }}</td>
                <td>
                    <a href="{{ route('admin.users.show', $u->id) }}" class="btn btn-sm btn-info">عرض</a>
                    <a href="{{ route('admin.users.edit', $u->id) }}" class="btn btn-sm btn-warning">تعديل</a>

                    <form action="{{ route('admin.users.destroy', $u->id) }}" method="POST" style="display:inline;">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-danger" onclick="return confirm('هل تريد الحذف؟')">حذف</button>
                    </form>

                    <form action="{{ route('admin.users.toggleActive', $u->id) }}" method="POST" style="display:inline;">
                        @csrf
                        <button class="btn btn-sm btn-secondary">{{ $u->is_active ? 'تعطيل' : 'تفعيل' }}</button>
                    </form>

                    @if(($u->status ?? '') !== 'banned')
                        <form action="{{ route('admin.users.ban', $u->id) }}" method="POST" style="display:inline;">
                            @csrf
                            <button class="btn btn-sm btn-dark">حظر</button>
                        </form>
                    @else
                        <form action="{{ route('admin.users.unban', $u->id) }}" method="POST" style="display:inline;">
                            @csrf
                            <button class="btn btn-sm btn-success">إلغاء الحظر</button>
                        </form>
                    @endif

                </td>
            </tr>
        @endforeach
    </tbody>
</table>

{{ $users->links() }}

@stop

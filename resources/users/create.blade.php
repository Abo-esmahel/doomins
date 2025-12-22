@extends('adminlte::page')

@section('title','إنشاء مستخدم')

@section('content_header')
    <h1>إنشاء مستخدم جديد</h1>
@stop

@section('content')
@include('partials.alerts')

<form method="POST" action="{{ route('admin.users.store') }}">
    @csrf
    <div class="mb-3">
        <label>الاسم</label>
        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
    </div>
    <div class="mb-3">
        <label>الإيميل</label>
        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
    </div>
    <div class="mb-3">
        <label>كلمة المرور</label>
        <input type="password" name="password" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>تأكيد كلمة المرور</label>
        <input type="password" name="password_confirmation" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>الرول</label>
        <select name="role" class="form-select" required>
            <option value="patient">Patient</option>
            <option value="doctor">Doctor</option>
            <option value="pharmacist">Pharmacist</option>
            <option value="lab">Lab</option>
            <option value="admin">Admin</option>
        </select>
    </div>
    <div class="form-check mb-3">
        <input type="checkbox" name="is_active" class="form-check-input" id="active" checked>
        <label for="active" class="form-check-label">نشط</label>
    </div>
    <button class="btn btn-primary">حفظ</button>
</form>
@stop

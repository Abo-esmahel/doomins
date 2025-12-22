@extends('adminlte::page')

@section('title','تعديل مستخدم')

@section('content_header')
    <h1>تعديل المستخدم</h1>
@stop

@section('content')
@include('partials.alerts')

<form method="POST" action="{{ route('admin.users.update', $user->id) }}">
    @csrf
    @method('PUT')

    <div class="mb-3">
        <label>الاسم</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
    </div>

    <div class="mb-3">
        <label>الإيميل</label>
        <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
    </div>

    <div class="mb-3">
        <label>كلمة المرور (اتركها فارغة إن لم تريد تغييرها)</label>
        <input type="password" name="password" class="form-control">
    </div>

    <div class="mb-3">
        <label>تأكيد كلمة المرور</label>
        <input type="password" name="password_confirmation" class="form-control">
    </div>

    <div class="mb-3">
        <label>الرول</label>
        <select name="role" class="form-select" required>
            <option value="patient" {{ $user->role=='patient' ? 'selected':'' }}>Patient</option>
            <option value="doctor" {{ $user->role=='doctor' ? 'selected':'' }}>Doctor</option>
            <option value="pharmacist" {{ $user->role=='pharmacist' ? 'selected':'' }}>Pharmacist</option>
            <option value="lab" {{ $user->role=='lab' ? 'selected':'' }}>Lab</option>
            <option value="admin" {{ $user->role=='admin' ? 'selected':'' }}>Admin</option>
        </select>
    </div>

    <div class="form-check mb-3">
        <input type="checkbox" name="is_active" class="form-check-input" id="active" {{ $user->is_active ? 'checked' : '' }}>
        <label for="active" class="form-check-label">نشط</label>
    </div>

    <button class="btn btn-success">تحديث</button>
</form>
@stop

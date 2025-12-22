@extends('adminlte::page')

@section('title','عرض المستخدم')

@section('content_header')
    <h1>عرض المستخدم</h1>
@stop

@section('content')
<div class="card">
    <div class="card-body">
        <h3>{{ $user->name }}</h3>
        <p><strong>الإيميل:</strong> {{ $user->email }}</p>
        <p><strong>الرول:</strong> {{ $user->role }}</p>
        <p><strong>الحالة:</strong> {{ $user->is_active ? 'نشط' : 'غير نشط' }}</p>
        <p><strong>التاريخ:</strong> {{ $user->created_at }}</p>
        <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-warning">تعديل</a>
        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">عودة</a>
    </div>
</div>
@stop

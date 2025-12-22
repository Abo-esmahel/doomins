<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users'],
            'password' => ['required', 'confirmed'],
            'role' => ['nullable', 'in:user,admin'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'الاسم مطلوب',
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.email' => 'البريد الإلكتروني غير صالح',
            'email.unique' => 'البريد الإلكتروني مستخدم بالفعل',
            'phone.unique' => 'رقم الهاتف مستخدم بالفعل',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.confirmed' => 'كلمة المرور غير متطابقة',
        ];
    }
}

<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CartAddRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_type' => 'required|in:domain,server',
            'product_id' => 'required|integer|min:1',
            'quantity' => 'sometimes|integer|min:1|max:10'
        ];
    }

    public function messages(): array
    {
        return [
            'product_type.required' => 'نوع المنتج مطلوب',
            'product_type.in' => 'نوع المنتج يجب أن يكون domain أو server',
            'product_id.required' => 'معرف المنتج مطلوب',
            'product_id.integer' => 'معرف المنتج يجب أن يكون رقم',
            'quantity.integer' => 'الكمية يجب أن تكون رقم',
            'quantity.min' => 'الكمية يجب أن تكون على الأقل 1',
            'quantity.max' => 'الكمية لا يمكن أن تتجاوز 10'
        ];
    }
}

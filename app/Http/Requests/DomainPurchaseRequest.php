<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DomainPurchaseRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            'domain' => 'required|string|max:63',
            'tld'    => 'required|string|max:24',
            'years'  => 'required|integer|min:1|max:10',
            'contact' => 'required|array',
            'contact.name' => 'required|string',
            'contact.email' => 'required|email',
            'contact.country' => 'required|string|size:2', // ISO2
            'idempotency_key' => 'nullable|string|max:255',
            'ns' => 'nullable|array',
            'ns.*' => 'string',
            'privacy' => 'nullable|boolean',
        ];
    }

    protected function prepareForValidation()
    {
        // normalize domain/tld
        $this->merge([
            'domain' => strtolower(trim($this->domain)),
            'tld' => strtolower(trim($this->tld)),
        ]);
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->company_id;
    }

    public function rules(): array
    {
        return [
            'first_name'     => ['required', 'string', 'max:100'],
            'last_name'      => ['required', 'string', 'max:100'],
            'company_name'   => ['nullable', 'string', 'max:150'],
            'email'          => ['nullable', 'email', 'max:150'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'address_line'   => ['nullable', 'string', 'max:200'],
            'postal_code'    => ['nullable', 'string', 'max:20'],
            'city'           => ['nullable', 'string', 'max:100'],
            'country'        => ['nullable', 'string', 'max:100'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

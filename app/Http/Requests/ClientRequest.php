<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->company_id;
    }

    /**
     * The dropdown carries a "＋ Add a new source" sentinel; when it's picked
     * the real value lives in lead_source_custom. Fold it back before
     * validation so the rest of the stack only ever sees lead_source.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('lead_source') === '__new__') {
            $this->merge(['lead_source' => trim((string) $this->input('lead_source_custom', ''))]);
        }
    }

    public function rules(): array
    {
        return [
            'navigation_area' => ['nullable', 'string', 'max:150'],
            'current_boat'    => ['nullable', 'string', 'max:150'],
            'lead_source'     => ['nullable', 'string', 'max:100'],
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

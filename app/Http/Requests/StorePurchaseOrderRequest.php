<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasRole('owner-mitra') === true; }
    public function rules(): array { return ['branch_id' => ['required', 'integer', 'exists:branches,id'], 'notes' => ['nullable', 'string', 'max:2000'], 'items' => ['required', 'array', 'min:1'], 'items.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id'], 'items.*.quantity' => ['required', 'numeric', 'gt:0']]; }
}

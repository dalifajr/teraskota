<?php

namespace App\Http\Requests;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $menuId = $this->route('menu') ? $this->route('menu')->id : null;
        $tenantId = TenantContext::id();

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('menus', 'code')
                    ->where(fn($q) => $tenantId !== null ? $q->where('tenant_id', $tenantId) : $q)
                    ->ignore($menuId),
            ],
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'profit_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(fn($q) => $tenantId !== null ? $q->where('tenant_id', $tenantId) : $q),
            ],
            'use_global_profit' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}

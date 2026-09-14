<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMenuRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $menuId = $this->route('menu') ? $this->route('menu')->id : null;

        return [
            'code' => ['required', 'string', 'max:50', 'unique:menus,code,' . $menuId],
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'profit_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'category_id' => ['required', 'exists:categories,id'],
            'use_global_profit' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}

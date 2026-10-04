<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:in,out,set'],
            'quantity' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Tipe penyesuaian stok wajib diisi.',
            'type.in' => 'Tipe harus in, out, atau set.',
            'quantity.required' => 'Jumlah wajib diisi.',
            'quantity.min' => 'Jumlah tidak boleh negatif.',
        ];
    }
}

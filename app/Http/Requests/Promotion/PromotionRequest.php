<?php

namespace App\Http\Requests\Promotion;

use App\Enums\DiscountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromotionRequest extends FormRequest
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
        $promotionId = $this->route('promotion')?->id;

        return [
            'code' => ['required', 'string', 'max:50', 'alpha_dash',
                Rule::unique('promotions', 'code')->ignore($promotionId)],
            'name' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'image_url' => ['nullable', 'string', 'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== null
                        && filter_var($value, FILTER_VALIDATE_URL) === false
                        && ! preg_match('~^/images/uploads/[A-Za-z0-9._-]+$~', $value)) {
                        $fail('Gambar promo tidak valid.');
                    }
                }],
            'discount_type' => ['required', 'string', Rule::in(DiscountType::values())],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode promo wajib diisi.',
            'code.unique' => 'Kode promo sudah digunakan.',
            'discount_type.required' => 'Tipe diskon wajib diisi.',
            'discount_value.required' => 'Nilai diskon wajib diisi.',
            'discount_value.min' => 'Nilai diskon minimal 0.01.',
            'ends_at.after_or_equal' => 'Tanggal berakhir harus setelah tanggal mulai.',
        ];
    }

    /**
     * A percentage discount above 100 is nonsensical.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input('discount_type') === DiscountType::PERCENTAGE->value) {
                if ((float) $this->input('discount_value') > 100) {
                    $validator->errors()->add('discount_value', 'Diskon persen maksimal 100.');
                }
            }
        });
    }
}

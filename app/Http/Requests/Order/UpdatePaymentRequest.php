<?php

namespace App\Http\Requests\Order;

use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends FormRequest
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
            'payment_status' => ['required', 'string', Rule::in(PaymentStatus::values())],
            'transaction_id' => ['nullable', 'string', 'max:100', 'unique:payments,transaction_id'],
        ];
    }
}

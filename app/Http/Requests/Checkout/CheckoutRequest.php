<?php

namespace App\Http\Requests\Checkout;

use App\Enums\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === User::ROLE_CUSTOMER;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $destinations = $this->input('payment_method') === PaymentMethod::BANK_TRANSFER->value
            ? config('payments.banks', [])
            : config('payments.e_wallets', []);

        return [
            'shipping_name' => ['required', 'string', 'max:100'],
            'shipping_phone' => ['required', 'string', 'max:30'],
            'shipping_address' => ['required', 'string', 'max:1000'],
            'shipping_city' => ['required', 'string', 'max:100'],
            'shipping_postal_code' => ['required', 'string', 'max:20'],
            'payment_method' => ['required', 'string', Rule::in(PaymentMethod::values())],
            'payment_destination_code' => [
                Rule::requiredIf(fn (): bool => in_array(
                    $this->input('payment_method'),
                    [PaymentMethod::BANK_TRANSFER->value, PaymentMethod::E_WALLET->value],
                    true
                )),
                'nullable',
                'string',
                Rule::in(collect($destinations)
                    ->filter(fn (array $destination): bool => filled($destination['account_number'])
                        && filled($destination['account_name']))
                    ->pluck('code')
                    ->all()),
            ],
            'promotion_code' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shipping_name.required' => 'Nama penerima wajib diisi.',
            'shipping_phone.required' => 'Nomor telepon wajib diisi.',
            'shipping_address.required' => 'Alamat wajib diisi.',
            'payment_method.required' => 'Metode pembayaran wajib dipilih.',
            'payment_method.in' => 'Metode pembayaran tidak valid.',
        ];
    }
}

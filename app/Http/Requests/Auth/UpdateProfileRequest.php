<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => [
                'sometimes', 'required', 'string', 'email', 'max:150',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'avatar_url' => ['sometimes', 'nullable', 'string', 'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== null
                        && filter_var($value, FILTER_VALIDATE_URL) === false
                        && ! preg_match('~^/images/uploads/[A-Za-z0-9._-]+$~', $value)) {
                        $fail('Foto profil tidak valid.');
                    }
                }],
            'password' => ['sometimes', 'required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'current_password' => ['required_with:password', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('email')) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }
}

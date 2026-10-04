<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthService
{
    /**
     * A valid hash of a random secret, computed once. Used to keep the timing of a
     * failed login comparable whether or not the account exists.
     */
    private static ?string $dummyHash = null;

    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => $data['password'],
            'role' => User::ROLE_CUSTOMER,
            'phone' => $data['phone'] ?? null,
        ]);

        return [
            'user' => $user,
            'token' => $this->issueToken($request = request(), $user, $data['device_name'] ?? 'mobile'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{user: User, token: string}|null
     */
    public function login(Request $request, array $data): ?array
    {
        $user = User::where('email', $data['email'])->first();

        // Hash::check against a dummy hash keeps the timing similar whether the
        // account exists or not, so the endpoint is not a user-enumeration oracle.
        $hash = $user?->password ?? (self::$dummyHash ??= Hash::make(bin2hex(random_bytes(16))));

        if ($user === null || ! Hash::check($data['password'], $hash)) {
            Log::notice('Failed login attempt', ['email' => $data['email'], 'ip' => $request->ip()]);

            return null;
        }

        $this->auditLog->recordLogin($request, $user);

        return [
            'user' => $user,
            'token' => $this->issueToken($request, $user, $data['device_name'] ?? 'mobile'),
        ];
    }

    public function logout(Request $request): void
    {
        $request->user()->currentAccessToken()?->delete();
    }

    public function updateProfile(User $user, array $data): User
    {
        $attributes = array_filter([
            'name' => $data['name'] ?? null,
            'email' => isset($data['email']) ? strtolower($data['email']) : null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'avatar_url' => $data['avatar_url'] ?? null,
        ], fn ($value) => $value !== null);

        if (isset($data['password'])) {
            if (! Hash::check($data['current_password'] ?? '', $user->password)) {
                throw BusinessRuleException::make(
                    'Password saat ini salah.',
                    422,
                    'INVALID_CURRENT_PASSWORD'
                );
            }

            $attributes['password'] = $data['password'];
        }

        $user->update($attributes);

        return $user->fresh();
    }

    private function issueToken(Request $request, User $user, string $deviceName): string
    {
        return $user->createToken($deviceName)->plainTextToken;
    }
}

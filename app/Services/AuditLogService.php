<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuditLogService
{
    /**
     * Record an administrative action. Passwords and tokens are never logged.
     *
     * @param  array<string, mixed>  $properties
     */
    public function record(
        Request $request,
        string $action,
        string $module,
        string $description,
        array $properties = [],
        ?int $userId = null
    ): void {
        try {
            AuditLog::record([
                'user_id' => $userId ?? $request->user()?->id,
                'action' => $action,
                'module' => $module,
                'description' => $description,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'properties' => $this->sanitize($properties),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Auditing must never break the business operation.
            Log::warning('Failed to write audit log', [
                'action' => $action,
                'module' => $module,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function recordLogin(Request $request, User $user): void
    {
        $this->record(
            $request,
            action: 'auth.login',
            module: 'auth',
            description: "User {$user->email} login",
            userId: $user->id
        );
    }

    /**
     * Strip sensitive keys before persisting properties.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function sanitize(array $properties): array
    {
        $sensitive = ['password', 'password_confirmation', 'current_password', 'token', 'secret'];

        return collect($properties)
            ->except($sensitive)
            ->map(function ($value) use ($sensitive) {
                if (is_array($value)) {
                    return collect($value)->except($sensitive)->all();
                }

                return $value;
            })
            ->all();
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

/**
 * Uses Laravel's standard password_reset_tokens table (no custom token scheme).
 */
class ForgotPasswordController extends Controller
{
    public function sendResetLink(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // Failures are swallowed and logged, so the response can never be used to
        // enumerate registered email addresses.
        rescue(
            fn () => Password::sendResetLink($request->only('email')),
            report: false,
        );

        return ApiResponse::success(
            ['sent' => true],
            'Jika email terdaftar, tautan reset password telah dikirim.'
        );
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return ApiResponse::success([
            'user' => (new UserResource($result['user']))->resolve(),
            'token' => $result['token'],
            'token_type' => 'Bearer',
        ], 'Registrasi berhasil', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request, $request->validated());

        if ($result === null) {
            return ApiResponse::error('Email atau password salah.', 401);
        }

        return ApiResponse::success([
            'user' => (new UserResource($result['user']))->resolve(),
            'token' => $result['token'],
            'token_type' => 'Bearer',
        ], 'Login berhasil');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request);

        return ApiResponse::success([], 'Logout berhasil');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'user' => (new UserResource($request->user()))->resolve(),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->authService->updateProfile($request->user(), $request->validated());

        return ApiResponse::success([
            'user' => (new UserResource($user))->resolve(),
        ], 'Profil berhasil diperbarui');
    }
}

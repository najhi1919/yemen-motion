<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;


class AuthApiController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated) {
            // Extract role name and remove it from data that is saved to the users table
            $roleName = $validated['role'];
            $validatedData = $validated;
            // Remove role field from validated data to avoid database column mismatch
            unset($validatedData['role']);

            // Create user with the remaining data (password hashing handled by model cast)
            $user = User::create($validatedData);

            // Assign the role via Spatie Permission
            $user->assignRole($roleName);

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'user' => new UserResource($user),
                    'token' => $token,
                    'role' => $user->roles->first()?->name,
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ],
                'errors' => null,
            ], 201);
        });
    }

    public function login(LoginRequest $request): JsonResponse
    {
        // Validate request using LoginRequest rules
        $credentials = $request->validated();

        // Build rate limiter key based on lowercased email and IP address
        $key = 'login:' . strtolower($credentials['email']) . ':' . $request->ip();

        // Check if the key has exceeded the allowed attempts (5 attempts per 60 seconds)
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many login attempts. Please try again later.',
                'data' => null,
                'errors' => null,
            ], 429);
        }

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            // Record a failed attempt
            RateLimiter::hit($key);
            return response()->json([
                'success' => false,
                'message' => 'بيانات الدخول غير صحيحة.',
                'data' => null,
                'errors' => null,
            ], 401);
        }

        // Successful login – clear any existing attempts
        RateLimiter::clear($key);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
                'role' => $user->roles->first()?->name,
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
            'errors' => null,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
            'data' => null,
            'errors' => null,
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'User data retrieved',
            'data' => [
                'user' => new UserResource($user),
                'role' => $user->roles->first()?->name,
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
            'errors' => null,
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'إذا كان البريد مسجلاً لدينا، فسيتم إرسال رابط استعادة كلمة المرور.',
                'errors' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'إذا كان البريد مسجلاً لدينا، فسيتم إرسال رابط استعادة كلمة المرور.',
            'errors' => null,
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'token', 'password', 'password_confirmation'),
            function (User $user, string $password): void {
                $user->password = $password;
                $user->save();
                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'تم تغيير كلمة المرور بنجاح.',
                'errors' => null,
            ]);
        }

        return response()->json([
            'success' => false,
            'data' => null,
            'message' => 'رابط استعادة كلمة المرور غير صالح أو منتهي الصلاحية.',
            'errors' => null,
        ], 422);
    }
}

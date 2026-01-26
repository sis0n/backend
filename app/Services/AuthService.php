<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\PersonalAccessTokenResult;

class AuthService
{
    public function attemptLogin(string $identifier, string $password): array
    {
        $user = User::where('username', $identifier)->first();

        if (!$user) {
            $student = User::whereHas('student', fn($q) => $q->where('student_number', $identifier))->first();
            if ($student) $user = $student;
        }

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'identifier' => ['These credentials do not match our records.']
            ]);
        }

        $user->tokens->each(fn($token) => $token->delete());

        $tokenResult = $user->createToken('AuthToken');

        $accessToken = $tokenResult->accessToken;

        $refreshToken = $tokenResult->token->id; // placeholder for refresh token logic

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_at' => $tokenResult->token->expires_at->toDateTimeString(),
        ];
    }

    public function getAuthenticatedUser(User $user): array
    {
        $user->load(match ($user->role) {
            'student' => 'student',
            'faculty' => 'faculty',
            'staff' => 'staff',
            default => [],
        });

        return [
            'user' => $user,
            'role' => $user->role,
        ];
    }

    public function logout(User $user): bool
    {
        $token = $user->token();

        if ($token) {
            $token->revoke();

            return true;
        }

        return false;
    }
}

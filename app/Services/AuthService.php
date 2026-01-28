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
        /*
        $activeToken = $user->tokens()
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->exists();

        if ($activeToken) {
            throw ValidationException::withMessages([
                'session' => ['Account is currently logged in on another device. Please log out first.']
            ]);
        }
        */

        $tokenResult = $user->createToken('AuthToken');
        $accessToken = $tokenResult->accessToken;

        return [
            'access_token' => $accessToken,
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

    public function refreshToken(string $refreshTokenId): array
    {
        $token = \Laravel\Passport\Token::find($refreshTokenId);

        if (!$token) {
            throw new \Exception('Invalid refresh token.');
        }

        if ($token->revoked) {
            throw new \Exception('Token has been revoked.');
        }

        $user = $token->user;

        if (!$user) {
            throw new \Exception('User not found.');
        }

        $token->revoke();

        $newTokenResult = $user->createToken('AuthToken');

        return [
            'access_token' => $newTokenResult->accessToken,
            'refresh_token' => $newTokenResult->token->id,
            'token_type' => 'Bearer',
            'expires_at' => $newTokenResult->token->expires_at->toDateTimeString(),
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

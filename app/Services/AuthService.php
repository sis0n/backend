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
        // Find user by username or student/faculty/staff identifier
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

        // Delete old tokens
        $user->tokens->each(fn($token) => $token->delete());

        // Create new personal access token
        $tokenResult = $user->createToken('AuthToken');

        $accessToken = $tokenResult->accessToken;

        // For password grant, we can generate it manually if needed.
        $refreshToken = $tokenResult->token->id; // placeholder for refresh token logic

        // Profile based on role
        $profile = match ($user->role) {
            'student' => $user->student,
            'faculty' => $user->faculty,
            'staff'   => $user->staff,
            default   => null,
        };

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_at' => $tokenResult->token->expires_at->toDateTimeString(),
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

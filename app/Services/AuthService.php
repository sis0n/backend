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

        $profileData = null;
        if ($user->role === 'student' && $user->student) {
            $profileData = ['student_number' => $user->student->student_number];
        } elseif ($user->role === 'faculty' && $user->faculty) {
            $profileData = ['unique_faculty_id' => $user->faculty->unique_faculty_id];
        } elseif ($user->role === 'staff' && $user->staff) {
            $profileData = ['employee_id' => $user->staff->employee_id];
        }

        return [
            'full_name' => trim("{$user->first_name} {$user->middle_name} {$user->last_name} {$user->suffix}"),
            'profile_picture' => $user->profile_picture ? url($user->profile_picture) : null,
            'role' => $user->role,
            'profile_details' => $profileData
        ];
    }

    public function refreshToken(string $refreshTokenId): array
    {
        // Find the token record in the database using the ID provided as refresh_token
        $token = \Laravel\Passport\Token::find($refreshTokenId);

        if (!$token) {
            throw new \Exception('Invalid refresh token.');
        }

        // Optional: Check if revoked
        if ($token->revoked) {
            throw new \Exception('Token has been revoked.');
        }

        $user = $token->user;

        if (!$user) {
            throw new \Exception('User not found.');
        }

        // Revoke the old token
        $token->revoke();

        // Create a new token
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

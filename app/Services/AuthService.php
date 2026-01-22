<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function attemptLogin(string $identifier, string $password): array
    {
        $user = User::where('username', $identifier)->first();

        if(!$user){
            $student = User::whereHas('student', function($query) use ($identifier){
                $query->where('student_number', $identifier);
            })->first();

            if($student){
                $user = $student;
            }
        }

        // if no user found (student, staff, and facuty), or password dont match
        if(!$user || !Hash::check($password, $user->password)){
            throw ValidationException::withMessages([
                'identifier' => ['these credentials do not match our records.']
            ]);
        }

        // detele existing tokens for user to ensure fresh tokens
        $user->tokens->each(function ($token) {
            $token->delete();
        });

        $token = $user->createToken('authToken');

        // profile data na naka base sa role
        $profile = null;
        if($user->role === 'student'){
            $profile = $user->student;
        } elseif($user->role === 'faculty'){
            $profile = $user->faculty;
        } elseif($user->role === 'staff'){
            $profile = $user->staff;
        }

        return [
            'user' => $user,
            'profile' => $profile,
            'access_token' => $token->accessToken,
            'refresh_token' => $token->refreshToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->token->expires_at->toDateTimeString(),
        ];
    }
}
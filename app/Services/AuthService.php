<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

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

        // LOG ACTION: LOGIN
        AuditTrailService::log($user->user_id, 'LOGIN', 'AUTH', null, 'User logged in successfully via Mobile App.');

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
            // LOG ACTION: LOGOUT
            AuditTrailService::log($user->user_id, 'LOGOUT', 'AUTH', null, 'User logged out.');

            $token->revoke();

            return true;
        }

        return false;
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw new \Exception('The current password provided is incorrect.');
        }

        $user->update([
            'password' => Hash::make($newPassword)
        ]);

        // LOG ACTION: UPDATE PASSWORD
        AuditTrailService::log($user->user_id, 'UPDATE', 'USERS', $user->user_id, 'User changed their password.');
    }

    /**
     * Generate and send OTP for password reset based on unique Username or Student Number.
     */
    public function sendOtp(string $identifier): array
    {
        // 1. Find user by unique Username first
        $user = User::where('username', $identifier)->first();

        // 2. If not found, try finding via Student Number
        if (!$user) {
            $student = User::whereHas('student', fn($q) => $q->where('student_number', $identifier))->first();
            if ($student) $user = $student;
        }

        if (!$user) {
            return ['success' => false, 'message' => 'Account not found with that username or student ID.'];
        }

        if (!$user->email) {
            return [
                'success' => false, 
                'message' => 'This account does not have a registered email address. Please contact the Librarian to update your profile.'
            ];
        }

        $email = $user->email;

        // Generate 6-digit OTP
        $otp = sprintf("%06d", mt_rand(1, 999999));
        $expiresAt = Carbon::now('Asia/Manila')->addMinutes(10);

        // Save to password_reset_tokens table
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token'      => $otp,
                'expires_at' => $expiresAt,
                'created_at' => now()
            ]
        );

        // SEND REAL EMAIL (API Style - No Blade needed)
        try {
            Mail::raw("Your Library System password reset OTP code is: {$otp}. This code will expire in 10 minutes.", function ($message) use ($email) {
                $message->to($email)
                        ->subject('Password Reset OTP');
            });
        } catch (\Exception $e) {
            Log::error("Failed to send OTP email to {$email}: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send email. Please check your SMTP configuration in .env'];
        }

        // Mask the email for security
        $maskedEmail = substr($email, 0, 1) . '****' . substr($email, strpos($email, '@') - 1);

        return [
            'success' => true,
            'message' => "OTP has been sent to your registered email: {$maskedEmail}",
            'email'   => $email 
        ];
    }

    /**
     * Verify the provided OTP.
     */
    public function verifyOtp(string $email, string $otp): array
    {
        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('token', $otp)
            ->first();

        if (!$record) {
            return ['success' => false, 'message' => 'Invalid OTP code.'];
        }

        if (Carbon::parse($record->expires_at)->isPast()) {
            return ['success' => false, 'message' => 'OTP has expired. Please request a new one.'];
        }

        return ['success' => true, 'message' => 'OTP verified successfully.'];
    }

    /**
     * Reset the user's password.
     */
    public function resetPassword(string $email, string $newPassword): array
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return ['success' => false, 'message' => 'User record not found.'];
        }

        // Update password
        $user->update([
            'password' => Hash::make($newPassword)
        ]);

        // Clean up the token
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        // Log Action
        AuditTrailService::log($user->user_id, 'RESET_PASSWORD', 'USERS', $user->user_id, 'User reset their password via OTP.');

        return ['success' => true, 'message' => 'Password reset successful. You can now login with your new password.'];
    }
}

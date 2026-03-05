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

        if (!in_array($user->role, ['student', 'faculty', 'staff'])) {
            throw ValidationException::withMessages([
                'identifier' => ['Access denied. This mobile application is restricted to Students, Faculty, and Staff only.']
            ]);
        }

        $user->tokens->each(fn($token) => $token->delete());

        $tokenResult = $user->createToken('AuthToken');

        $accessToken = $tokenResult->accessToken;

        $refreshToken = $tokenResult->token->id; // placeholder for refresh token logic

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

        AuditTrailService::log($user->user_id, 'UPDATE', 'USERS', $user->user_id, 'User changed their password.');
    }

    /**
     * Generate and send OTP for password reset based on unique Username or Student Number.
     */
    public function sendOtp(string $identifier): array
    {
        $user = User::where('username', $identifier)->first();

        if (!$user) {
            $student = User::whereHas('student', fn($q) => $q->where('student_number', $identifier))->first();
            if ($student) $user = $student;
        }

        if (!$user) {
            return ['success' => false, 'message' => 'Account not found with that username or student ID.'];
        }

        if (!in_array($user->role, ['student', 'faculty', 'staff'])) {
            return ['success' => false, 'message' => 'Access denied. Only Students, Faculty, and Staff can reset their password via mobile.'];
        }

        if (!$user->email) {
            return [
                'success' => false,
                'message' => 'This account does not have a registered email address. Please contact the Librarian to update your profile.'
            ];
        }

        $email = $user->email;

        $otp = sprintf("%06d", mt_rand(1, 999999));
        $expiresAt = Carbon::now('Asia/Manila')->addMinutes(10);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token'      => $otp,
                'expires_at' => $expiresAt,
                'created_at' => now()
            ]
        );

        try {
            Mail::raw("Your Library System password reset OTP code is: {$otp}. This code will expire in 10 minutes.", function ($message) use ($email) {
                $message->to($email)
                    ->subject('Password Reset OTP');
            });
        } catch (\Exception $e) {
            Log::error("Failed to send OTP email to {$email}: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send email. Please check your SMTP configuration in .env'];
        }

        $maskedEmail = substr($email, 0, 1) . '****' . substr($email, strpos($email, '@') - 1);

        return [
            'success' => true,
            'message' => "OTP has been sent to your registered email: {$maskedEmail}",
            'email'   => $email
        ];
    }

    /**
     * Verify the provided OTP and return a temporary reset token.
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

        $resetToken = \Illuminate\Support\Str::random(6);

        DB::table('password_reset_tokens')->where('email', $email)->update([
            'token' => $resetToken,
            'expires_at' => Carbon::now('Asia/Manila')->addMinutes(15)
        ]);

        return [
            'success' => true,
            'message' => 'OTP verified successfully.',
            'reset_token' => $resetToken
        ];
    }

    /**
     * Reset the user's password using the temporary reset token.
     */
    public function resetPassword(string $email, string $resetToken, string $newPassword): array
    {
        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('token', $resetToken)
            ->first();

        if (!$record) {
            return ['success' => false, 'message' => 'Invalid or expired reset token.'];
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            return ['success' => false, 'message' => 'User record not found for this email.'];
        }

        try {
            DB::beginTransaction();

            DB::table('users')->where('user_id', $user->user_id)->update([
                'password' => Hash::make($newPassword),
                'updated_at' => now()
            ]);

            DB::table('password_reset_tokens')->where('email', $email)->delete();

            AuditTrailService::log($user->user_id, 'RESET_PASSWORD', 'USERS', $user->user_id, 'User reset their password via OTP.');

            DB::commit();
            return ['success' => true, 'message' => 'Password reset successful. You can now login with your new password.'];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => 'Failed to update password: ' . $e->getMessage()];
        }
    }
}

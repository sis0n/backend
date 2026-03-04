<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PasswordResetController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Send OTP to user's email.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => 'required|string',
        ]);

        $result = $this->authService->sendOtp($request->identifier);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result, 200);
    }

    /**
     * Verify the sent OTP.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|string|size:6',
        ]);

        $result = $this->authService->verifyOtp($request->email, $request->otp);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result, 200);
    }

    /**
     * Reset password after OTP verification.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email'       => 'required|email',
            'reset_token' => 'required|string',
            'password'    => 'required|string|min:8|confirmed',
        ]);

        $result = $this->authService->resetPassword(
            $request->email,
            $request->reset_token,
            $request->password
        );

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result, 200);
    }
}

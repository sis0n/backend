<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\String\TruncateMode;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(Request $request): JsonResponse
    {
        // dd(config('services.passport'));
        $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            $tokens = $this->authService->attemptLogin(
                $request->identifier,
                $request->password
            );

            return response()->json([
                'success' => true,
                'message' => 'Login Successful',
                'data' => $tokens
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Authentication Failed!',
                'errors' => $e->errors()
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function refresh(Request $request): JsonResponse
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        try {
            $tokens = $this->authService->refreshToken($request->refresh_token);

            return response()->json([
                'message' => 'Token refreshed successfully',
                'data' => $tokens
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to refresh token',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function me(Request $request): JsonResponse
    {
        $userData = $this->authService->getAuthenticatedUser($request->user());

        return response()->json([
            'success' => true,
            'data' => $userData
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $this->authService->logout($request->user());

            return response()->json([
                'success' => true,
                'message' => 'nagana tol'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        
        try{
            $this->authService->changePassword(
                $request->user(),
                $request->current_password,
                $request->new_password,
            );

            return response()->json([
                'success' => true,
                'message' => 'password has been updated',
            ]);
        } catch (\Exception $e){
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

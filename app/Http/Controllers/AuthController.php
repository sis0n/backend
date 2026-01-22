<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        try{
            $result = $this->authService->attemptLogin(
                $request->identifier,
                $request->password
            );

            return response()->json([
                'message' => 'Login Successful',
                'data' => $result
            ]);
        } catch (ValidationException $e){
            return response()->json([
                'message' => 'Authentication Failed!',
                'errors' => $e->errors()
            ], 401);
        } catch (\Exception $e){
            return response()->json([
                'message' => 'An unexpected error occured',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

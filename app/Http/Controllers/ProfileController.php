<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\StudentProfileService;
use App\Services\FacultyProfileService;
use App\Services\StaffProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * Display the authenticated user's profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = match ($user->role) {
            'student' => (new StudentProfileService())->getProfile($user),
            'faculty' => (new FacultyProfileService())->getProfile($user),
            'staff'   => (new StaffProfileService())->getProfile($user),
            default   => ['error' => 'Invalid role assigned to user.'],
        };

        if (isset($data['error'])) {
            return response()->json(['success' => false, 'message' => $data['error']], 400);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Update the authenticated user's profile.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'student') {
            $validator = Validator::make($request->all(), [
                'course_id' => 'required|integer',
                'year_level' => 'required|integer',
                'section' => 'required|string|max:50',
                'email' => 'required|email|max:100',
                'contact' => 'required|string|max:20',
                'profile_picture' => 'required|image|mimes:jpeg,png,jpg|max:2048',
                'registration_form' => 'required|mimes:pdf,jpeg,png,jpg|max:5120',
            ]);
        } else {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|max:100',
                'contact' => 'required|string|max:20',
                'profile_picture' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            ]);
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $service = match ($user->role) {
            'student' => new StudentProfileService(),
            'faculty' => new FacultyProfileService(),
            'staff'   => new StaffProfileService(),
            default   => null,
        };

        if (!$service) return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);

        $profilePic = $request->file('profile_picture');
        $regFile = $request->file('registration_form');

        if ($user->role === 'student') {
            $result = $service->updateProfile($user, $request->all(), $regFile, $profilePic);
        } else {
            $result = $service->updateProfile($user, $request->all(), $profilePic);
        }

        if (isset($result['status']) && $result['status'] === 'error') {
            return response()->json(['success' => false, 'message' => $result['message']], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.'
        ]);
    }
}
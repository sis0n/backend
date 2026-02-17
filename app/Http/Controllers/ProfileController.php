<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\StudentProfileService;
use App\Services\FacultyProfileService;
use App\Services\StaffProfileService;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        // Dito natin pipiliin ang tamang Service
        $data = match ($user->role) {
            'student' => (new StudentProfileService())->getProfile($user),
            'faculty' => (new FacultyProfileService())->getProfile($user),
            'staff'   => (new StaffProfileService())->getProfile($user),
            default   => ['error' => 'Invalid Role'],
        };

        if (isset($data['error'])) {
            return response()->json(['success' => false, 'message' => $data['error']], 400);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        if ($request->hasFile('profile_picture')) {
            $request->validate([
                'profile_picture' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            ]);

            $file = $request->file('profile_picture');
            $filename = 'profile_' . $user->user_id . '_' . time() . '.' . $file->getClientOriginalExtension();

            $file->move(public_path('uploads/profile_images'), $filename);

            if ($user->profile_picture && file_exists(public_path($user->profile_picture))) {
                @unlink(public_path($user->profile_picture));
            }

            $user->profile_picture = 'uploads/profile_images/' . $filename;
            $user->save();
        }

        $service = match ($user->role) {
            'student' => new StudentProfileService(),
            'faculty' => new FacultyProfileService(),
            'staff' => new StaffProfileService(),
            default => null,
        };

        if (!$service) return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);

        $result = $service->updateProfile($user, $request->all());

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error']
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            // 'message' => 'profile has been updated.'
        ]);
    }
}

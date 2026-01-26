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
}

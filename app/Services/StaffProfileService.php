<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class StaffProfileService
{
    public function getProfile($user)
    {
        $result = (array) DB::table('users as u')
            ->leftJoin('staff as s', 'u.user_id', '=', 's.user_id')
            ->where('u.user_id', $user->user_id)
            ->select(
                'u.user_id',
                'u.username',
                'u.first_name',
                'u.middle_name',
                'u.last_name',
                'u.suffix',
                'u.email',
                'u.profile_picture',
                'u.role',
                'f.faculty_id',
                'f.unique_faculty_id',
                'f.college_id',
                'f.contact',
                'f.status',
                'f.profile_updated',
                'c.college_code',
                'c.college_name'
            )
            ->first();

        if (!$result) return ['error' => 'Staff not found'];

        $result['is_qualified'] = !empty($result['first_name']) && !empty($result['position']) &&
            !empty($result['contact']);
        return $result;
    }

    public function updateProfile($user, $data, $profilePic = null)
    {
        $profilePicPath = $user->profile_picture;
        if ($profilePic) {
            $profilePicPath = $profilePic->store('profile_pictures', 'public');
        }

        DB::transaction(function () use ($user, $data, $profilePicPath) {
            // 1. Update Users Table
            DB::table('users')->where('user_id', $user->user_id)->update([
                'first_name'      => $data['first_name'],
                'middle_name'     => $data['middle_name'] ?? null,
                'last_name'       => $data['last_name'],
                'suffix'          => $data['suffix'] ?? null,
                'email'           => $data['email'], // Idagdag ito para sa account consistency
                'profile_picture' => $profilePicPath,
                'updated_at'      => now(),
            ]);

            // 2. Update Staff Table
            DB::table('staff')->where('user_id', $user->user_id)->update([
                'position'        => $data['position'],
                'contact'         => $data['contact'],
                'profile_updated' => 1,
                'updated_at'      => now(), // Optional pero maganda para sa audit
            ]);
        });

        return ['status' => 'success'];
    }
}

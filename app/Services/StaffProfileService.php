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
                // 'f.faculty_id',
                's.employee_id',
                's.position',
                's.contact',
                's.status',
                's.profile_updated',
            )
            ->first();

        if (!$result) return ['error' => 'Staff not found'];

        $result['is_qualified'] = !empty($result['first_name']) && !empty($result['position']) &&
            !empty($result['contact']);
        return $result;
    }

    public function updateProfile($user, $data, $profilePic = null)
    {
        $staff = DB::table('staff')->where('user_id', $user->user_id)->first();

        if (!$staff) {
            return ['status' => 'error', 'message' => 'Staff record not found'];
        }


        $profilePicPath = $profilePic ? $profilePic : $user->profile_picture;

        try {
            DB::transaction(function () use ($user, $data, $profilePicPath, $staff) {
                DB::table('users')->where('user_id', $user->user_id)->update([
                    'first_name'      => $data['first_name'] ?? $user->first_name,
                    'middle_name'     => $data['middle_name'] ?? $user->middle_name,
                    'last_name'       => $data['last_name'] ?? $user->last_name,
                    'suffix'          => $data['suffix'] ?? $user->suffix,
                    'email'           => $data['email'] ?? $user->email,
                    'profile_picture' => $profilePicPath,
                    'updated_at'      => now(),
                ]);

                DB::table('staff')->where('user_id', $user->user_id)->update([
                    'position'        => $data['position'] ?? $staff->position,
                    'contact'         => $data['contact'] ?? $staff->contact,
                    'profile_updated' => 1,
                    'updated_at'      => now(),
                ]);
            });

            return ['status' => 'success'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }
}

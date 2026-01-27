<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class FacultyProfileService
{
    public function getProfile($user)
    {
        $result = (array) DB::table('users as u')
            ->leftJoin('faculty as f', 'u.user_id', '=', 'f.user_id')
            ->leftJoin('colleges as c', 'f.college_id', '=', 'c.college_id')
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
                's.employee_id',
                's.position',
                's.contact',
                's.status',
                's.profile_updated'
            )
            ->first();

        if (!$result) return ['error' => 'Faculty not found'];

        if ($result['college_code'] && $result['college_name']) {
            $result['college_full_name'] = $result['college_code'] . ' - ' . $result['college_name'];
        }

        $result['is_qualified'] = !empty($result['first_name']) && !empty($result['last_name']) &&
            !empty($result['college_id']) && !empty($result['contact']);
        return $result;
    }

    public function updateProfile($user, $data, $profilePic = null)
    {
        $profilePicPath = $user->profile_picture;
        if ($profilePic) {
            $profilePicPath = $profilePic->store('profile_pictures', 'public');
        }

        DB::transaction(function () use ($user, $data, $profilePicPath) {
            DB::table('users')->where('user_id', $user->user_id)->update([
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'suffix' => $data['suffix'] ?? null,
                'email' => $data['email'], 
                'profile_picture' => $profilePicPath,
                'updated_at' => now(),
            ]);

            DB::table('faculty')->where('user_id', $user->user_id)->update([
                'college_id' => $data['college_id'],
                'contact' => $data['contact'],
                'profile_updated' => 1,
                'updated_at' => now(), 
            ]);
        });
        return ['status' => 'success'];
    }
}

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
                'u.user_id', 'u.username', 'u.first_name', 'u.middle_name', 'u.last_name', 
                'u.suffix', 'u.email', 'u.profile_picture', 'u.role',
                'f.faculty_id', 'f.unique_faculty_id', 'f.college_id', 'f.contact', 
                'f.status', 'f.profile_updated',
                'c.college_code', 'c.college_name'
            )
            ->first();

        if (!$result) return ['error' => 'Staff not found'];

        $result['is_qualified'] = !empty($result['first_name']) && !empty($result['position']) &&
            !empty($result['contact']);
        return $result;
    }
}

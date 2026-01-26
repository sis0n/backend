<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class StudentProfileService
{
    public function getProfile($user)
    {
        $result = (array) DB::table('users as u')
            ->leftJoin('students as s', 'u.user_id', '=', 's.user_id')
            ->leftJoin('courses as c', 's.course_id', '=', 'c.course_id')
            ->where('u.user_id', $user->user_id)
            ->select(
                    'u.user_id', 'u.username', 'u.first_name', 'u.middle_name', 'u.last_name', 
                    'u.suffix', 'u.email', 'u.profile_picture', 'u.role',
                    's.student_id', 's.student_number', 's.course_id', 's.year_level', 
                    's.section', 's.contact', 's.registration_form', 's.profile_updated',
                    'c.course_code', 'c.course_title'
                )
            ->first();

        if (!$result) return ['error' => 'Student not found'];

        if ($result['course_code'] && $result['course_title']) {
            $result['course_full_name'] = $result['course_code'] . ' - ' . $result['course_title'];
        }

        $result['is_qualified'] = !empty($result['first_name']) && !empty($result['last_name']) &&
            !empty($result['email']) && !empty($result['profile_picture']) &&
            !empty($result['course_id']) && !empty($result['contact']);
        return $result;
    }
}

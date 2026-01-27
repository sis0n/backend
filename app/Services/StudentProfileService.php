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
                'u.user_id',
                'u.username',
                'u.first_name',
                'u.middle_name',
                'u.last_name',
                'u.suffix',
                'u.email',
                'u.profile_picture',
                'u.role',
                's.student_id',
                's.student_number',
                's.course_id',
                's.year_level',
                's.section',
                's.contact',
                's.registration_form',
                's.profile_updated',
                'c.course_code',
                'c.course_title'
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

    public function updateProfile($user, $data, $regFile = null, $profilePic = null)
    {
        $student = DB::table('students')->where('user_id', $user->user_id)->first();

        if ($student && $student->profile_updated == 1) {
            return [
                'error' => 'profile is already locked'
            ];
        }

        $regFormPath = $student->registration_form ?? null;
        if ($regFile) {
            $regFormPath = $regFile->store('registration_forms', 'public');
        }

        $profilePicPath = $user->profile_picture;
        if ($profilePic) {
            $profilePicPath = $profilePic->store('profile_pictures', 'public');
        }

        DB::transaction(function () use ($user, $data, $regFormPath, $profilePicPath) {
            DB::table('users')->where('user_id', $user->user_id)->update([
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'suffix' => $data['suffix'] ?? null,
                'email' => $data['email'], 
                'profile_picture' => $profilePicPath,
                'updated_at' => now(),
            ]);

            DB::table('students')->where('user_id', $user->user_id)->update([
                'course_id' => $data['course_id'],
                'year_level' => $data['year_level'],
                'section' => $data['section'],
                'contact' => $data['contact'],
                'registration_form' => $regFormPath,
                'profile_updated' => 1,
            ]);
        });

        return ['status' => 'success'];
    }
}

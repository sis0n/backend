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

        if (!$student) {
            return ['status' => 'error', 'message' => 'Student record not found'];
        }

        if ($student->profile_updated == 1) {
            return ['status' => 'error', 'message' => 'Profile is already locked.'];
        }

        try {
            $regFormPath = $student->registration_form;
            if ($regFile) {
                $regFileName = 'reg_' . $user->user_id . '_' . time() . '.' . $regFile->getClientOriginalExtension();
                $regFile->move(public_path('uploads/reg_forms'), $regFileName);
                $regFormPath = 'uploads/reg_forms/' . $regFileName;
            }

            $profilePicPath = $user->profile_picture;
            if ($profilePic) {
                $picFileName = 'profile_' . $user->user_id . '_' . time() . '.' . $profilePic->getClientOriginalExtension();
                $profilePic->move(public_path('uploads/profile_images'), $picFileName);
                $profilePicPath = 'uploads/profile_images/' . $picFileName;
            }

            DB::transaction(function () use ($user, $data, $regFormPath, $profilePicPath, $student) {
                DB::table('users')->where('user_id', $user->user_id)->update([
                    'first_name'      => $data['first_name'] ?? $user->first_name,
                    'middle_name'     => $data['middle_name'] ?? $user->middle_name,
                    'last_name'       => $data['last_name'] ?? $user->last_name,
                    'suffix'          => $data['suffix'] ?? $user->suffix,
                    'email'           => $data['email'] ?? $user->email,
                    'profile_picture' => $profilePicPath, 
                    'updated_at'      => now(),
                ]);

                DB::table('students')->where('user_id', $user->user_id)->update([
                    'course_id'         => $data['course_id'] ?? $student->course_id,
                    'year_level'        => $data['year_level'] ?? $student->year_level,
                    'section'           => $data['section'] ?? $student->section,
                    'contact'           => $data['contact'] ?? $student->contact,
                    'registration_form' => $regFormPath, 
                    'profile_updated'   => 1,
                    'updated_at'        => now(),
                ]);
            });

            return ['status' => 'success', 'message' => 'Profile updated and locked successfully'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Something went wrong: ' . $e->getMessage()];
        }
    }
}

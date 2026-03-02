<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AcademicService
{
    public function getAllCourses()
    {
        return DB::table('courses')
            ->select('course_id', 'course_code', 'course_title')
            ->orderBy('course_code', 'asc')
            ->get();
    }

    public function getAllColleges()
    {
        return DB::table('colleges')
            ->select('college_id', 'college_code', 'college_name')
            ->orderBy('college_code', 'asc')
            ->get();
    }
}

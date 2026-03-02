<?php

namespace App\Http\Controllers;

use App\Services\AcademicService;
use Illuminate\Http\JsonResponse;

class AcademicController extends Controller
{
    protected $academicService;

    public function __construct(AcademicService $academicService)
    {
        $this->academicService = $academicService;
    }

    public function getCourses(): JsonResponse
    {
        try {
            $courses = $this->academicService->getAllCourses();
            return response()->json([
                'success' => true,
                'courses' => $courses
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching courses: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getColleges(): JsonResponse
    {
        try {
            $colleges = $this->academicService->getAllColleges();
            return response()->json([
                'success' => true,
                'colleges' => $colleges
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }
}

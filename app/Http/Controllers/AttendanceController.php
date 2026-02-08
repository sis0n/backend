<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    protected $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    public function getHistory(Request $request)
    {
        $result = $this->attendanceService->getStudentHistory($request->user());

        if(!$result['success']){
            return response()->json($result, 403);
        }

        return response()->json($result);
    }
}

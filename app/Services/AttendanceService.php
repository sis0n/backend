<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Symfony\Component\String\TruncateMode;

class AttendanceService
{
    public function getStudentHistory($user)
    {
        if(strtolower($user->role) !== 'student'){
            return [
                'success' => false,
                'message' => 'attendance history is only available for students'
            ];
        }

        $logs = DB::table('attendance_logs')
            ->where('user_id', $user->user_id)
            ->select('timestamp', 'method')
            ->orderBy('timestamp', 'desc')
            ->get()
            ->map(function ($log) {
                $dt = Carbon::parse($log->timestamp);

                return [
                    'date' => $dt->format('D, M j, Y'),
                    'day' => $dt->format('D'),
                    'time' => $dt->format('g:i A'),
                    'method' => strtolower($log->method)
                ];
            });
        return [
            'success' => true,
            'data' => $logs
        ];
    }
}
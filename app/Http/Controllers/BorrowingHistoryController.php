<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\BorrowingHistoryService;
use Illuminate\Http\Request;

class BorrowingHistoryController extends Controller
{
    protected $historyService;

    public function __construct(BorrowingHistoryService $historyService)
    {
        $this->historyService = $historyService;
    }

    public function getMyHistory(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $result = $this->historyService->getHistory($user);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 404);
        }

        return response()->json($result, 200);
    }
}

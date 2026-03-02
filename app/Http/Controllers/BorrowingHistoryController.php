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

        $result = $this->historyService->getHistory($user);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 404);
        }

        $records = $result['records'];

        return response()->json([
            'success' => true,
            'statistics' => $result['statistics'],
            'data' => $records->items(),
            'pagination' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'total' => $records->total(),
                'has_more' => $records->hasMorePages()
            ]
        ]);
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BorrowingHistoryService
{
    public function getHistory($user)
    {
        $role = strtolower($user->role);

        $tableMapping = [
            'student' => 'students',
            'faculty' => 'faculty',
            'staff'   => 'staff',
        ];

        $tableName = $tableMapping[$role] ?? $role;
        $column = $role . '_id';

        $profile = DB::table($tableName)->where('user_id', $user->user_id)->first();
        $profileId = $profile ? $profile->$column : null;

        if (!$profileId) {
            return [
                'success' => false,
                'message' => "Profile record not found in table: $tableName"
            ];
        }

        $stats = $this->getStatistics($column, $profileId);

        $records = DB::table('borrow_transaction_items as items')
            ->join('borrow_transactions as trans', 'items.transaction_id', '=', 'trans.transaction_id')
            ->join('books', 'items.book_id', '=', 'books.book_id')
            ->leftJoin('users as librarian_user', 'trans.librarian_id', '=', 'librarian_user.user_id')
            ->where('trans.' . $column, $profileId)
            ->select(
                'books.title',
                'books.author',
                'trans.borrowed_at',
                'trans.due_date',
                'trans.expires_at',
                'items.returned_at',
                'trans.status',
                DB::raw("CONCAT(librarian_user.first_name, ' ', librarian_user.last_name) as processed_by")
            )
            ->orderBy('trans.generated_at', 'desc')
            ->get()
            ->map(function ($record) {
                return [
                    'title' => $record->title,
                    'author' => $record->author,
                    'borrowed_at' => $record->borrowed_at ? Carbon::parse($record->borrowed_at)->toDateTimeString() : 'N/A',
                    'due_date' => $record->due_date ? Carbon::parse($record->due_date)->toDateTimeString() : 'N/A',
                    'returned_at' => $record->returned_at ? Carbon::parse($record->returned_at)->toDateTimeString() : 'Not yet returned',
                    'librarian' => $record->processed_by ?? 'N/A',
                    'status' => $this->resolveStatus($record)
                ];
            });

        return [
            'success' => true,
            'statistics' => $stats,
            'records' => $records
        ];
    }

    private function getStatistics($column, $profileId)
    {
        $now = now();
        $baseQuery = DB::table('borrow_transaction_items as items')
            ->join('borrow_transactions as trans', 'items.transaction_id', '=', 'trans.transaction_id')
            ->where('trans.' . $column, $profileId);

        return [
            'total_borrowed' => (clone $baseQuery)->whereIn('trans.status', ['borrowed', 'returned', 'overdue'])->count(),
            'currently_borrowed' => (clone $baseQuery)->where('trans.status', 'borrowed')->whereNull('items.returned_at')->count(),
            'overdue' => (clone $baseQuery)->where(function ($q) use ($now) {
                $q->where('trans.status', 'overdue')
                    ->orWhere(function ($sq) use ($now) {
                        $sq->where('trans.status', 'borrowed')
                            ->where('trans.due_date', '<', $now);
                    });
            })->count(),
            'returned' => (clone $baseQuery)->whereNotNull('items.returned_at')->count(),
        ];
    }

    private function resolveStatus($record)
    {
        if ($record->returned_at) return 'returned';

        if ($record->status === 'overdue' || ($record->status === 'borrowed' && Carbon::parse($record->due_date)->isPast())) {
            return 'overdue';
        }

        if ($record->status === 'pending' && Carbon::parse($record->expires_at)->isPast()) {
            return 'expired';
        }

        return $record->status;
    }
}

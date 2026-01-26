<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardService
{
    public function getDashboardData(User $user): array
    {
        return match ($user->role) {
            'student' => $this->getStudentStats($user),
            'faculty', 'staff' => $this->getNonAttendanceBorrowerStats($user),
            default => [
                'success' => false,
                'error' => 'Role not supported'
            ],
        };
    }

    private function getStudentStats(User $user): array
    {
        $student = DB::table('students')->where('user_id', $user->user_id)->first();

        if (!$student) {
            return $this->emptyResponse('Student Dashboard (No Profile Found)', true);
        }

        $now = Carbon::now();
        $stats = $this->getBasicBorrowCounts('student_id', (int) $student->student_id);

        $daysVisited = DB::table('borrow_transactions')
            ->where('student_id', $student->student_id)
            ->whereMonth('borrowed_at', $now->month)
            ->whereYear('borrowed_at', $now->year)
            ->distinct()
            ->count(DB::raw('DATE(borrowed_at)'));

        return [
            'summary' => [
                'books_borrowed' => (int) $stats->books_borrowed,
                'days_visited'   => (int) $daysVisited,
                'overdue_books'  => (int) $stats->overdue_books,
            ],
            'currently_borrowed_books' => $this->getCurrentlyBorrowedList('student_id', (int) $student->student_id),
            'role_label' => 'Student Dashboard'
        ];
    }

    private function getNonAttendanceBorrowerStats(User $user): array
    {
        $role = $user->role;
        $table = ($role === 'faculty') ? 'faculty' : 'staff';
        $fk = $role . '_id';

        $profile = DB::table($table)->where('user_id', $user->user_id)->first();

        if (!$profile) {
            return $this->emptyResponse(ucfirst($role) . ' Dashboard (No Profile Found)', false);
        }

        $stats = $this->getBasicBorrowCounts($fk, (int) $profile->$fk);

        return [
            'summary' => [
                'books_borrowed' => (int) $stats->books_borrowed,
                'overdue_books'  => (int) $stats->overdue_books,
            ],
            'currently_borrowed_books' => $this->getCurrentlyBorrowedList($fk, (int) $profile->$fk),
            'role_label' => ucfirst($role) . ' Dashboard'
        ];
    }

    private function emptyResponse(string $label, bool $showAttendance): array
    {
        $res = [
            'summary' => [
                'books_borrowed' => 0,
                'overdue_books'  => 0,
            ],
            'currently_borrowed_books' => [],
            'role_label' => $label
        ];
        if ($showAttendance) $res['summary']['days_visited'] = 0;
        return $res;
    }

    private function getBasicBorrowCounts(string $fk, int $id)
    {
        return DB::table('borrow_transaction_items as items')
            ->join('borrow_transactions as trans', 'items.transaction_id', '=', 'trans.transaction_id')
            ->where("trans.$fk", $id)
            ->selectRaw("
                COUNT(CASE WHEN items.status = 'borrowed' THEN 1 END) as books_borrowed,
                COUNT(CASE WHEN (items.status = 'borrowed' OR items.status = 'overdue') AND trans.due_date < ? THEN 1 END) as overdue_books
            ", [Carbon::now()])
            ->first();
    }

    private function getCurrentlyBorrowedList(string $fk, int $id)
    {
        return DB::table('borrow_transaction_items as items')
            ->join('borrow_transactions as trans', 'items.transaction_id', '=', 'trans.transaction_id')
            ->join('books', 'items.book_id', '=', 'books.book_id')
            ->where("trans.$fk", $id)
            ->where('items.status', 'borrowed')
            ->select('books.title', 'books.author', 'trans.due_date', 'items.status', 'books.accession_number')
            ->get();
    }
}

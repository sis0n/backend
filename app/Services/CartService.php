<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CartService
{
    /**
     * add a book to the users specific cart table based on their role
     */
    public function addToCart($user, $bookId)
    {
        // 1. Get dynamic table and ID config
        $config = $this->getCartConfig($user);
        $tableName = $config['table'];
        $idColumn = $config['column'];
        $ownerId = $config['id'];

        if (!$ownerId) {
            return ['status' => 'error', 'message' => 'Profile record not found for this user.'];
        }

        $currentCartCount = DB::table($tableName)
            ->where($idColumn, $ownerId)
            ->whereNull('checked_out_at')
            ->count();

        if ($currentCartCount >= 5) {
            return ['status' => 'error', 'message' => 'Limit reached! You can only add up to 5 books in your cart.'];
        }

        $book = DB::table('books')->where('book_id', $bookId)->first();
        if (!$book || $book->availability !== 'available' || $book->quantity <= 0) {
            return ['status' => 'error', 'message' => 'This book is currently unavailable for borrowing.'];
        }

        $exists = DB::table($tableName)
            ->where($idColumn, $ownerId)
            ->where('book_id', $bookId)
            ->whereNull('checked_out_at')
            ->exists();

        if ($exists) {
            return ['status' => 'error', 'message' => 'This book is already in your cart.'];
        }

        DB::table($tableName)->insert([
            $idColumn => $ownerId,
            'book_id' => $bookId,
            'added_at' => now(),
        ]);

        return ['status' => 'success', 'message' => 'Successfully added to cart!'];
    }

    /**
     * fetch all items in the users cart
     */
    public function getCartItems($user)
    {
        $config = $this->getCartConfig($user);

        return DB::table($config['table'])
            ->join('books', "{$config['table']}.book_id", "=", "books.book_id")
            ->where("{$config['table']}.{$config['column']}", $config['id'])
            ->whereNull("{$config['table']}.checked_out_at")
            ->select(
                "{$config['table']}.cart_id",
                'books.book_id',
                'books.title',
                'books.author',
                'books.cover',
                'books.accession_number'
            )
            ->get();
    }

    /**
     * private helper to determine table and id based on user role.
     */
    private function getCartConfig($user)
    {
        switch (strtolower($user->role)) {
            case 'student':
                $id = DB::table('students')->where('user_id', $user->user_id)->value('student_id');
                return ['table' => 'carts', 'column' => 'student_id', 'id' => $id];

            case 'faculty':
                $id = DB::table('faculty')->where('user_id', $user->user_id)->value('faculty_id');
                return ['table' => 'faculty_carts', 'column' => 'faculty_id', 'id' => $id];

            case 'staff':
                $id = DB::table('staff')->where('user_id', $user->user_id)->value('staff_id');
                return ['table' => 'staff_carts', 'column' => 'staff_id', 'id' => $id];

            default:
                return ['table' => null, 'column' => null, 'id' => null];
        }
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Str;
use Carbon\Carbon;
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

        if (!$config['id']) {
            return [
                'items' => [],
                'total_items' => 0
            ];
        }

        $items = DB::table($config['table'])
            ->join('books', "{$config['table']}.book_id", "=", "books.book_id")
            ->where("{$config['table']}.{$config['column']}", $config['id'])
            ->whereNull("{$config['table']}.checked_out_at")
            ->select(
                "{$config['table']}.cart_id",
                "{$config['table']}.added_at",
                'books.book_id',
                'books.title',
                'books.author',
                'books.cover',
                'books.accession_number',
                'books.call_number',
                'books.subject'
            )
            ->get();

        return [
            'items' => $items,
            'total_items' => $items->count()
        ];
    }

    /**
     * private helper to determine table and id based on user role.
     */
    private function getCartConfig($user)
    {
        switch (strtolower($user->role)) {
            case 'student':
                $id = DB::table('students')->where('user_id', $user->user_id)->value('student_id');
                return [
                    'table' => 'carts',
                    'table_profile' => 'students',
                    'column' => 'student_id',
                    'id' => $id
                ];

            case 'faculty':
                $id = DB::table('faculty')->where('user_id', $user->user_id)->value('faculty_id');
                return [
                    'table' => 'faculty_carts',
                    'table_profile' => 'faculty',
                    'column' => 'faculty_id',
                    'id' => $id
                ];

            case 'staff':
                $id = DB::table('staff')->where('user_id', $user->user_id)->value('staff_id');
                return [
                    'table' => 'staff_carts',
                    'table_profile' => 'staff',
                    'column' => 'staff_id',
                    'id' => $id
                ];

            default:
                return ['table' => null, 'table_profile' => null, 'column' => null, 'id' => null];
        }
    }

    /**
     * remove item from cart
     */
    public function removeFromCart($user, $cartId)
    {
        $config = $this->getCartConfig($user);

        if (!$config['table']) {
            return [
                'success' => false,
                'message' => 'invalid user role'
            ];
        }

        $exists = DB::table($config['table'])
            ->where('cart_id', $cartId)
            ->where($config['column'], $config['id'])
            ->exists();

        if (!$exists) {
            return [
                'success' => false,
                'message' => 'item not found'
            ];
        }

        DB::table($config['table'])->where('cart_id', $cartId)->delete();

        return [
            'success' => true,
            'message' => 'item removed from the cart'
        ];
    }

    public function checkout($user)
    {
        $config = $this->getCartConfig($user);

        if (!$config['id']) {
            return ['success' => false, 'message' => 'User profile not found'];
        }

        $profile = DB::table($config['table_profile'])
            ->join('users', "{$config['table_profile']}.user_id", "=", "users.user_id")
            ->where("{$config['table_profile']}.{$config['column']}", $config['id'])
            ->select("{$config['table_profile']}.*", "users.email", "users.profile_picture")
            ->first();

        $validation = $this->isProfileComplete($profile, $user->role);
        if (!$validation['is_complete']) {
            return [
                'success' => false,
                'message' => 'Incomplete profile: ' . implode(', ', $validation['missing_fields'])
            ];
        }

        return DB::transaction(function () use ($user, $config) {
            $cartItems = DB::table($config['table'])
                ->join('books', "$config[table].book_id", "=", "books.book_id")
                ->where($config['table'] . '.' . $config['column'], $config['id'])
                ->select(
                    'books.book_id',
                    'books.title',
                    'books.author',
                    'books.accession_number',
                    'books.call_number'
                )
                ->get();

            if ($cartItems->isEmpty()) {
                return ['success' => false, 'message' => 'Cart is empty'];
            }

            foreach ($cartItems as $item) {
                $isUnavailable = DB::table('borrow_transaction_items')
                    ->join('borrow_transactions', 'borrow_transaction_items.transaction_id', '=', 'borrow_transactions.transaction_id')
                    ->where('borrow_transaction_items.book_id', $item->book_id)
                    ->whereIn('borrow_transactions.status', ['pending', 'borrowed'])
                    ->whereNull('borrow_transaction_items.returned_at')
                    ->exists();

                if ($isUnavailable) {
                    return ['success' => false, 'message' => "The book '{$item->title}' is unavailable."];
                }
            }

            $existingTransaction = DB::table('borrow_transactions')
                ->where($config['column'], $config['id'])
                ->where('status', 'pending')
                ->first();

            $existingCount = 0;
            if ($existingTransaction) {
                $existingCount = DB::table('borrow_transaction_items')
                    ->where('transaction_id', $existingTransaction->transaction_id)
                    ->count();
            }

            $newItemsCount = $cartItems->count();

            if (($existingCount + $newItemsCount) > 5) {
                return [
                    'success' => false,
                    'message' => "Limit reached. You can only borrow a total of 5 books. (You have {$existingCount} pending, adding {$newItemsCount})"
                ];
            }

            $now = Carbon::now();
            $expiresAt = $now->copy()->addMinutes(15);
            $dueDate = $now->copy()->addDays(3);

            if ($existingTransaction) {
                $transactionId = $existingTransaction->transaction_id;
                $transactionCode = $existingTransaction->transaction_code;

                DB::table('borrow_transactions')
                    ->where('transaction_id', $transactionId)
                    ->update([
                        'expires_at' => $expiresAt,
                        'generated_at' => $now
                    ]);
            } else {
                $transactionCode = strtoupper(Str::random(13));
                $transactionId = DB::table('borrow_transactions')->insertGetId([
                    $config['column']   => $config['id'],
                    'transaction_code' => $transactionCode,
                    'generated_at'     => $now,
                    'expires_at'       => $expiresAt,
                    'status'           => 'pending',
                    'due_date'         => $dueDate,
                ]);
            }

            foreach ($cartItems as $item) {
                $existsInTransaction = DB::table('borrow_transaction_items')
                    ->where('transaction_id', $transactionId)
                    ->where('book_id', $item->book_id)
                    ->exists();

                if (!$existsInTransaction) {
                    DB::table('borrow_transaction_items')->insert([
                        'transaction_id' => $transactionId,
                        'book_id'        => $item->book_id,
                        'returned_at'    => null,
                    ]);
                }
            }

            DB::table($config['table'])->where($config['column'], $config['id'])->delete();

            $allBooks = DB::table('borrow_transaction_items')
                ->join('books', 'borrow_transaction_items.book_id', '=', 'books.book_id')
                ->where('borrow_transaction_items.transaction_id', $transactionId)
                ->select('books.book_id', 'books.title', 'books.author', 'books.accession_number', 'books.call_number')
                ->get();

            return [
                'success' => true,
                'message' => $existingTransaction ? 'Books added to your existing pending transaction' : 'Checkout successful',
                'data' => [
                    'transaction_code' => $transactionCode,
                    'expires_at'       => $expiresAt->toDateTimeString(),
                    'books'            => $allBooks
                ]
            ];
        });
    }

    private function isProfileComplete($profile, $role)
    {
        if (!$profile) return ['is_complete' => false, 'missing_fields' => ['Profile record not found']];

        $missing = [];
        $role = strtolower($role);

        if (empty($profile->profile_picture)) {
            $missing[] = 'Profile Picture';
        }
        if (empty($profile->email)) {
            $missing[] = 'Email Address';
        }

        if ($role === 'student') {
            if (empty($profile->course_id))         $missing[] = 'Course';
            if (empty($profile->year_level))        $missing[] = 'Year Level';
            if (empty($profile->section))           $missing[] = 'Section';
            if (empty($profile->contact))           $missing[] = 'Contact';
            if (empty($profile->registration_form)) $missing[] = 'Registration Form';
        } elseif ($role === 'faculty') {
            if (empty($profile->college_id))        $missing[] = 'College';
            if (empty($profile->contact))           $missing[] = 'Contact';
        } elseif ($role === 'staff') {
            // Required: Position, Contact
            if (empty($profile->position))          $missing[] = 'Position';
            if (empty($profile->contact))           $missing[] = 'Contact';
        }

        return [
            'is_complete' => empty($missing),
            'missing_fields' => $missing
        ];
    }
}

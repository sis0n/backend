<?php

namespace App\Services;

use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CartService
{
    /**
     * Add a book to the centralized 'carts' table.
     */
    public function addToCart($user, $bookId)
    {
        $userId = $user->user_id;

        // check current cart limit
        $currentCartCount = DB::table('carts')
            ->where('user_id', $userId)
            ->whereNull('checked_out_at')
            ->count();

        if ($currentCartCount >= 5) {
            return ['status' => 'error', 'message' => 'Limit reached! You can only add up to 5 books in your cart.'];
        }

        // chgeck book availability
        $book = DB::table('books')->where('book_id', $bookId)->first();
        if (!$book || $book->availability !== 'available' || $book->quantity <= 0) {
            return ['status' => 'error', 'message' => 'This book is currently unavailable for borrowing.'];
        }

        $exists = DB::table('carts')
            ->where('user_id', $userId)
            ->where('book_id', $bookId)
            ->whereNull('checked_out_at')
            ->exists();

        if ($exists) {
            return ['status' => 'error', 'message' => 'This book is already in your cart.'];
        }

        // insert into centralized carts table
        DB::table('carts')->insert([
            'user_id' => $userId,
            'book_id' => $bookId,
            'added_at' => now(),
        ]);

        return ['status' => 'success', 'message' => 'Successfully added to cart!'];
    }

    /**
     * Fetch items in the user's cart.
     */
    public function getCartItems($user)
    {
        $items = DB::table('carts')
            ->join('books', "carts.book_id", "=", "books.book_id")
            ->where("carts.user_id", $user->user_id)
            ->whereNull("carts.checked_out_at")
            ->select(
                "carts.cart_id",
                "carts.added_at",
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
     * Remove item from centralized cart.
     */
    public function removeFromCart($user, $cartId)
    {
        $deleted = DB::table('carts')
            ->where('cart_id', $cartId)
            ->where('user_id', $user->user_id)
            ->delete();

        if (!$deleted) {
            return ['success' => false, 'message' => 'Item not found in your cart.'];
        }

        return ['success' => true, 'message' => 'Item removed from the cart.'];
    }

    /**
     * Checkout centralized cart and create a transaction.
     */
    public function checkout($user)
    {
        $profileData = $this->getUserProfileData($user);

        if (!$profileData['id']) {
            return ['success' => false, 'message' => 'User profile record not found.'];
        }

        // profile completion check
        $validation = $this->isProfileComplete($profileData['profile'], $user->role);
        if (!$validation['is_complete']) {
            return [
                'success' => false,
                'message' => 'Incomplete profile: ' . implode(', ', $validation['missing_fields'])
            ];
        }

        return DB::transaction(function () use ($user, $profileData) {
            $cartItems = DB::table('carts')
                ->join('books', "carts.book_id", "=", "books.book_id")
                ->where('carts.user_id', $user->user_id)
                ->whereNull('carts.checked_out_at')
                ->select('books.book_id', 'books.title')
                ->get();

            if ($cartItems->isEmpty()) {
                return ['success' => false, 'message' => 'Cart is empty'];
            }

            // check book availability in real time
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

            // check existing pending transactions for this specific borrower
            $existingTransaction = DB::table('borrow_transactions')
                ->where($profileData['column'], $profileData['id'])
                ->where('status', 'pending')
                ->first();

            $existingCount = $existingTransaction ? DB::table('borrow_transaction_items')->where('transaction_id', $existingTransaction->transaction_id)->count() : 0;
            $newItemsCount = $cartItems->count();

            if (($existingCount + $newItemsCount) > 5) {
                return [
                    'success' => false,
                    'message' => "Limit reached. Max 5 books total. (Pending: {$existingCount}, Adding: {$newItemsCount})"
                ];
            }

            $now = Carbon::now();
            $expiresAt = $now->copy()->addMinutes(15);
            $dueDate = $now->copy()->addDays(3);

            if ($existingTransaction) {
                $transactionId = $existingTransaction->transaction_id;
                $transactionCode = $existingTransaction->transaction_code;
                DB::table('borrow_transactions')->where('transaction_id', $transactionId)->update(['expires_at' => $expiresAt, 'generated_at' => $now]);
            } else {
                $transactionCode = strtoupper(Str::random(13));
                $transactionId = DB::table('borrow_transactions')->insertGetId([
                    $profileData['column'] => $profileData['id'],
                    'transaction_code' => $transactionCode,
                    'generated_at'     => $now,
                    'expires_at'       => $expiresAt,
                    'status'           => 'pending',
                    'due_date'         => $dueDate,
                ]);
            }

            foreach ($cartItems as $item) {
                DB::table('borrow_transaction_items')->updateOrInsert(
                    ['transaction_id' => $transactionId, 'book_id' => $item->book_id],
                    ['returned_at' => null]
                );
            }

            DB::table('carts')->where('user_id', $user->user_id)->delete();

            return [
                'success' => true,
                'message' => 'Checkout successful',
                'data' => [
                    'transaction_code' => $transactionCode,
                    'expires_at'       => $expiresAt->toDateTimeString(),
                ]
            ];
        });
    }

    /**
     * Helper to get profile ID and column for transactions.
     */
    private function getUserProfileData($user)
    {
        $role = strtolower($user->role);
        $table = match ($role) {
            'student' => 'students',
            'faculty' => 'faculty',
            'staff'   => 'staff',
            default   => null
        };

        if (!$table) return ['id' => null, 'column' => null, 'profile' => null];

        $column = $role . '_id';
        $profile = DB::table($table)->where('user_id', $user->user_id)->first();

        return [
            'id'     => $profile ? $profile->$column : null,
            'column' => $column,
            'profile' => $profile
        ];
    }

    private function isProfileComplete($profile, $role)
    {
        if (!$profile) return ['is_complete' => false, 'missing_fields' => ['Profile record not found']];
        $missing = [];
        $role = strtolower($role);

        if ($role === 'student') {
            if (empty($profile->course_id)) $missing[] = 'Course';
            if (empty($profile->year_level)) $missing[] = 'Year Level';
            if (empty($profile->contact)) $missing[] = 'Contact';
        } elseif (in_array($role, ['faculty', 'staff'])) {
            if (empty($profile->contact)) $missing[] = 'Contact';
        }

        return ['is_complete' => empty($missing), 'missing_fields' => $missing];
    }
}

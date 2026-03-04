<?php

namespace App\Services;

use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;

class CartService
{
    /**
     * Get policy for a specific role.
     */
    private function getPolicy($role)
    {
        return DB::table('library_policies')
            ->where('role', strtolower($role))
            ->first();
    }

    /**
     * Add a book to the centralized 'carts' table.
     */
    public function addToCart($user, $bookId)
    {
        $userId = $user->user_id;

        // Check book availability
        $book = DB::table('books')->where('book_id', $bookId)->first();
        if (!$book || $book->availability !== 'available' || $book->quantity <= 0) {
            return ['status' => 'error', 'message' => 'This book is currently unavailable for borrowing.'];
        }

        // Check if already in cart
        $exists = DB::table('carts')
            ->where('user_id', $userId)
            ->where('book_id', $bookId)
            ->whereNull('checked_out_at')
            ->exists();

        if ($exists) {
            return ['status' => 'error', 'message' => 'This book is already in your cart.'];
        }

        // Insert into centralized carts table
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
     * Remove item from cart.
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
     * Checkout process with dynamic policies, QR code generation and Audit Logging.
     */
    public function checkout($user)
    {
        $profileData = $this->getUserProfileData($user);

        if (!$profileData['id']) {
            return ['success' => false, 'message' => 'User profile record not found.'];
        }

        // profile completion check (STRICT VALIDATION)
        $validation = $this->isProfileComplete($user, $profileData['profile']);
        if (!$validation['is_complete']) {
            return [
                'success' => false,
                'message' => 'Incomplete profile requirements: ' . implode(', ', $validation['missing_fields'])
            ];
        }

        $policy = $this->getPolicy($user->role);
        $maxBooks = $policy ? $policy->max_books : 5;
        $duration = $policy ? $policy->borrow_duration_days : 3;

        return DB::transaction(function () use ($user, $profileData, $maxBooks, $duration) {
            $cartItems = DB::table('carts')
                ->join('books', "carts.book_id", "=", "books.book_id")
                ->where('carts.user_id', $user->user_id)
                ->whereNull('carts.checked_out_at')
                ->select('books.book_id', 'books.title')
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
                ->where($profileData['column'], $profileData['id'])
                ->where('status', 'pending')
                ->first();

            $existingCount = $existingTransaction ? DB::table('borrow_transaction_items')->where('transaction_id', $existingTransaction->transaction_id)->count() : 0;
            $newItemsCount = $cartItems->count();

            if (($existingCount + $newItemsCount) > $maxBooks) {
                return [
                    'success' => false,
                    'message' => "Limit reached. Your role allows max {$maxBooks} books total. (Pending: {$existingCount}, Adding: {$newItemsCount})"
                ];
            }

            $now = Carbon::now('Asia/Manila');
            $expiresAt = $now->copy()->addMinutes(15);
            $dueDate = $now->copy()->addDays($duration);

            if ($existingTransaction) {
                $transactionId = $existingTransaction->transaction_id;
                $transactionCode = $existingTransaction->transaction_code;
                DB::table('borrow_transactions')->where('transaction_id', $transactionId)->update(['expires_at' => $expiresAt, 'generated_at' => $now, 'due_date' => $dueDate]);
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

            $qrFolder = 'uploads/qrcodes';
            $qrFileName = $transactionCode . '.svg';
            $qrPath = $qrFolder . '/' . $qrFileName;

            if (!Storage::disk('public')->exists($qrFolder)) {
                Storage::disk('public')->makeDirectory($qrFolder);
            }

            $qrCodeImage = QrCode::size(300)->errorCorrection('H')->generate($transactionCode);
            Storage::disk('public')->put($qrPath, $qrCodeImage);

            DB::table('borrow_transactions')->where('transaction_id', $transactionId)->update(['qrcode' => $qrPath]);

            DB::table('carts')->where('user_id', $user->user_id)->delete();

            AuditTrailService::log(
                $user->user_id,
                'CHECKOUT',
                'TRANSACTIONS',
                $transactionCode,
                "User checked out {$newItemsCount} book(s). Transaction Code: {$transactionCode}"
            );

            return [
                'success' => true,
                'message' => 'Checkout successful',
                'data' => [
                    'transaction_code' => $transactionCode,
                    'expires_at'       => $expiresAt->toDateTimeString(),
                    'qrcode_url'       => url('storage/' . $qrPath),
                ]
            ];
        });
    }

    /**
     * Check current pending transaction status for the user.
     */
    public function checkStatus($user): array
    {
        $now = Carbon::now('Asia/Manila');
        
        DB::table('borrow_transactions')
            ->where('status', 'pending')
            ->where('expires_at', '<', $now)
            ->update(['status' => 'expired']);

        $profileData = $this->getUserProfileData($user);
        if (!$profileData['id']) {
            return ['success' => true, 'status' => 'none'];
        }

        $pending = DB::table('borrow_transactions')
            ->where($profileData['column'], $profileData['id'])
            ->where('status', 'pending')
            ->first();

        if (!$pending) {
            return ['success' => true, 'status' => 'none'];
        }

        $fullName = trim("{$user->first_name} {$user->middle_name} {$user->last_name} {$user->suffix}");
        $userData = [
            'name' => $fullName,
            'role' => $user->role,
        ];

        if ($user->role === 'student') {
            $course = DB::table('courses')->where('course_id', $profileData['profile']->course_id)->first();
            $userData['student_number'] = $profileData['profile']->student_number;
            $userData['year_level']     = $profileData['profile']->year_level;
            $userData['section']        = $profileData['profile']->section;
            $userData['course']         = $course ? $course->course_code : 'N/A';
        } elseif ($user->role === 'faculty') {
            $college = DB::table('colleges')->where('college_id', $profileData['profile']->college_id)->first();
            $userData['unique_id'] = $profileData['profile']->unique_faculty_id;
            $userData['college']   = $college ? $college->college_code : 'N/A';
        } elseif ($user->role === 'staff') {
            $userData['employee_id'] = $profileData['profile']->employee_id;
            $userData['position']    = $profileData['profile']->position;
        }

        $books = DB::table('borrow_transaction_items as items')
            ->join('books', 'items.book_id', '=', 'books.book_id')
            ->where('items.transaction_id', $pending->transaction_id)
            ->select('books.book_id', 'books.title', 'books.author', 'books.accession_number', 'books.call_number')
            ->get();

        return [
            'success'          => true,
            'status'           => 'pending',
            'transaction_code' => $pending->transaction_code,
            'generated_at'     => $pending->generated_at,
            'expires_at'       => $pending->expires_at,
            'qrcode_url'       => $pending->qrcode ? url('storage/' . $pending->qrcode) : null,
            'user_details'     => $userData,
            'books'            => $books
        ];
    }

    public function removeMultipleFromCart($user, array $cartIds)
    {
        $deletedCount = DB::table('carts')
            ->whereIn('cart_id', $cartIds)
            ->where('user_id', $user->user_id)
            ->delete();

        return [
            'success' => true,
            'message' => "successfully removed {$deletedCount} items from the cart."
        ];
    }

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

    /**
     * Strict Validation for checkout.
     */
    private function isProfileComplete($user, $profile)
    {
        if (!$profile) return ['is_complete' => false, 'missing_fields' => ['Profile record not found']];
        
        $missing = [];
        $role = strtolower($user->role);

        // Common Fields (From Users Table)
        if (empty($user->profile_picture)) $missing[] = 'Profile Picture';
        if (empty($user->email))           $missing[] = 'Email Address';

        // Role Specific Fields (From Profile Tables)
        if ($role === 'student') {
            if (empty($profile->course_id))         $missing[] = 'Course';
            if (empty($profile->year_level))        $missing[] = 'Year Level';
            if (empty($profile->section))           $missing[] = 'Section';
            if (empty($profile->contact))           $missing[] = 'Contact Number';
            if (empty($profile->registration_form)) $missing[] = 'Registration Form';
        } elseif ($role === 'faculty') {
            if (empty($profile->college_id))        $missing[] = 'Department/College';
            if (empty($profile->contact))           $missing[] = 'Contact Number';
        } elseif ($role === 'staff') {
            if (empty($profile->position))          $missing[] = 'Position';
            if (empty($profile->contact))           $missing[] = 'Contact Number';
        }

        // Global check for profile_updated flag
        if ($profile->profile_updated == 0) {
            $missing[] = 'General Profile Setup';
        }

        return [
            'is_complete' => empty($missing),
            'missing_fields' => $missing
        ];
    }
}

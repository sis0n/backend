<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Illuminate\Support\Facades\Storage;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\BorrowingHistoryController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AcademicController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/refresh', [AuthController::class, 'refresh']);

Route::post('/upload-file', function (Request $request) {
    if ($request->has('file') && $request->has('filename') && $request->has('folder')) {
        $folder = trim($request->folder, '/');
        $path = 'uploads/' . $folder . '/' . $request->filename;
        Storage::disk('public')->put($path, base64_decode($request->file));
        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => asset('storage/' . $path)
        ]);
    }
    return response()->json(['success' => false, 'message' => 'Incomplete data'], 400);
});

Route::post('/upload-qr', function (Request $request) {
    if ($request->has('image') && $request->has('filename')) {
        $path = 'uploads/qrcodes/' . $request->filename;
        Storage::disk('public')->put($path, base64_decode($request->image));
        return response()->json(['success' => true]);
    }
    return response()->json(['success' => false, 'message' => 'Missing data'], 400);
});

Route::middleware('auth:api')->group(function() {

    Route::get('/courses', [AcademicController::class, 'getCourses']);
    Route::get('/colleges', [AcademicController::class, 'getColleges']);
    
    Route::post('/changePassword', [AuthController::class, 'changePassword']);
    Route::get('/attendance/history', [AttendanceController::class, 'getHistory']);
    Route::get('/borrowingHistory', [BorrowingHistoryController::class, 'getMyHistory']);
    Route::post('/cart/checkout', [CartController::class, 'checkout']);
    Route::get('/cart/status', [CartController::class, 'checkStatus']);
    Route::delete('/cart/{id}', [CartController::class, 'destroy']);
    Route::post('/cart/add', [CartController::class, 'add']);
    Route::post('/cart/bulkDelete', [CartController::class, 'bulkDestroy']);
    Route::get('/cart', [CartController::class, 'index']);
    Route::get('/books', [BookController::class, 'index']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile/update', [ProfileController::class, 'update']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    protected $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    public function add(Request $request)
    {
        $request->validate([
            'book_id' => 'required|integer'
        ]);

        $result = $this->cartService->addToCart($request->user(), $request->book_id);

        return response()->json($result);
    }

    public function index(Request $request)
    {
        $cartData = $this->cartService->getCartItems($request->user());

        return response()->json([
            'success' => true,
            'data' => $cartData['items'],
            'total_items' => $cartData['total_items']
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $result = $this->cartService->removeFromCart($request->user(), $id);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);
        }

        return response()->json($result);
    }

    public function checkout(Request $request)
    {
        $result = $this->cartService->checkout($request->user());

        return response()->json($result, $result['success'] ? 200 : 400);
    }
}

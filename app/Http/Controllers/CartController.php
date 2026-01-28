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
        $items = $this->cartService->getCartItems($request->user());

        return response()->json([
            'success' => true,
            'data' => $items
        ]);
    }
}

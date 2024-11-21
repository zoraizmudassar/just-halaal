<?php

namespace App\Http\Controllers\web;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Item; // Assuming this is the model for products or items
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Razorpay\Api\Addon;

class CartController extends Controller
{
    public function add(Request $request)
    {
        // Validate the request
        $request->validate([
            'item_id' => 'required|integer|exists:items,id',
            'quantity' => 'required|integer|min:1',
            'addons' => 'nullable|array',
            'addons.*' => 'integer|exists:addons,id', // Ensure each addon exists
            'variations' => 'nullable|array',
            'variations.*' => 'string', // Adjust validation based on your variations structure
        ]);

        // Check if the user is logged in
        if (!Auth::check()) {
            return redirect()->back()->with('error', 'Please login to add items to your cart.');
        }

        // Add item to the cart logic
        $cart = session()->get('cart', []);
        $itemId = $request->item_id;
        $quantity = $request->quantity;
        $addons = $request->addons ?? [];
        $variations = $request->variations ?? [];

        // Fetch the store ID for the item being added
        $itemStoreId = \App\Models\Item::find($itemId)->store_id;

        // Check if the cart already contains items
        if (!empty($cart)) {
            // Get the store ID of the first item in the cart
            $existingStoreId = \App\Models\Item::find(array_key_first($cart))->store_id;

            // If the store IDs do not match, show an error
            if ($existingStoreId !== $itemStoreId) {
                return redirect()->back()->with('error', 'You can only add products from the same store to your cart.');
            }
        }

        // Check if the item already exists in the cart
        if (isset($cart[$itemId])) {
            // Update quantity and addons/variations for the existing item
            $cart[$itemId]['quantity'] += $quantity;
            $cart[$itemId]['addons'] = array_unique(array_merge($cart[$itemId]['addons'], $addons));
            $cart[$itemId]['variations'] = array_unique(array_merge($cart[$itemId]['variations'], $variations));
        } else {
            // Add new item with addons and variations
            $cart[$itemId] = [
                'quantity' => $quantity,
                'addons' => $addons,
                'variations' => $variations,
            ];
        }

        // Save the cart back to the session
        session()->put('cart', $cart);

        // Handle the response
        return redirect()->back()->with('success', 'Item added to cart successfully.');
    }


public function show()
{
    // Fetch cart data from session
    $cart = session()->get('cart', []);

    // Prepare cart items with product details, variations, and addons
    $cartDetails = collect($cart)->map(function ($item, $itemId) {
        // Fetch product details
        $product = Item::find($itemId);

        // Fetch addons details if available
        $addons = !empty($item['addons'])
            ? Addon::whereIn('id', $item['addons'])->get()
            : collect();

        return [
            'product' => $product,
            'quantity' => $item['quantity'],
            'variations' => $item['variations'] ?? [],
            'addons' => $addons,
        ];
    });

    // Calculate subtotal
    $subtotal = $cartDetails->reduce(function ($carry, $item) {
        $productPrice = $item['product']->price ?? 0;
        $addonsPrice = $item['addons']->sum('price');
        $itemTotal = $item['quantity'] * ($productPrice + $addonsPrice);
        return $carry + $itemTotal;
    }, 0);

    return view('web.cart.index', compact('cartDetails', 'subtotal'));
}

public function updateCart(Request $request)
{
    $request->validate([
        'item_id' => 'required|integer|exists:items,id',
        'quantity' => 'required|integer|min:1',
    ]);

    $cart = session()->get('cart', []);
    $itemId = $request->item_id;
    $quantity = $request->quantity;

    if (isset($cart[$itemId])) {
        $cart[$itemId]['quantity'] = $quantity;
        session()->put('cart', $cart);
    }

    return response()->json([
        'success' => true,
        'message' => 'Cart updated successfully.',
    ]);
}

public function removeFromCart(Request $request)
{
    $request->validate([
        'item_id' => 'required|integer|exists:items,id',
    ]);

    $cart = session()->get('cart', []);
    $itemId = $request->item_id;

    if (isset($cart[$itemId])) {
        unset($cart[$itemId]);
        session()->put('cart', $cart);
    }

    return response()->json([
        'success' => true,
        'message' => 'Item removed from cart successfully.',
    ]);
}

}

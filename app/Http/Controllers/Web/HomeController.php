<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AddOn;
use App\Models\Category;
use App\Models\Item;
use App\Models\Store;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;


class HomeController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('products')
        ->has('products')
        ->inRandomOrder()
        ->take(5)
        ->get();

        // Fetch products that belong to the filtered categories
        $products = Item::all();


        $stores = Store::all();
// dd($stores);
        // Return the view with categories
        return view('web.home.index', compact('categories', 'products', 'stores'));
    }

    public function show($id)
    {
        $product = Item::findOrFail($id);

        $addOnIds = json_decode($product->add_ons, true);

        $addons = AddOn::whereIn('id', $addOnIds)->get();

        $relatedProducts = Item::where('category_id', $product->category_id)
            ->where('id', '!=', $id)
            ->take(4)
            ->get();
            $store = $product->store;
        return view('web.products.detail', compact('product', 'relatedProducts', 'addons','store'));
    }
    public function restaurant(Request $request, $id)
    {
        // Find the store by ID or fail
        $store = Store::findOrFail($id);

        // Get filter inputs
        $categoryId = $request->input('category_id');
        $search = $request->input('search');
        $maxPrice = $request->input('max_price', 500); // Default max price is 500
        $minPrice = $request->input('min_price', 0); // Default min price is 0

        // Query to fetch products for the store
        $query = Item::where('store_id', $store->id);

        // Filter by category if provided
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        // Filter by search keyword if provided
        if ($search) {
            $query->where('name', 'LIKE', '%' . $search . '%');
        }

        // Filter by price range
        $query->whereBetween('price', [$minPrice, $maxPrice]);

        // Paginate the filtered products
        $products = $query->paginate(10);

        // Fetch all unique category IDs for the store's products
        $allCategoryIds = Item::where('store_id', $store->id)->pluck('category_id')->unique();

        // Fetch categories based on those IDs
        $categories = Category::whereIn('id', $allCategoryIds)->get();

        return view('web.shop.index', compact('store', 'products', 'categories', 'categoryId', 'search', 'minPrice', 'maxPrice'));
    }

    public function showCart()
    {
        $cart = session()->get('cart', []);
        $cartCount = array_sum($cart);

        return view('web.partials.header', compact('cartCount'));
    }
}

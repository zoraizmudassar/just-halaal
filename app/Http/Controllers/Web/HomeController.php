<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\Store;

class HomeController extends Controller
{
    public function index()
    {
       $categories = Category::withCount('products')
        ->orderBy('products_count', 'desc')
        ->take(4)
        ->get();

    // Fetch products that belong to the filtered categories
    $products = Item::whereIn('category_id', $categories->pluck('id'))->get();
    $stores = Store::all();
        // Return the view with categories
        return view('web.home.index', compact('categories', 'products', 'stores'));
    }

    public function show($id)
    {
        // Fetch the product by ID
        $product = Item::findOrFail($id);

        // Return a view with the product
        return view('web.products.detail', compact('product'));
    }
}

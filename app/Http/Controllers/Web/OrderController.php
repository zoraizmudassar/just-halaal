<?php

namespace App\Http\Controllers\web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class OrderController extends Controller
{
    public function index()
    {
        // Create a collection of dummy orders
        $orders = collect([
            (object)[
                'id' => 1,
                'status' => 'Pending',
                'total' => 100.50,
                'created_at' => now(),
            ],
            (object)[
                'id' => 2,
                'status' => 'Completed',
                'total' => 200.75,
                'created_at' => now()->subDays(1),
            ],
            (object)[
                'id' => 3,
                'status' => 'Cancelled',
                'total' => 50.00,
                'created_at' => now()->subDays(2),
            ],
        ]);

        // Paginate the dummy data manually (10 per page)
        $orders = new \Illuminate\Pagination\LengthAwarePaginator(
            $orders->forPage(1, 10), // Items for the current page
            $orders->count(),       // Total number of items
            10,                     // Items per page
            1                       // Current page
        );

        return view('web.auth.order', compact('orders'));
    }
}

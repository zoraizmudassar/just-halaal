<?php

namespace App\Http\Controllers\web\stripe;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StripeController extends Controller
{
    public function index()
    {
        return view('stripe');
    }

    public function processPayment(Request $request)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));

        Charge::create([
            "amount" => 1000, // Amount in cents ($10.00)
            "currency" => "usd",
            "source" => $request->stripeToken,
            "description" => "Test payment from Laravel app",
        ]);

        return back()->with('success', 'Payment successful!');
    }
}

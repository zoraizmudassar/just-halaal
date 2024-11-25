<?php

namespace App\Http\Controllers\web\stripe;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Stripe\Charge;
use Stripe\Stripe;

class StripeController extends Controller
{
    public function showPaymentForm()
    {
        return view('web.auth.stripe.payment');
    }

    // Process the payment
    public function processPayment(Request $request)
    {
        // Validate the request
        $request->validate([
            'stripeToken' => 'required',
        ]);

        // Set the Stripe API key
        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            // Create the charge on Stripe's servers
            $charge = Charge::create([
                'amount' => 1000, // Amount in cents ($10.00)
                'currency' => 'usd',
                'source' => $request->stripeToken,
                'description' => 'Test payment from Laravel app',
            ]);

            // Payment successful
            return redirect()->route('payment.form')->with('success', 'Payment Successful!');
        } catch (\Exception $e) {
            // Payment failed
            return redirect()->route('payment.form')->with('error', $e->getMessage());
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\UserCard;
use Illuminate\Http\Request;
use Stripe\Customer;
use Stripe\PaymentMethod;
use Stripe\Stripe;

class StripeController extends Controller
{
    public function registerCard(Request $request)
    {
        $request->validate([
            'payment_method' => 'required|string'
        ]);

        $user = auth()->user();
        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            if (!$user->stripe_customer_id) {
                $customer = Customer::create([
                    'email' => $user->email,
                    'name' => $user->first_name . ' ' . $user->last_name,
                ]);
                $user->stripe_customer_id = $customer->id;
                $user->save();
            }

            $paymentMethod = PaymentMethod::retrieve($request->payment_method);
            $paymentMethod->attach(['customer' => $user->stripe_customer_id]);

            UserCard::create([
                'user_id' => $user->id,
                'stripe_card_id' => $paymentMethod->id,
                'last4' => $paymentMethod->card->last4,
                'brand' => $paymentMethod->card->brand,
            ]);

            return response()->json(['message' => 'Tarjeta registrada con éxito', 'stripe_card_id' => $paymentMethod->id], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

}

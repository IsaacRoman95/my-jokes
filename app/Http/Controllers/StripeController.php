<?php

namespace App\Http\Controllers;

use App\UserCard;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Log;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\Stripe;

class StripeController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function registerCard(Request $request)
    {
        $request->validate([
            'payment_method' => 'required|string'
        ]);

        $user = auth()->user();

        try {
            if (!$user->stripe_customer_id) {
                $customer = Customer::create([
                    'email' => $user->email,
                    'name' => $user->first_name . ' ' . $user->last_name,
                ]);
                $user->update(['stripe_customer_id' => $customer->id]);
            }

            if (
                UserCard::where('user_id', $user->id)
                    ->where('stripe_card_id', $request->payment_method)
                    ->exists()
            ) {
                return response()->json(['error' => 'Esta tarjeta ya está registrada'], 400);
            }

            $paymentMethod = PaymentMethod::retrieve($request->payment_method);
            $paymentMethod->attach(['customer' => $user->stripe_customer_id]);

            UserCard::create([
                'user_id' => $user->id,
                'stripe_card_id' => $paymentMethod->id,
                'last4' => $paymentMethod->card->last4,
                'brand' => $paymentMethod->card->brand,
            ]);

            return response()->json([
                'message' => 'Tarjeta registrada con éxito',
                'stripe_card_id' => $paymentMethod->id
            ], 201);
        } catch (ApiErrorException $e) {
            Log::error("Stripe error en registerCard: " . $e->getMessage());
            return response()->json(['error' => 'No se pudo registrar la tarjeta'], 500);
        }
    }

    public function listCards()
    {
        $user = auth()->user();
        $cards = $user->userCards()->select(['id', 'stripe_card_id', 'last4', 'brand'])->get();

        return response()->json(['cards' => $cards], 200);
    }

    public function chargeCard(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'card_id' => 'required|exists:user_cards,id'
        ], [
            'card_id.exists' => 'La tarjeta seleccionada no existe.'
        ]);

        $user = auth()->user();

        $card = UserCard::where('id', $request->card_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$card) {
            return response()->json(["error" => "Tarjeta no encontrada"], 404);
        }

        try {
            if (!$user->stripe_customer_id) {
                return response()->json(['error' => 'El usuario no tiene una cuenta en Stripe'], 400);
            }

            $paymentIntent = PaymentIntent::create([
                "amount" => $request->amount * 100,
                "currency" => "usd",
                "customer" => $user->stripe_customer_id,
                "payment_method" => $card->stripe_card_id,
                "off_session" => true,
                "confirm" => true
            ]);

            return response()->json([
                "message" => "Pago exitoso",
                "payment_intent_id" => $paymentIntent->id,
                "status" => $paymentIntent->status,
                "amount" => $paymentIntent->amount / 100
            ], 200);
        } catch (ApiErrorException $e) {
            Log::error("Stripe error en chargeCard: " . $e->getMessage());
            return response()->json(["error" => "No se pudo procesar el pago"], 500);
        }
    }

}

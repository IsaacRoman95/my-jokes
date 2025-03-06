<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\UserCard;
use Illuminate\Support\Facades\Http;

class HomeController extends Controller
{
    public function home(Request $request)
    {
        $user = auth()->user();

        $userCard = UserCard::where('user_id', $user->id)->first();
        if (!$userCard) {
            return response()->json(['message' => 'NO_CARD'], 400);
        }

        $response = Http::get('https://v2.jokeapi.dev/joke/Any');
        $joke = $response->json();

        return response()->json([
            'joke' => $joke,
        ], 200);
    }
}

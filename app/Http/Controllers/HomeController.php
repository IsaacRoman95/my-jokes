<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use App\UserCard;
use Illuminate\Support\Facades\Http;

class HomeController extends Controller
{
    public function home(Request $request)
    {
        $user = auth()->user();

        if (!UserCard::where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'NO_CARD'], 400);
        }

        try {
            $response = Http::get('https://v2.jokeapi.dev/joke/Any');

            if (!$response->successful() || empty($response->json())) {
                return response()->json([
                    'message' => 'NO_JOKE_AVAILABLE',
                    'error' => 'No se pudo obtener un chiste en este momento. Intenta más tarde.'
                ], 500);
            }

            $jokeData = $response->json();

            if (!isset($jokeData['type'])) {
                return response()->json([
                    'message' => 'INVALID_JOKE_FORMAT',
                    'error' => 'La API de chistes devolvió un formato inesperado.'
                ], 500);
            }

            return response()->json([
                'joke' => $jokeData,
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'API_ERROR',
                'error' => 'Ocurrió un error al intentar obtener el chiste.',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}

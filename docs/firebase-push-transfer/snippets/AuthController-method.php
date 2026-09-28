<?php

// A integrer dans le controleur d'authentification existant.
public function updateFcmToken(\Illuminate\Http\Request $request)
{
    $validated = $request->validate([
        'fcm_token' => ['required', 'string', 'max:4096'],
    ]);

    $user = $request->user();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Utilisateur non authentifie.',
        ], 401);
    }

    $user->update([
        'fcm_token' => $validated['fcm_token'],
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Token FCM mis a jour avec succes.',
        'data' => [
            'user_id' => $user->id,
        ],
    ]);
}


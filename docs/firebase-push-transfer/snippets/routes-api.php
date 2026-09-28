<?php

use App\Http\Controllers\NotificationController;

// Fusionner dans le groupe /api/v1 protege par le middleware du projet.
Route::middleware('auth.multiple')->group(function () {
    Route::post('update-fcm-token', [AuthController::class, 'updateFcmToken']);
    Route::post('send-notification-to-current-user', [NotificationController::class, 'sendToCurrentUser']);

    // Ajouter ici le middleware/controle de role administrateur du projet cible.
    Route::post('send-notification-to-user', [NotificationController::class, 'sendToUser']);
    Route::post('send-notification-to-multiple-users', [NotificationController::class, 'sendToMultipleUsers']);
    Route::post('send-notification-to-all-users', [NotificationController::class, 'sendToAllUsers']);
});


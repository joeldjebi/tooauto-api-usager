<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use App\Models\User;
use App\Models\Chauffeur;
use Illuminate\Support\Facades\Log;

class FirebaseNotificationService
{
    protected $messaging;

    public function __construct()
    {
        try {
            $credentialsPath = storage_path('app/firebase-credentials.json');

            if (!file_exists($credentialsPath)) {
                Log::error('Fichier de credentials Firebase introuvable: ' . $credentialsPath);
                throw new \Exception('Fichier de credentials Firebase introuvable');
            }

            $factory = (new Factory)
                ->withServiceAccount($credentialsPath);

            $this->messaging = $factory->createMessaging();
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'initialisation de Firebase: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Envoyer une notification à un utilisateur spécifique
     * Supporte les modèles User et Chauffeur
     *
     * @param User|Chauffeur $user
     * @param string $title
     * @param string $body
     * @param array $data
     * @return bool
     */
    public function sendToUser($user, $title, $body, $data = [])
    {
        // Vérifier que l'utilisateur est une instance de User ou Chauffeur
        if (!($user instanceof User) && !($user instanceof Chauffeur)) {
            Log::error('Type d\'utilisateur invalide pour l\'envoi de notification', [
                'user_type' => get_class($user),
            ]);
            return false;
        }

        if (!$user->fcm_token) {
            Log::warning('Utilisateur ' . $user->id . ' n\'a pas de token FCM');
            return false;
        }

        try {
            $notification = Notification::create($title, $body);

            $message = CloudMessage::withTarget('token', $user->fcm_token)
                ->withNotification($notification)
                ->withData($data);

            $this->messaging->send($message);

            Log::info('Notification FCM envoyée avec succès', [
                'user_id' => $user->id,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'timestamp' => now()->toDateTimeString(),
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('Erreur envoi notification FCM', [
                'user_id' => $user->id,
                'title' => $title,
                'error' => $e->getMessage(),
                'timestamp' => now()->toDateTimeString(),
            ]);
            return false;
        }
    }

    /**
     * Envoyer une notification à plusieurs utilisateurs
     *
     * @param array $userIds
     * @param string $title
     * @param string $body
     * @param array $data
     * @return bool
     */
    public function sendToMultipleUsers(array $userIds, $title, $body, $data = [])
    {
        $users = User::whereIn('id', $userIds)
            ->whereNotNull('fcm_token')
            ->get();

        if ($users->isEmpty()) {
            Log::warning('Aucun utilisateur avec token FCM trouvé pour les IDs: ' . implode(', ', $userIds));
            return false;
        }

        $tokens = $users->pluck('fcm_token')->toArray();

        try {
            $notification = Notification::create($title, $body);

            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withData($data);

            $this->messaging->sendMulticast($message, $tokens);

            Log::info('Notifications FCM envoyées avec succès à ' . count($tokens) . ' utilisateurs');
            return true;
        } catch (\Exception $e) {
            Log::error('Erreur envoi notification FCM multiple: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoyer une notification à tous les utilisateurs avec token FCM
     *
     * @param string $title
     * @param string $body
     * @param array $data
     * @return bool
     */
    public function sendToAllUsers($title, $body, $data = [])
    {
        $users = User::whereNotNull('fcm_token')->get();

        if ($users->isEmpty()) {
            Log::warning('Aucun utilisateur avec token FCM trouvé');
            return false;
        }

        $tokens = $users->pluck('fcm_token')->toArray();

        try {
            $notification = Notification::create($title, $body);

            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withData($data);

            $this->messaging->sendMulticast($message, $tokens);

            Log::info('Notifications FCM envoyées avec succès à tous les utilisateurs (' . count($tokens) . ')');
            return true;
        } catch (\Exception $e) {
            Log::error('Erreur envoi notification FCM à tous les utilisateurs: ' . $e->getMessage());
            return false;
        }
    }
}

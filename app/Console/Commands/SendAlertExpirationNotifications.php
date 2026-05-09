<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Alert;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use App\Services\AlertNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendAlertExpirationNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alerts:send-expiration-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envoyer des notifications push pour les alertes expirant dans 7, 6, 5, 4, 3, 2, 1, 0 jours';

    protected $firebaseService;
    protected $alertNotificationService;

    public function __construct()
    {
        parent::__construct();
        $this->firebaseService = new FirebaseNotificationService();
        $this->alertNotificationService = new AlertNotificationService();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Début de l\'envoi des notifications d\'expiration d\'alertes...');

        // Logger le début de l'exécution
        Log::info('=== Début de l\'envoi des notifications d\'expiration d\'alertes ===', [
            'timestamp' => now()->toDateTimeString(),
            'date' => Carbon::today()->format('Y-m-d'),
        ]);

        $daysToCheck = [7, 6, 5, 4, 3, 2, 1, 0];
        $today = Carbon::today();
        $totalSent = 0;
        $totalErrors = 0;

        foreach ($daysToCheck as $days) {
            $targetDate = $today->copy()->addDays($days);

            $this->info("Vérification des alertes expirant le {$targetDate->format('Y-m-d')} (dans {$days} jours)...");

            // Récupérer les alertes expirant à cette date
            $alerts = Alert::whereDate('date_fin', $targetDate->format('Y-m-d'))
                ->with(['user', 'type_alert', 'vehicule'])
                ->get();

            if ($alerts->isEmpty()) {
                $this->info("Aucune alerte trouvée pour cette date.");
                continue;
            }

            $this->info("{$alerts->count()} alerte(s) trouvée(s).");

            foreach ($alerts as $alert) {
                try {
                    // Vérifier que l'alerte a un type
                    if (!$alert->type_alert) {
                        $this->warn("Alerte ID {$alert->id} : Pas de type d'alerte associé.");
                        continue;
                    }

                    // Préparer les informations du véhicule
                    $vehiculeInfo = 'votre véhicule';
                    if ($alert->vehicule) {
                        $vehiculeInfo = $alert->vehicule->immatriculation ?? 'votre véhicule';
                    }

                    // Générer le titre et le message personnalisés
                    $title = $this->alertNotificationService->getNotificationTitle(
                        $alert->type_alert,
                        $days
                    );

                    $body = $this->alertNotificationService->getNotificationBody(
                        $alert->type_alert,
                        $days,
                        $vehiculeInfo,
                        $alert->date_fin
                    );

                    // Préparer les données supplémentaires
                    $data = [
                        'type' => 'alert_expiration',
                        'alert_id' => $alert->id,
                        'type_alert_id' => $alert->type_alert_id,
                        'type_alert_libelle' => $alert->type_alert->libelle,
                        'vehicule_id' => $alert->vehicule_id,
                        'date_fin' => $alert->date_fin,
                        'days_remaining' => $days,
                    ];

                    // Déterminer les utilisateurs à notifier
                    $usersToNotify = collect();

                    // Cas 1: Alerte avec user_id
                    if ($alert->user_id && $alert->user) {
                        if ($alert->user->fcm_token) {
                            $usersToNotify->push($alert->user);
                        } else {
                            $this->warn("Alerte ID {$alert->id} : L'utilisateur {$alert->user->id} n'a pas de token FCM.");
                        }
                    }
                    // Cas 2: Alerte avec gestionnaire_de_flotte_id
                    elseif ($alert->gestionnaire_de_flotte_id) {
                        $users = User::where('gestionnaire_de_flotte_id', $alert->gestionnaire_de_flotte_id)
                            ->whereNotNull('fcm_token')
                            ->get();

                        if ($users->isEmpty()) {
                            $this->warn("Alerte ID {$alert->id} : Aucun utilisateur avec token FCM trouvé pour le gestionnaire_de_flotte_id {$alert->gestionnaire_de_flotte_id}.");
                        } else {
                            $usersToNotify = $users;
                        }
                    }
                    // Cas 3: Aucun utilisateur associé
                    else {
                        $this->warn("Alerte ID {$alert->id} : Pas d'utilisateur ou gestionnaire_de_flotte associé.");
                        continue;
                    }

                    // Envoyer les notifications à tous les utilisateurs concernés
                    foreach ($usersToNotify as $user) {
                        try {
                            $result = $this->firebaseService->sendToUser(
                                $user,
                                $title,
                                $body,
                                $data
                            );

                            if ($result) {
                                $totalSent++;
                                $this->info("✓ Notification envoyée à l'utilisateur {$user->id} pour l'alerte {$alert->id} ({$alert->type_alert->libelle})");

                                // Logger avec détails
                                Log::info('Notification d\'expiration d\'alerte envoyée', [
                                    'alert_id' => $alert->id,
                                    'user_id' => $user->id,
                                    'type_alert' => $alert->type_alert->libelle,
                                    'vehicule_id' => $alert->vehicule_id,
                                    'date_fin' => $alert->date_fin,
                                    'days_remaining' => $days,
                                    'timestamp' => now()->toDateTimeString(),
                                ]);
                            } else {
                                $totalErrors++;
                                $this->error("✗ Échec de l'envoi pour l'alerte {$alert->id} à l'utilisateur {$user->id}");

                                Log::error('Échec envoi notification d\'expiration d\'alerte', [
                                    'alert_id' => $alert->id,
                                    'user_id' => $user->id,
                                    'type_alert' => $alert->type_alert->libelle,
                                    'timestamp' => now()->toDateTimeString(),
                                ]);
                            }
                        } catch (\Exception $e) {
                            $totalErrors++;
                            $this->error("✗ Erreur pour l'alerte {$alert->id} et l'utilisateur {$user->id}: " . $e->getMessage());
                            Log::error("Erreur lors de l'envoi de notification pour l'alerte {$alert->id} à l'utilisateur {$user->id}: " . $e->getMessage());
                        }
                    }

                } catch (\Exception $e) {
                    $totalErrors++;
                    $this->error("✗ Erreur pour l'alerte {$alert->id}: " . $e->getMessage());
                    Log::error("Erreur lors du traitement de l'alerte {$alert->id}: " . $e->getMessage());
                }
            }
        }

        $this->info("\n=== Résumé ===");
        $this->info("Notifications envoyées avec succès: {$totalSent}");
        $this->info("Erreurs: {$totalErrors}");
        $this->info("Terminé !");

        // Logger le résumé
        Log::info('=== Fin de l\'envoi des notifications d\'expiration d\'alertes ===', [
            'total_sent' => $totalSent,
            'total_errors' => $totalErrors,
            'timestamp' => now()->toDateTimeString(),
            'date' => Carbon::today()->format('Y-m-d'),
        ]);

        return Command::SUCCESS;
    }
}

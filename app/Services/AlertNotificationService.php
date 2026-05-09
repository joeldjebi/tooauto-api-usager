<?php

namespace App\Services;

use App\Models\Type_alert;
use Carbon\Carbon;

class AlertNotificationService
{
    /**
     * Générer le titre de la notification selon le type d'alerte et les jours restants
     *
     * @param Type_alert $typeAlert
     * @param int $daysRemaining
     * @return string
     */
    public function getNotificationTitle(Type_alert $typeAlert, int $daysRemaining): string
    {
        $typeName = $typeAlert->libelle;

        if ($daysRemaining === 0) {
            return "🚨 {$typeName} expiré aujourd'hui !";
        } elseif ($daysRemaining === 1) {
            return "⚠️ {$typeName} expire demain !";
        } else {
            return "⏰ {$typeName} expire dans {$daysRemaining} jours";
        }
    }

    /**
     * Générer le message de la notification selon le type d'alerte
     *
     * @param Type_alert $typeAlert
     * @param int $daysRemaining
     * @param string $vehiculeInfo
     * @param string $dateFin
     * @return string
     */
    public function getNotificationBody(Type_alert $typeAlert, int $daysRemaining, string $vehiculeInfo, string $dateFin): string
    {
        $typeName = $typeAlert->libelle;
        $dateFormatted = Carbon::parse($dateFin)->format('d/m/Y');

        $messages = [
            'Assurance' => [
                0 => "Votre assurance a expiré aujourd'hui ({$dateFormatted}). Veuillez renouveler immédiatement pour rester couvert.",
                1 => "Votre assurance expire demain ({$dateFormatted}). N'oubliez pas de renouveler votre contrat pour maintenir votre couverture.",
                'default' => "Votre assurance expire le {$dateFormatted} (dans {$daysRemaining} jours). Pensez à renouveler votre contrat d'assurance pour le véhicule {$vehiculeInfo}.",
            ],
            'Vidange' => [
                0 => "La date de vidange est aujourd'hui ({$dateFormatted}). Pensez à effectuer la vidange de votre véhicule {$vehiculeInfo}.",
                1 => "La vidange de votre véhicule est prévue pour demain ({$dateFormatted}). Préparez-vous à effectuer l'entretien.",
                'default' => "La vidange de votre véhicule {$vehiculeInfo} est prévue le {$dateFormatted} (dans {$daysRemaining} jours). Planifiez votre rendez-vous chez le garagiste.",
            ],
            'Visite technique' => [
                0 => "La visite technique est prévue aujourd'hui ({$dateFormatted}). Rendez-vous au centre de contrôle technique avec votre véhicule {$vehiculeInfo}.",
                1 => "Votre visite technique est prévue pour demain ({$dateFormatted}). N'oubliez pas de prendre rendez-vous et de préparer votre véhicule.",
                'default' => "Votre visite technique pour le véhicule {$vehiculeInfo} est prévue le {$dateFormatted} (dans {$daysRemaining} jours). Pensez à réserver votre créneau.",
            ],
            'Controle technique' => [
                0 => "Le contrôle technique est prévu aujourd'hui ({$dateFormatted}). Rendez-vous au centre agréé avec votre véhicule {$vehiculeInfo}.",
                1 => "Votre contrôle technique est prévu pour demain ({$dateFormatted}). Assurez-vous que votre véhicule est en bon état et prenez rendez-vous.",
                'default' => "Le contrôle technique de votre véhicule {$vehiculeInfo} est prévu le {$dateFormatted} (dans {$daysRemaining} jours). Réservez votre créneau dès maintenant.",
            ],
        ];

        $typeMessages = $messages[$typeName] ?? null;

        if ($typeMessages) {
            if (isset($typeMessages[$daysRemaining])) {
                return $typeMessages[$daysRemaining];
            } else {
                return $typeMessages['default'];
            }
        }

        // Message par défaut si le type n'est pas dans la liste
        if ($daysRemaining === 0) {
            return "Votre {$typeName} a expiré aujourd'hui ({$dateFormatted}). Veuillez prendre les mesures nécessaires pour le véhicule {$vehiculeInfo}.";
        } elseif ($daysRemaining === 1) {
            return "Votre {$typeName} expire demain ({$dateFormatted}). Pensez à renouveler pour le véhicule {$vehiculeInfo}.";
        } else {
            return "Votre {$typeName} pour le véhicule {$vehiculeInfo} expire le {$dateFormatted} (dans {$daysRemaining} jours).";
        }
    }
}
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CronJobOrgService
{
    protected $apiKey;
    protected $endpoint = 'https://api.cron-job.org';

    public function __construct()
    {
        $this->apiKey = env('CRON_JOB_ORG_API_KEY');
    }

    /**
     * Obtenir les headers pour les requêtes API
     *
     * @return array
     */
    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Lister tous les jobs
     *
     * @return array|null
     */
    public function listJobs()
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->endpoint . '/jobs');

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Erreur lors de la récupération des jobs: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('Exception lors de la récupération des jobs: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Créer un nouveau job
     *
     * @param string $url
     * @param string $title
     * @param array $schedule
     * @param bool $enabled
     * @return array|null
     */
    public function createJob(string $url, string $title = 'Alert Notifications', array $schedule = [], bool $enabled = true)
    {
        // Schedule par défaut : tous les jours à 9h00 (heure d'Abidjan)
        $defaultSchedule = [
            'timezone' => 'Africa/Abidjan',
            'expiresAt' => 0,
            'hours' => [9],
            'mdays' => [-1], // Tous les jours du mois
            'minutes' => [0],
            'months' => [-1], // Tous les mois
            'wdays' => [-1], // Tous les jours de la semaine
        ];

        $schedule = array_merge($defaultSchedule, $schedule);

        $payload = [
            'job' => [
                'url' => $url,
                'title' => $title,
                'enabled' => $enabled,
                'saveResponses' => true,
                'requestMethod' => 1, // POST
                'requestTimeout' => 300,
                'schedule' => $schedule,
            ],
        ];

        try {
            $response = Http::withHeaders($this->getHeaders())
                ->put($this->endpoint . '/jobs', $payload);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Erreur lors de la création du job: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('Exception lors de la création du job: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Mettre à jour un job existant
     *
     * @param int $jobId
     * @param array $jobData
     * @return bool
     */
    public function updateJob(int $jobId, array $jobData): bool
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->patch($this->endpoint . '/jobs/' . $jobId, ['job' => $jobData]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Exception lors de la mise à jour du job: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprimer un job
     *
     * @param int $jobId
     * @return bool
     */
    public function deleteJob(int $jobId): bool
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->delete($this->endpoint . '/jobs/' . $jobId);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Exception lors de la suppression du job: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtenir les détails d'un job
     *
     * @param int $jobId
     * @return array|null
     */
    public function getJobDetails(int $jobId)
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->endpoint . '/jobs/' . $jobId);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Exception lors de la récupération des détails du job: ' . $e->getMessage());
            return null;
        }
    }
}


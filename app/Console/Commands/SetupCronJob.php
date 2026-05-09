<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CronJobOrgService;
use Illuminate\Support\Facades\URL;

class SetupCronJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:setup-alert-notifications 
                            {--url= : URL complète de l\'endpoint (optionnel)}
                            {--force : Forcer la création même si un job existe déjà}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Créer automatiquement le job Cron-job.org pour les notifications d\'alertes';

    protected $cronService;

    public function __construct()
    {
        parent::__construct();
        $this->cronService = new CronJobOrgService();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Configuration du job Cron-job.org pour les notifications d\'alertes...');

        // Vérifier que la clé API est configurée
        if (empty(env('CRON_JOB_ORG_API_KEY'))) {
            $this->error('La clé API Cron-job.org n\'est pas configurée dans le fichier .env');
            $this->info('Ajoutez: CRON_JOB_ORG_API_KEY=votre_cle_api');
            return Command::FAILURE;
        }

        // Vérifier que la clé API de protection est configurée
        if (empty(env('CRON_API_KEY'))) {
            $this->error('La clé API de protection (CRON_API_KEY) n\'est pas configurée dans le fichier .env');
            $this->info('Ajoutez: CRON_API_KEY=une_cle_secrete_aleatoire');
            return Command::FAILURE;
        }

        // Construire l'URL de l'endpoint
        $url = $this->option('url');
        if (empty($url)) {
            $baseUrl = env('APP_URL', 'http://localhost');
            $url = rtrim($baseUrl, '/') . '/api/v1/cron/send-alert-notifications';
        }

        $this->info("URL de l'endpoint: {$url}");

        // Vérifier les jobs existants
        $this->info('Vérification des jobs existants...');
        $existingJobs = $this->cronService->listJobs();

        if ($existingJobs && isset($existingJobs['jobs'])) {
            $this->info('Jobs existants trouvés: ' . count($existingJobs['jobs']));
            
            // Chercher un job existant avec la même URL
            foreach ($existingJobs['jobs'] as $job) {
                if (isset($job['url']) && $job['url'] === $url) {
                    $this->warn("Un job existe déjà pour cette URL (ID: {$job['jobId']})");
                    
                    if (!$this->option('force')) {
                        if (!$this->confirm('Voulez-vous le supprimer et en créer un nouveau ?', false)) {
                            $this->info('Opération annulée.');
                            return Command::SUCCESS;
                        }
                    }
                    
                    // Supprimer le job existant
                    if ($this->cronService->deleteJob($job['jobId'])) {
                        $this->info("Job existant supprimé (ID: {$job['jobId']})");
                    } else {
                        $this->error("Impossible de supprimer le job existant");
                        return Command::FAILURE;
                    }
                    break;
                }
            }
        }

        // Créer le nouveau job
        $this->info('Création du nouveau job...');
        $result = $this->cronService->createJob(
            $url,
            'Alert Notifications - Expiration des alertes',
            [
                'timezone' => 'Africa/Abidjan',
                'hours' => [9], // 9h00
                'minutes' => [0],
            ]
        );

        if ($result && isset($result['jobId'])) {
            $this->info("✓ Job créé avec succès !");
            $this->info("ID du job: {$result['jobId']}");
            $this->info("Le job s'exécutera tous les jours à 9h00 (heure d'Abidjan)");
            $this->info("URL: {$url}");
            
            // Afficher les détails du job
            $jobDetails = $this->cronService->getJobDetails($result['jobId']);
            if ($jobDetails && isset($jobDetails['jobDetails'])) {
                $nextExecution = $jobDetails['jobDetails']['nextExecution'] ?? null;
                if ($nextExecution) {
                    $nextDate = date('Y-m-d H:i:s', $nextExecution);
                    $this->info("Prochaine exécution: {$nextDate}");
                }
            }
            
            return Command::SUCCESS;
        } else {
            $this->error('Échec de la création du job');
            $this->error('Vérifiez votre clé API et votre connexion internet');
            return Command::FAILURE;
        }
    }
}

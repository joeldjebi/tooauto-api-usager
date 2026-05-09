<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;

class ViewNotificationLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:view-logs 
                            {--lines=100 : Nombre de lignes à afficher}
                            {--date= : Filtrer par date (format: Y-m-d)}
                            {--today : Afficher uniquement les logs d\'aujourd\'hui}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Afficher les logs des notifications envoyées';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $logFile = storage_path('logs/laravel.log');
        
        if (!file_exists($logFile)) {
            $this->error('Fichier de logs introuvable: ' . $logFile);
            return Command::FAILURE;
        }

        $lines = (int) $this->option('lines');
        $lines = min($lines, 1000); // Maximum 1000 lignes
        
        $dateFilter = $this->option('date');
        if ($this->option('today')) {
            $dateFilter = Carbon::today()->format('Y-m-d');
        }

        $this->info("Lecture des logs de notifications...");
        $this->info("Fichier: {$logFile}");
        if ($dateFilter) {
            $this->info("Filtre date: {$dateFilter}");
        }
        $this->line('');

        // Lire les dernières lignes du fichier de log
        $command = "tail -n {$lines} " . escapeshellarg($logFile);
        $logContent = shell_exec($command);
        
        // Filtrer les logs liés aux notifications
        $notificationLogs = [];
        $allLines = explode("\n", $logContent);
        
        foreach ($allLines as $line) {
            if (empty(trim($line))) {
                continue;
            }
            
            $isNotificationLog = 
                stripos($line, 'Notification FCM') !== false || 
                stripos($line, 'notification d\'expiration') !== false ||
                stripos($line, 'Début de l\'envoi des notifications') !== false ||
                stripos($line, 'Fin de l\'envoi des notifications') !== false ||
                stripos($line, 'Erreur envoi notification') !== false;
            
            if ($isNotificationLog) {
                // Appliquer le filtre de date si fourni
                if ($dateFilter && stripos($line, $dateFilter) === false) {
                    continue;
                }
                $notificationLogs[] = $line;
            }
        }
        
        if (empty($notificationLogs)) {
            $this->warn('Aucun log de notification trouvé.');
            if ($dateFilter) {
                $this->info("Essayez sans le filtre de date ou vérifiez que des notifications ont été envoyées le {$dateFilter}");
            }
            return Command::SUCCESS;
        }

        $filterText = $dateFilter ? 'filtrés par date' : 'tous';
        $this->info("=== Logs de notifications ({$filterText}) ===");
        $this->line('');
        
        // Afficher les logs (plus récents en premier)
        $reversedLogs = array_reverse($notificationLogs);
        foreach ($reversedLogs as $log) {
            // Colorer selon le type de log
            if (stripos($log, 'succès') !== false || stripos($log, 'envoyée') !== false) {
                $this->line($log);
            } elseif (stripos($log, 'Erreur') !== false || stripos($log, 'échec') !== false) {
                $this->error($log);
            } elseif (stripos($log, 'Début') !== false || stripos($log, 'Fin') !== false) {
                $this->comment($log);
            } else {
                $this->line($log);
            }
        }
        
        $this->line('');
        $this->info("Total: " . count($notificationLogs) . " entrées de log");
        
        // Statistiques
        $successCount = 0;
        $errorCount = 0;
        foreach ($notificationLogs as $log) {
            if (stripos($log, 'succès') !== false || stripos($log, 'envoyée') !== false) {
                $successCount++;
            } elseif (stripos($log, 'Erreur') !== false || stripos($log, 'échec') !== false) {
                $errorCount++;
            }
        }
        
        if ($successCount > 0 || $errorCount > 0) {
            $this->line('');
            $this->info("Statistiques:");
            $this->line("  ✓ Succès: {$successCount}");
            $this->line("  ✗ Erreurs: {$errorCount}");
        }

        return Command::SUCCESS;
    }
}

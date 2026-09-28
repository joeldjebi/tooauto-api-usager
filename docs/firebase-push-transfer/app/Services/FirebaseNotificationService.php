<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use RuntimeException;

class FirebaseNotificationService
{
    private $messaging;

    public function __construct(Factory $factory)
    {
        $credentialsPath = (string) config('firebase.credentials');

		if ($credentialsPath !== '' && !str_starts_with($credentialsPath, DIRECTORY_SEPARATOR)) {
			$credentialsPath = base_path($credentialsPath);
		}

        if ($credentialsPath === '' || !is_file($credentialsPath)) {
            throw new RuntimeException('Le fichier de credentials Firebase est introuvable.');
        }

        $this->messaging = $factory
            ->withServiceAccount($credentialsPath)
            ->createMessaging();
    }

    public function sendToUser(User $user, string $title, string $body, array $data = []): array
    {
        if (!$user->fcm_token) {
            return $this->emptyReport();
        }

        $message = $this->message($title, $body, $data)
            ->withChangedTarget('token', $user->fcm_token);

        try {
            $this->messaging->send($message);

            return [
                'success_count' => 1,
                'failure_count' => 0,
                'target_count' => 1,
            ];
        } catch (\Throwable $exception) {
            Log::error('Erreur envoi notification FCM', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success_count' => 0,
                'failure_count' => 1,
                'target_count' => 1,
            ];
        }
    }

    public function sendToMultipleUsers(array $userIds, string $title, string $body, array $data = []): array
    {
        $tokens = User::query()
            ->whereIn('id', array_unique($userIds))
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '<>', '')
            ->pluck('fcm_token');

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    public function sendToAllUsers(string $title, string $body, array $data = []): array
    {
        $report = $this->emptyReport();

        User::query()
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '<>', '')
            ->select(['id', 'fcm_token'])
            ->chunkById(1000, function (Collection $users) use (&$report, $title, $body, $data) {
                $batchReport = $this->sendToTokens(
                    $users->pluck('fcm_token'),
                    $title,
                    $body,
                    $data
                );

                $report = $this->mergeReports($report, $batchReport);
            });

        return $report;
    }

    private function sendToTokens(Collection $tokens, string $title, string $body, array $data): array
    {
        $report = $this->emptyReport();
        $tokens = $tokens->filter()->unique()->values();

        foreach ($tokens->chunk(500) as $tokenBatch) {
            try {
                $sendReport = $this->messaging->sendMulticast(
                    $this->message($title, $body, $data),
                    $tokenBatch->all()
                );

                $report = $this->mergeReports($report, [
                    'success_count' => $sendReport->successes()->count(),
                    'failure_count' => $sendReport->failures()->count(),
                    'target_count' => $tokenBatch->count(),
                ]);
            } catch (\Throwable $exception) {
                Log::error('Erreur envoi notification FCM multicast', [
                    'target_count' => $tokenBatch->count(),
                    'error' => $exception->getMessage(),
                ]);

                $report = $this->mergeReports($report, [
                    'success_count' => 0,
                    'failure_count' => $tokenBatch->count(),
                    'target_count' => $tokenBatch->count(),
                ]);
            }
        }

        return $report;
    }

    private function message(string $title, string $body, array $data): CloudMessage
    {
        return CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($this->normalizeData($data));
    }

    private function normalizeData(array $data): array
    {
        return collect($data)->map(function ($value) {
            if (is_array($value) || is_object($value)) {
                return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            return (string) $value;
        })->all();
    }

    private function emptyReport(): array
    {
        return [
            'success_count' => 0,
            'failure_count' => 0,
            'target_count' => 0,
        ];
    }

    private function mergeReports(array $report, array $addition): array
    {
        return [
            'success_count' => $report['success_count'] + $addition['success_count'],
            'failure_count' => $report['failure_count'] + $addition['failure_count'],
            'target_count' => $report['target_count'] + $addition['target_count'],
        ];
    }
}

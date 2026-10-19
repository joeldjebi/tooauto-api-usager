<?php

namespace App\Services;

use Exception;

class SmsService
{
    protected string $url;
    protected string $username;
    protected string $password;
    protected string $defaultSender;
    protected int $timeout;
    protected bool $verifySsl;

    public function __construct()
    {
        $this->url = config('services.mtarget.url', 'https://api-public-2.mtarget.fr/messages');
        $this->username = (string) config('services.mtarget.username', '');
        $this->password = (string) config('services.mtarget.password', '');
        $this->defaultSender = config('services.mtarget.sender', 'TOOAUTO');
        $this->timeout = (int) config('services.mtarget.timeout', 30);
        $this->verifySsl = (bool) config('services.mtarget.verify_ssl', true);
    }

    /**
     * Envoie un SMS via l'API MTarget.
     *
     * @throws Exception
     */
    public function sendSmsMtarget(string $message, string $msisdn, ?string $sender = null): string
    {
        $result = $this->sendSmsMtargetDetailed($message, $msisdn, $sender);

        if ($result['http_status'] !== 200) {
            throw new Exception('Erreur HTTP : ' . $result['http_status'] . ' - Réponse : ' . $result['raw_body']);
        }

        $providerResult = $result['results'][0] ?? [];
        if ((string) ($providerResult['code'] ?? '') !== '0'
            || strtoupper((string) ($providerResult['reason'] ?? '')) !== 'ACCEPTED') {
            throw new Exception('MTarget n’a pas accepté le SMS : ' . $result['raw_body']);
        }

        return $result['raw_body'];
    }

    /**
     * Envoie un SMS et retourne les éléments de transport nécessaires au suivi.
     * Une réponse ACCEPTED reste une acceptation fournisseur, pas une preuve de livraison.
     *
     * @throws Exception
     */
    public function sendSmsMtargetDetailed(string $message, string $msisdn, ?string $sender = null, ?string $remoteId = null): array
    {
        if ($this->username === '' || $this->password === '') {
            throw new Exception('Les identifiants MTarget ne sont pas configurés.');
        }

        $msisdn = $this->formatMsisdn($msisdn);

        $parameters = [
            'username' => $this->username,
            'password' => $this->password,
            'msisdn' => $msisdn,
            'msg' => $message,
            'sender' => $sender ?: $this->defaultSender,
        ];

        if ($remoteId !== null) {
            $parameters['remoteid'] = $remoteId;
        }

        $postData = http_build_query($parameters, '', '&', PHP_QUERY_RFC1738);

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_TIMEOUT => $this->timeout,
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);

            throw new Exception('Erreur cURL : ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $rawBody = (string) $response;
        $decodedBody = json_decode($rawBody, true);

        return [
            'http_status' => (int) $httpCode,
            'raw_body' => $rawBody,
            'body' => is_array($decodedBody) ? $decodedBody : null,
            'results' => is_array($decodedBody) && isset($decodedBody['results']) && is_array($decodedBody['results'])
                ? $decodedBody['results']
                : [],
            'msisdn' => $msisdn,
            'sender' => $sender ?: $this->defaultSender,
        ];
    }

    protected function formatMsisdn(string $msisdn): string
    {
        $msisdn = preg_replace('/[\s().-]+/', '', trim($msisdn));

        if (strpos($msisdn, '+') !== 0) {
            return '+' . $msisdn;
        }

        return $msisdn;
    }
}

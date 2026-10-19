<?php

namespace App\Http\Controllers;

use App\Models\SmsTestMessage;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SmsTestController extends Controller
{
    private const DELIVERY_STATUSES = [
        0 => 'waiting',
        1 => 'in_progress',
        2 => 'sent_to_operator',
        3 => 'delivered',
        4 => 'refused',
        6 => 'not_delivered',
    ];

    public function send(Request $request, SmsService $smsService): JsonResponse
    {
        $validated = $request->validate([
            'msisdn' => ['required', 'string', 'max:32', 'regex:/^\\+?[0-9\\s().-]+$/'],
            'message' => ['required', 'string', 'max:2000'],
            'sender' => ['nullable', 'string', 'max:32'],
        ]);

        $user = $request->user();

        $remoteId = Str::upper(Str::random(32));

        try {
            $provider = $smsService->sendSmsMtargetDetailed(
                $validated['message'],
                $validated['msisdn'],
                $validated['sender'] ?? null,
                $remoteId
            );
        } catch (\Throwable $exception) {
            Log::error('Erreur de transport lors du test SMS MTarget.', [
                'user_id' => $user?->id,
                'remote_id' => $remoteId,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => "Impossible de joindre le fournisseur SMS.",
                'data' => [
                    'provider' => 'mtarget',
                    'submission_status' => 'transport_error',
                    'delivery_status' => 'unknown',
                    'remote_id' => $remoteId,
                    'provider_error' => $exception->getMessage(),
                ],
            ], 502);
        }

        $providerResult = $provider['results'][0] ?? [];
        $providerCode = isset($providerResult['code']) ? (string) $providerResult['code'] : null;
        $providerReason = isset($providerResult['reason']) ? (string) $providerResult['reason'] : null;
        $accepted = $provider['http_status'] === 200
            && $providerCode === '0'
            && Str::upper((string) $providerReason) === 'ACCEPTED';
        $ticket = isset($providerResult['ticket']) ? (string) $providerResult['ticket'] : null;

        $record = SmsTestMessage::create([
            'requested_by_user_id' => $user?->id,
            'remote_id' => $remoteId,
            'provider_ticket' => $ticket,
            'destination' => $provider['msisdn'],
            'sender' => $provider['sender'],
            'message' => $validated['message'],
            'submission_status' => $accepted ? 'accepted' : 'rejected',
            'provider_code' => $providerCode,
            'provider_reason' => $providerReason,
            'provider_sms_count' => isset($providerResult['smscount']) ? (int) $providerResult['smscount'] : null,
            'provider_http_status' => $provider['http_status'],
            'provider_response' => [
                'body' => $provider['body'],
                'raw_body' => $provider['raw_body'],
            ],
            'delivery_reports' => [],
        ]);

        $callbackToken = (string) config('services.mtarget.dlr_token', '');
        $callbackUrl = $callbackToken !== ''
            ? route('mtarget.dlr', ['token' => $callbackToken])
            : null;

        return response()->json([
            'success' => $accepted,
            'message' => $accepted
                ? 'Le fournisseur a accepté le SMS. La livraison sera confirmée par accusé DLR.'
                : 'Le fournisseur a refusé la soumission du SMS.',
            'data' => [
                'id' => $record->id,
                'provider' => 'mtarget',
                'submission_status' => $record->submission_status,
                'delivery_status' => $accepted ? 'waiting_for_dlr' : 'not_submitted',
                'ticket' => $record->provider_ticket,
                'remote_id' => $record->remote_id,
                'destination' => $record->destination,
                'sender' => $record->sender,
                'sms_count' => $record->provider_sms_count,
                'provider_http_status' => $record->provider_http_status,
                'provider_code' => $record->provider_code,
                'provider_reason' => $record->provider_reason,
                'provider_response' => $record->provider_response,
                'status_url' => $record->provider_ticket
                    ? route('mtarget.test.status', ['ticket' => $record->provider_ticket])
                    : null,
                'dlr_callback_url' => $callbackUrl,
                'delivery_tracking_configured' => $callbackUrl !== null,
            ],
        ], $accepted ? 200 : 502);
    }

    public function status(Request $request, string $ticket): JsonResponse
    {
        $sms = SmsTestMessage::where('provider_ticket', $ticket)->first();

        if (!$sms) {
            return response()->json([
                'success' => false,
                'message' => 'SMS de test introuvable.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatStatus($sms),
        ]);
    }

    public function deliveryReport(Request $request, string $token): JsonResponse
    {
        $expectedToken = (string) config('services.mtarget.dlr_token', '');
        if ($expectedToken === '' || !hash_equals($expectedToken, $token)) {
            return response()->json(['success' => false], 403);
        }

        $validated = $request->validate([
            'MsgId' => ['nullable', 'string', 'max:128', 'required_without:remoteid'],
            'remoteid' => ['nullable', 'string', 'max:250', 'required_without:MsgId'],
            'Status' => ['required', 'integer', 'in:0,1,2,3,4,6'],
            'StatusText' => ['nullable', 'string', 'max:64'],
            'DestinationAdress' => ['nullable', 'string', 'max:32'],
            'OriginatedAddress' => ['nullable', 'string', 'max:32'],
            'CreateDateTime' => ['nullable', 'string', 'max:64'],
            'SendDateTime' => ['nullable', 'string', 'max:64'],
            'DeliveryDateTime' => ['nullable', 'string', 'max:64'],
            'Reason' => ['nullable', 'string', 'max:255'],
            'RSN' => ['nullable', 'string', 'max:64'],
            'SmsCount' => ['nullable', 'integer', 'min:0'],
        ]);

        $sms = SmsTestMessage::query()
            ->when(!empty($validated['MsgId']), function ($query) use ($validated) {
                $query->where('provider_ticket', $validated['MsgId']);
            })
            ->when(empty($validated['MsgId']) && !empty($validated['remoteid']), function ($query) use ($validated) {
                $query->where('remote_id', $validated['remoteid']);
            })
            ->first();

        if (!$sms) {
            Log::warning('Accusé DLR MTarget reçu pour un SMS de test non suivi.', [
                'ticket' => $validated['MsgId'] ?? null,
                'remote_id' => $validated['remoteid'] ?? null,
            ]);

            return response()->json(['success' => true, 'tracked' => false]);
        }

        DB::transaction(function () use ($sms, $validated) {
            $sms = SmsTestMessage::whereKey($sms->id)->lockForUpdate()->first();
            $reports = $sms->delivery_reports ?? [];
            $reports[] = $validated;
            $incomingStatus = (int) $validated['Status'];
            $currentStatus = $sms->delivery_status_code;
            $terminalStatuses = [3, 4, 6];

            $updateStatus = $currentStatus === null
                || (!in_array((int) $currentStatus, $terminalStatuses, true)
                    && (in_array($incomingStatus, $terminalStatuses, true)
                        || $this->statusRank($incomingStatus) >= $this->statusRank((int) $currentStatus)));

            $sms->delivery_reports = $reports;
            if ($updateStatus) {
                $sms->delivery_status_code = $incomingStatus;
                $sms->delivery_status_text = $validated['StatusText'] ?? self::DELIVERY_STATUSES[$incomingStatus];
                $sms->delivery_reason = $validated['Reason'] ?? null;
                $sms->delivery_rsn = isset($validated['RSN']) ? (string) $validated['RSN'] : null;
                $sms->originated_address = $validated['OriginatedAddress'] ?? null;
                $sms->provider_created_at = $validated['CreateDateTime'] ?? null;
                $sms->provider_sent_at = $validated['SendDateTime'] ?? null;
                $sms->provider_delivered_at = $validated['DeliveryDateTime'] ?? null;
            }
            $sms->save();
        });

        return response()->json(['success' => true, 'tracked' => true]);
    }

    private function formatStatus(SmsTestMessage $sms): array
    {
        $deliveryStatus = $sms->delivery_status_code === null
            ? 'waiting_for_dlr'
            : (self::DELIVERY_STATUSES[$sms->delivery_status_code] ?? 'unknown');

        return [
            'id' => $sms->id,
            'provider' => 'mtarget',
            'submission_status' => $sms->submission_status,
            'delivery_status_code' => $sms->delivery_status_code,
            'delivery_status' => $deliveryStatus,
            'delivery_status_text' => $sms->delivery_status_text,
            'ticket' => $sms->provider_ticket,
            'remote_id' => $sms->remote_id,
            'destination' => $sms->destination,
            'sender' => $sms->sender,
            'sms_count' => $sms->provider_sms_count,
            'provider_http_status' => $sms->provider_http_status,
            'provider_code' => $sms->provider_code,
            'provider_reason' => $sms->provider_reason,
            'delivery_reason' => $sms->delivery_reason,
            'delivery_rsn' => $sms->delivery_rsn,
            'provider_created_at' => $sms->provider_created_at,
            'provider_sent_at' => $sms->provider_sent_at,
            'provider_delivered_at' => $sms->provider_delivered_at,
            'provider_response' => $sms->provider_response,
            'delivery_reports' => $sms->delivery_reports ?? [],
            'created_at' => optional($sms->created_at)->toDateTimeString(),
        ];
    }

    private function statusRank(int $status): int
    {
        return [0 => 0, 1 => 1, 2 => 2, 3 => 3, 4 => 3, 6 => 3][$status] ?? -1;
    }
}

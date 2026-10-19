<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsTestMessage extends Model
{
    protected $table = 'sms_test_messages';

    protected $fillable = [
        'requested_by_user_id',
        'remote_id',
        'provider_ticket',
        'destination',
        'sender',
        'message',
        'submission_status',
        'provider_code',
        'provider_reason',
        'provider_sms_count',
        'provider_http_status',
        'provider_response',
        'delivery_status_code',
        'delivery_status_text',
        'delivery_reason',
        'delivery_rsn',
        'originated_address',
        'provider_created_at',
        'provider_sent_at',
        'provider_delivered_at',
        'delivery_reports',
    ];

    protected $casts = [
        'provider_sms_count' => 'integer',
        'provider_http_status' => 'integer',
        'delivery_status_code' => 'integer',
        'provider_response' => 'array',
        'delivery_reports' => 'array',
    ];
}


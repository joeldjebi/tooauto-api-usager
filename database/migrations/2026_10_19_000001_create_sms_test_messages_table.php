<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sms_test_messages')) {
            return;
        }

        Schema::create('sms_test_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('requested_by_user_id')->nullable()->index();
            $table->string('remote_id', 64)->unique();
            $table->string('provider_ticket', 128)->nullable()->unique();
            $table->string('destination', 32);
            $table->string('sender', 32)->nullable();
            $table->text('message');
            $table->string('submission_status', 24);
            $table->string('provider_code', 32)->nullable();
            $table->string('provider_reason', 255)->nullable();
            $table->unsignedSmallInteger('provider_sms_count')->nullable();
            $table->unsignedSmallInteger('provider_http_status')->nullable();
            $table->json('provider_response')->nullable();
            $table->unsignedTinyInteger('delivery_status_code')->nullable();
            $table->string('delivery_status_text', 64)->nullable();
            $table->string('delivery_reason', 255)->nullable();
            $table->string('delivery_rsn', 64)->nullable();
            $table->string('originated_address', 32)->nullable();
            $table->string('provider_created_at', 64)->nullable();
            $table->string('provider_sent_at', 64)->nullable();
            $table->string('provider_delivered_at', 64)->nullable();
            $table->json('delivery_reports')->nullable();
            $table->timestamps();

            $table->index(['requested_by_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_test_messages');
    }
};


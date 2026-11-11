<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            // Providers deliver at least once: this index is what makes a redelivery harmless.
            $table->string('provider_event_id')->unique();
            $table->string('type');
            $table->jsonb('payload');
            $table->string('outcome');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};

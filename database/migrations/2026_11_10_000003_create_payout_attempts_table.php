<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_attempts', function (Blueprint $table) {
            // The attempt id travels to the provider as the transfer reference.
            $table->uuid('id')->primary();
            $table->foreignUuid('payout_id')->index()->constrained();
            $table->string('status')->index();
            $table->string('provider_transfer_id')->nullable();
            $table->string('detail')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_attempts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('wallet_id');
            // Money is stored as integer minor units (cents): floats cannot represent 0.10 exactly.
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->string('status')->index();
            $table->string('provider_reference')->nullable()->unique();
            $table->string('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};

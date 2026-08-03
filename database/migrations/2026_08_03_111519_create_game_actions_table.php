<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('player_id')->nullable()->constrained('game_players')->nullOnDelete();
            $table->string('action_type', 48);
            $table->foreignId('card_id')->nullable()->constrained('game_cards')->nullOnDelete();
            $table->unsignedInteger('turn_number')->default(0);
            $table->unsignedBigInteger('state_version')->default(0);
            $table->string('idempotency_key', 64)->nullable();
            $table->json('public_payload')->nullable();
            $table->json('private_payload')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['game_id', 'id']);
            $table->index(['game_id', 'turn_number']);
            $table->unique(['game_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_actions');
    }
};

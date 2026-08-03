<?php

use App\Enums\ConnectionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_players', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->string('nickname', 32);
            $table->string('secure_token_hash', 64);
            $table->unsignedTinyInteger('seat_number');
            $table->boolean('is_host')->default(false);
            $table->boolean('is_ready')->default(false);
            $table->timestamp('punishment_acknowledged_at')->nullable();
            $table->string('connection_status', 32)->default(ConnectionStatus::Connected->value);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->unsignedInteger('turn_timeout_count')->default(0);
            $table->unsignedInteger('cards_collected_count')->default(0);
            $table->unsignedInteger('piles_collected_count')->default(0);
            $table->unsignedInteger('failed_follow_suit_count')->default(0);
            $table->unsignedTinyInteger('finishing_position')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'seat_number']);
            $table->unique(['game_id', 'nickname']);
            $table->index(['game_id', 'secure_token_hash']);
            $table->index('secure_token_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_players');
    }
};

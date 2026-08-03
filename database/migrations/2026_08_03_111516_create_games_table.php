<?php

use App\Enums\GameStatus;
use App\Enums\RulePreset;
use App\Enums\TurnDirection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('game_code', 6)->unique();
            $table->string('invitation_token', 64)->unique();
            $table->string('name')->nullable();

            // Foreign keys to game_players are added once that table exists.
            $table->unsignedBigInteger('host_player_id')->nullable();
            $table->unsignedBigInteger('current_player_id')->nullable();
            $table->unsignedBigInteger('starting_player_id')->nullable();

            $table->string('status', 32)->default(GameStatus::Lobby->value);
            $table->unsignedTinyInteger('maximum_players')->default(4);
            $table->string('rule_preset', 32)->default(RulePreset::Classic->value);
            $table->json('rules');
            $table->boolean('punishment_enabled')->default(false);
            $table->json('punishment')->nullable();

            $table->string('active_suit', 16)->nullable();
            $table->string('direction', 32)->default(TurnDirection::Clockwise->value);
            $table->unsignedInteger('round_number')->default(0);
            $table->unsignedInteger('turn_number')->default(0);
            $table->unsignedBigInteger('state_version')->default(0);
            $table->timestamp('turn_deadline_at')->nullable();
            $table->boolean('pending_extra_turn')->default(false);
            $table->boolean('is_paused')->default(false);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['status', 'expires_at']);
            $table->index('turn_deadline_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};

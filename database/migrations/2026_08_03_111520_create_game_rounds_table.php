<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->unsignedInteger('round_number');
            $table->foreignId('starting_player_id')->nullable()->constrained('game_players')->nullOnDelete();
            $table->foreignId('winner_player_id')->nullable()->constrained('game_players')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'round_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_rounds');
    }
};

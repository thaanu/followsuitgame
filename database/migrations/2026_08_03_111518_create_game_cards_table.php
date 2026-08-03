<?php

use App\Enums\CardLocation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->unsignedTinyInteger('deck_number')->default(1);
            $table->string('suit', 16);
            $table->string('rank', 16);
            $table->unsignedTinyInteger('rank_value')->default(0);
            $table->string('card_code', 16);
            $table->string('location', 32)->default(CardLocation::Deck->value);
            $table->foreignId('player_id')->nullable()->constrained('game_players')->nullOnDelete();
            $table->unsignedInteger('pile_position')->nullable();

            // Server-only: the shuffled deck index. Never exposed to clients.
            $table->unsignedInteger('original_deal_position')->nullable();
            $table->timestamps();

            $table->index(['game_id', 'location']);
            $table->index(['game_id', 'player_id', 'location']);
            $table->index(['game_id', 'location', 'pile_position']);
            $table->unique(['game_id', 'card_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_cards');
    }
};

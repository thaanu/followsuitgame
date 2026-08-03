<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Points a finished game at the rematch the host created from it, so the
            // other players can follow along from the results screen.
            $table->foreignId('rematch_game_id')->nullable()->after('expires_at')
                ->constrained('games')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropForeign(['rematch_game_id']);
            $table->dropColumn('rematch_game_id');
        });
    }
};

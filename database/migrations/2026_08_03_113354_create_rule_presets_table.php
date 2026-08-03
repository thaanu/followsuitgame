<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rule_presets', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 48)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('rules');
            $table->boolean('is_built_in')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_presets');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('body_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('measured_on');
            $table->decimal('weight_kg', 5, 2);
            $table->decimal('body_fat_percentage', 4, 1)->nullable();
            $table->string('memo', 255)->nullable();
            $table->timestamps();

            // 1日1件。同日の再入力は上書き(BodyLogController が updateOrCreate で扱う)。
            // このユニーク制約は (user_id, measured_on) の検索(一覧・期間フィルタ)にも
            // そのまま使えるため、別途同じ列順の index は張らない。
            $table->unique(['user_id', 'measured_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('body_logs');
    }
};

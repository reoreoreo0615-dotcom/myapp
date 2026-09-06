<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Issue #23③: 誤って削除したセットを復元できるようにする。
     *
     * `WorkoutSetRepository` の集計・履歴クエリは `DB::table()` を多用しており
     * Eloquent と違って論理削除を自動で除外しないため、このマイグレーション
     * と合わせて同リポジトリ側にも `deleted_at IS NULL` 条件を追加している
     * (集計対象は常に workout_sets / workouts の両方が deleted_at IS NULL の行のみ)。
     */
    public function up(): void
    {
        Schema::table('workout_sets', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workout_sets', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};

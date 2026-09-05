<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ワークアウト開始時点(まだ自分自身のセットが記録されていない時点)の
     * 「前回のセット」「今日の目標」を種目ごとに一度だけ確定して保存する列。
     *
     * これが無いと、記録画面をリロードするたびに WorkoutSetRepository が
     * "最も新しいワークアウト" として進行中のワークアウト自身を拾ってしまい、
     * セットを記録した瞬間から「前回」「目標」が自分自身の入力値に汚染される
     * (ダブルプログレッションが暴走する)。詳細は
     * App\Services\WorkoutProgressionSnapshotService を参照。
     */
    public function up(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->json('progression_snapshot')->nullable()->after('memo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->dropColumn('progression_snapshot');
        });
    }
};

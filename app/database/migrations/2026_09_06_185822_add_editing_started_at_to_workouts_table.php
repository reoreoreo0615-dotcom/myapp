<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Issue #23①: 終了済みワークアウトは既定では編集不可のまま(WorkoutPolicy::update)
     * だが、「記録を修正する」ボタンで明示的に編集モードへ入れるようにする。
     * この列が非nullの間だけ、終了済みでもセットの追加・編集・削除を許可する
     * (WorkoutPolicy::update / startEditing / endEditing を参照)。
     *
     * 「修正を終える」を押すと null に戻り、閲覧専用に戻る。
     * finished_at 自体はこの操作で変化しない
     * (workouts.progression_snapshot も同様に一切再計算しない)。
     */
    public function up(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->timestamp('editing_started_at')->nullable()->after('finished_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->dropColumn('editing_started_at');
        });
    }
};

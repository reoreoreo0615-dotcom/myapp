<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Issue #23③: 誤って削除したワークアウトを復元できるようにする。
     *
     * 注意: workout_sets.workout_id / user_id は物理外部キー(cascadeOnDelete)
     * のままなので、ここでの論理削除は DB レベルの CASCADE を発火させない。
     * そのため workouts を論理削除しても workout_sets 自体は残るが、
     * すべての集計・履歴クエリは workouts と JOIN したうえで
     * workouts.deleted_at IS NULL を条件に含めるため
     * (Eloquent は自動、DB::table() は WorkoutSetRepository 側で明示的に対応)、
     * 論理削除されたワークアウトの配下セットが集計に混ざることはない。
     * 復元すれば何もせずそのまま元通り見えるようになる。
     *
     * ユーザー削除(DeleteUserService)は物理削除のままなので、
     * ここでの変更とは無関係(withTrashed()->forceDelete() で完全に消す)。
     */
    public function up(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workouts', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};

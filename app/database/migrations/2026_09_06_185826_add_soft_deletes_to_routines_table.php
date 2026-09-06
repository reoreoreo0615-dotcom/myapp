<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Issue #23③: 誤って削除したメニューを復元できるようにする。
     *
     * routine_exercises.routine_id / workouts.routine_id は物理外部キーの
     * ままなので、論理削除では発火しない(routine_exercises は残り、
     * workouts.routine_id も null化されずそのまま残る)。これは意図的な挙動:
     * 復元すれば構成・紐付けともに何もせず元通りになる。
     * Workout::routine() は withTrashed() 付きにしているため、
     * メニューが論理削除された後もそのメニューに基づくワークアウト
     * (進行中・終了済みいずれも)は種目構成を引き続き参照できる。
     */
    public function up(): void
    {
        Schema::table('routines', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routines', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};

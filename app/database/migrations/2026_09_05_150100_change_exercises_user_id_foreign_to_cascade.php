<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * exercises.user_id の削除ルールを SET NULL から CASCADE に変更する。
     *
     * SET NULL のままだと、ユーザーを削除した際にそのユーザーの独自種目が
     * user_id = NULL になり、全ユーザーに見える既定種目に昇格してしまう
     * (private なデータが公開される情報漏洩)。
     * ユーザーの独自種目は「そのユーザーに属する」が正しいセマンティクスなので、
     * ユーザー削除時は種目ごと削除する CASCADE が正しい。
     *
     * ただし workout_sets.exercise_id は RESTRICT のままなので、セット記録が
     * 残っている種目を FK だけで安全に削除することはできない。
     * さらに MySQL は users から出る複数のカスケード経路の実行順序を保証しないため、
     * アプリ側(DeleteUserService)でトランザクションを張り、
     * workouts → routines → exercises → user の順に明示的に削除する。
     */
    public function up(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('exercises', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('exercises', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }
};

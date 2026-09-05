<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 記録画面の「タップ1回で1セット」ボタンの二重送信対策。
     * クライアントがセットの入力行ごとに一意な client_request_id を発行し、
     * 同じ値で再送されたリクエストは既存行を返すだけで新規作成しない
     * (冪等キー)。ユニーク制約は、ネットワーク遅延で発生しうる
     * ほぼ同時の多重送信に対する最後の砦。
     */
    public function up(): void
    {
        Schema::table('workout_sets', function (Blueprint $table) {
            $table->string('client_request_id', 64)->nullable()->after('memo');
            $table->unique('client_request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workout_sets', function (Blueprint $table) {
            $table->dropUnique(['client_request_id']);
            $table->dropColumn('client_request_id');
        });
    }
};

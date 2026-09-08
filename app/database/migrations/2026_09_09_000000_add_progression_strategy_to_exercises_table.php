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
        Schema::table('exercises', function (Blueprint $table) {
            // Issue #27: 種目ごとに漸進法(ダブルプログレッション/リニア/5x5)を
            // 選べるようにする。default は既存の挙動(ダブルプログレッション)を
            // 一切変えないため 'double' 固定。
            $table->enum('progression_strategy', ['double', 'linear', 'five_by_five'])
                ->default('double')
                ->after('target_rep_max');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropColumn('progression_strategy');
        });
    }
};

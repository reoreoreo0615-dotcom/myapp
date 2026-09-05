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
        Schema::create('workout_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_id')->constrained('workouts')->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained('exercises')->restrictOnDelete();
            $table->tinyInteger('set_number')->unsigned();
            $table->decimal('weight', 5, 2)->default(0);
            $table->smallInteger('reps')->unsigned();
            $table->decimal('rpe', 3, 1)->nullable();
            $table->boolean('is_warmup')->default(false);
            $table->string('memo', 255)->nullable();
            $table->timestamps();

            $table->index(['exercise_id', 'workout_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workout_sets');
    }
};

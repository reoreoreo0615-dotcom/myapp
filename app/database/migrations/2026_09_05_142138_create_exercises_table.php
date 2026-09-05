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
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 80);
            $table->enum('muscle_group', ['chest', 'back', 'shoulders', 'legs', 'arms', 'core']);
            $table->enum('movement_type', ['push', 'pull', 'legs', 'core']);
            $table->enum('equipment', ['barbell', 'dumbbell', 'machine', 'cable', 'bodyweight']);
            $table->boolean('is_bodyweight')->default(false);
            $table->decimal('weight_increment', 4, 2)->default(2.50);
            $table->tinyInteger('target_rep_min')->unsigned()->default(8);
            $table->tinyInteger('target_rep_max')->unsigned()->default(12);
            $table->smallInteger('sort_order')->unsigned()->default(0);
            $table->timestamps();

            $table->index(['user_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};

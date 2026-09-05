<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Routine extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * The user who owns this routine.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The pivot rows linking this routine to its exercises.
     */
    public function routineExercises(): HasMany
    {
        return $this->hasMany(RoutineExercise::class);
    }

    /**
     * The exercises included in this routine.
     */
    public function exercises(): BelongsToMany
    {
        return $this->belongsToMany(Exercise::class, 'routine_exercises')
            ->withPivot(['sort_order', 'target_sets'])
            ->withTimestamps();
    }

    /**
     * The workout sessions performed from this routine.
     */
    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class);
    }
}

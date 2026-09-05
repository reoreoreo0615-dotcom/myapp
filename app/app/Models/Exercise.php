<?php

namespace App\Models;

use App\Enums\Equipment;
use App\Enums\MovementType;
use App\Enums\MuscleGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exercise extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'muscle_group',
        'movement_type',
        'equipment',
        'is_bodyweight',
        'weight_increment',
        'target_rep_min',
        'target_rep_max',
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
            'muscle_group' => MuscleGroup::class,
            'movement_type' => MovementType::class,
            'equipment' => Equipment::class,
            'is_bodyweight' => 'boolean',
            'weight_increment' => 'decimal:2',
            'target_rep_min' => 'integer',
            'target_rep_max' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The user who owns this custom exercise (null for default exercises).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The recorded sets for this exercise.
     */
    public function workoutSets(): HasMany
    {
        return $this->hasMany(WorkoutSet::class);
    }
}

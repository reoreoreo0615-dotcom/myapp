<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workout extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'routine_id',
        'performed_on',
        'started_at',
        'finished_at',
        'memo',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'performed_on' => 'date',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * The user who performed this workout.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The routine this workout was based on, if any.
     */
    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    /**
     * The sets recorded in this workout.
     */
    public function workoutSets(): HasMany
    {
        return $this->hasMany(WorkoutSet::class);
    }
}

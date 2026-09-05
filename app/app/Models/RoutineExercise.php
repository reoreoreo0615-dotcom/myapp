<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineExercise extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'routine_id',
        'exercise_id',
        'sort_order',
        'target_sets',
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
            'target_sets' => 'integer',
        ];
    }

    /**
     * The routine this row belongs to.
     */
    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    /**
     * The exercise this row references.
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}

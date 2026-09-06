<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workout extends Model
{
    use HasFactory;
    use SoftDeletes;

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
        'editing_started_at',
        'memo',
        'progression_snapshot',
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
            // 終了済みワークアウトを明示的な「編集モード」にした時刻(Issue #23①)。
            // 非nullの間だけ WorkoutPolicy::update が終了済みでもセットの
            // 追加・編集・削除を許可する。「修正を終える」で null に戻す。
            'editing_started_at' => 'datetime',
            // 種目ごとの「前回のセット」「今日の目標」をワークアウト開始時点で凍結したもの。
            // {@see \App\Services\WorkoutProgressionSnapshotService}
            'progression_snapshot' => 'array',
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
     *
     * withTrashed(): a routine can be soft-deleted (Issue #23③) while
     * workouts still reference it (routine_exercises / workouts.routine_id
     * are physical FKs that a logical delete does not touch). Without this,
     * an in-progress or past workout based on a since-deleted routine would
     * resolve `routine` to null and crash when the controller reads its
     * routineExercises.
     */
    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class)->withTrashed();
    }

    /**
     * The sets recorded in this workout.
     */
    public function workoutSets(): HasMany
    {
        return $this->hasMany(WorkoutSet::class);
    }
}

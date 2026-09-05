<?php

namespace App\Models;

use Database\Factories\BodyLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BodyLog extends Model
{
    /** @use HasFactory<BodyLogFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'measured_on',
        'weight_kg',
        'body_fat_percentage',
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
            'measured_on' => 'date',
            'weight_kg' => 'decimal:2',
            'body_fat_percentage' => 'decimal:1',
        ];
    }

    /**
     * The user this measurement belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

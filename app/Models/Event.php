<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    /** Colors you can pick for an event (name => hex). */
    public const COLORS = [
        'indigo' => '#6366f1',
        'sky' => '#0ea5e9',
        'emerald' => '#10b981',
        'amber' => '#f59e0b',
        'rose' => '#f43f5e',
        'violet' => '#8b5cf6',
        'slate' => '#64748b',
    ];

    protected $fillable = [
        'title', 'description', 'location', 'url', 'starts_at', 'ends_at',
        'all_day', 'color', 'is_public', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->where('starts_at', '>=', now()->startOfDay())
            ->orWhere('ends_at', '>=', now()));
    }

    public function hex(): string
    {
        return self::COLORS[$this->color] ?? self::COLORS['indigo'];
    }
}

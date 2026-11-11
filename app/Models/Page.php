<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = [
        'title', 'slug', 'blocks', 'visibility', 'is_published', 'is_homepage',
        'show_in_nav', 'nav_order', 'meta_title', 'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'is_published' => 'boolean',
            'is_homepage' => 'boolean',
            'show_in_nav' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Only one page can be the homepage.
        static::saving(function (Page $page) {
            if ($page->is_homepage) {
                static::where('id', '!=', $page->id ?? 0)->update(['is_homepage' => false]);
            }
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function isMembersOnly(): bool
    {
        return $this->visibility === 'members';
    }

    public function url(): string
    {
        return $this->is_homepage ? route('home') : route('page.show', $this->slug);
    }
}

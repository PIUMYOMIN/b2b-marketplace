<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsletterSubscriber extends Model
{
    protected $fillable = [
        'email', 'name', 'confirm_token', 'confirmed_at',
        'unsubscribe_token', 'unsubscribed_at',
        'pref_promotions', 'pref_new_sellers',
        'pref_product_updates', 'pref_platform_news',
        'user_id', 'source',
    ];

    protected $casts = [
        'confirmed_at'    => 'datetime',
        'unsubscribed_at' => 'datetime',
        'pref_promotions'      => 'boolean',
        'pref_new_sellers'     => 'boolean',
        'pref_product_updates' => 'boolean',
        'pref_platform_news'   => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isConfirmed(): bool
    {
        return !is_null($this->confirmed_at);
    }

    public function isActive(): bool
    {
        return $this->isConfirmed() && is_null($this->unsubscribed_at);
    }

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    /** Scope: confirmed and not unsubscribed */
    public function scopeActive($query)
    {
        return $query
            ->whereNotNull('confirmed_at')
            ->whereNull('unsubscribed_at');
    }
}
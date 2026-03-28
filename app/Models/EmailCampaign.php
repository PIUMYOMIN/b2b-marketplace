<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailCampaign extends Model
{
    protected $fillable = [
        'name', 'subject', 'body_html', 'body_text',
        'audience', 'audience_filter',
        'status', 'scheduled_at', 'sent_at',
        'recipients_count', 'delivered_count',
        'opened_count', 'clicked_count',
        'bounced_count', 'unsubscribed_count',
        'created_by',
    ];

    protected $casts = [
        'audience_filter' => 'array',
        'scheduled_at'    => 'datetime',
        'sent_at'         => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Open rate as a percentage */
    public function getOpenRateAttribute(): float
    {
        return $this->delivered_count > 0
            ? round(($this->opened_count / $this->delivered_count) * 100, 1)
            : 0.0;
    }
}
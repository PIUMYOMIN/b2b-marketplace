<?php
// app/Models/ReportComment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportComment extends Model
{
    protected $fillable = [
        'report_id', 'user_id', 'body', 'attachments', 'author_type', 'is_internal',
    ];

    protected $casts = [
        'attachments' => 'array',
        'is_internal' => 'boolean',
    ];

    public function report()  { return $this->belongsTo(Report::class); }
    public function author()  { return $this->belongsTo(User::class, 'user_id'); }
}
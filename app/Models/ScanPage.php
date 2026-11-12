<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanPage extends Model
{
    protected $fillable = ['scan_id', 'page_id', 'status', 'http_status', 'violations', 'passes', 'duration_ms', 'error'];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}

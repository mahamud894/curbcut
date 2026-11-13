<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistItem extends Model
{
    public const STATUSES = ['not_tested', 'pass', 'fail', 'not_applicable'];

    protected $fillable = ['site_id', 'criterion', 'status', 'notes', 'tested_by', 'tested_at'];

    protected function casts(): array
    {
        return ['tested_at' => 'datetime'];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tested_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityDocument extends Model
{
    protected $fillable = [
        'work_activity_id', 'title', 'path', 'original_name', 'mime_type',
        'size', 'uploaded_by_admin_id',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(WorkActivity::class, 'work_activity_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'uploaded_by_admin_id');
    }
}

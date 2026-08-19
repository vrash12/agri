<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupFile extends Model
{
    protected $fillable = [
        'municipality_id',
        'disk',
        'folder',
        'original_name',
        'stored_name',
        'path',
        'size',
        'mime',
        'sha256',
        'notes',
        'uploaded_by',
    ];

    protected $casts = [
        'municipality_id' => 'integer',
        'size' => 'integer',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }
}

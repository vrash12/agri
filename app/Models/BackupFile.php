<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupFile extends Model
{
  protected $fillable = [
    'disk','folder','original_name','stored_name','path','size','mime','sha256','notes','uploaded_by'
  ];

  public function uploader()
  {
    return $this->belongsTo(User::class, 'uploaded_by');
  }
}
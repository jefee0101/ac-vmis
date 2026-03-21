<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WellnessAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'wellness_log_id',
        'file_path',
        'file_type',
        'uploaded_by',
    ];

    public function wellnessLog()
    {
        return $this->belongsTo(WellnessLog::class, 'wellness_log_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}


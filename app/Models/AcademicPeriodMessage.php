<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPeriodMessage extends Model
{
    protected $fillable = [
        'academic_period_id',
        'message',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function period()
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

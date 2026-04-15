<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnnouncementEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'type',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function recipients()
    {
        return $this->hasMany(Announcement::class, 'event_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

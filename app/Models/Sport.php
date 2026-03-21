<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Team;

class Sport extends Model
{
    use HasFactory;

    protected $table = 'sports';

    protected $fillable = [
        'name',
        'description'
    ];

    // Relations
    public function teams()
    {
        return $this->hasMany(Team::class, 'sport_id');
    }
}

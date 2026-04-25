<?php

namespace App\Models;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Sport extends Model
{
    use HasFactory;

    public const NAME_BASKETBALL = 'Basketball';
    public const NAME_SOCCER = 'Soccer';
    public const NAME_VOLLEYBALL = 'Volleyball';

    public const SUPPORTED_NAMES = [
        self::NAME_BASKETBALL,
        self::NAME_SOCCER,
        self::NAME_VOLLEYBALL,
    ];

    protected $table = 'sports';

    protected $fillable = [
        'name',
    ];

    // Relations
    public function teams()
    {
        return $this->hasMany(Team::class, 'sport_id');
    }

    public function scopeSupported(Builder $query): Builder
    {
        return $query->whereIn('name', self::SUPPORTED_NAMES);
    }

    public static function supportedNames(): array
    {
        return self::SUPPORTED_NAMES;
    }
}

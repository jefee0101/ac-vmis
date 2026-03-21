<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountApproval extends Model
{
    // Explicit table name (good practice)
    protected $table = 'account_approvals';

    // Mass-assignable fields
    protected $fillable = [
        'user_id',
        'admin_id',
        'decision', // pending | approved | rejected
        'remarks',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    // The user being reviewed
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // The admin who approved/rejected (nullable)
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}

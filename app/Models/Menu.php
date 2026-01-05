<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $guarded = [];

    protected $casts = [
        'active_routes' => 'array', // Penting biar bisa di-loop di blade
        'is_visible' => 'boolean'
    ];
}
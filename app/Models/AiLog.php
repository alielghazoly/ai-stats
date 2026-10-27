<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'tools' => 'array',
    ];
}

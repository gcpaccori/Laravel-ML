<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParametroBanda extends Model
{

    protected function casts(): array
    {
        return [
            'low_score'  => 'float',
            'high_score' => 'float',
        ];
    }
}

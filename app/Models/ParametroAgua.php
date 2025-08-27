<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParametroAgua extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fecha_medicion'    => 'datetime',
            'temperatura'       => 'float',
            'ph'                => 'float',
            'oxigeno_disuelto'  => 'float',
            'ion_nitrato'       => 'float',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function piscina(): BelongsTo
    {
        return $this->belongsTo(Piscina::class);
    }

}

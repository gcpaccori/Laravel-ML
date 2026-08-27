<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlimentacionHorario extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'hora' => 'datetime:H:i',
        ];
    }

    public function tabla()
    {
        return $this->belongsTo(AlimentacionTabla::class);
    }
}

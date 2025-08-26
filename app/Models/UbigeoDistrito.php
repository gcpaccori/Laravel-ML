<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UbigeoDistrito extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    public function provincia(): BelongsTo
    {
        return $this->belongsTo(UbigeoProvincia::class, 'provincia_id');
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(UbigeoDepartamento::class, 'departamento_id');
    }
}

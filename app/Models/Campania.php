<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Campania extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function piscigranja() : BelongsTo
    {
        return $this->belongsTo(Piscigranja::class);
    }

    public function etapas() : HasMany
    {
        return $this->hasMany(CampaniaEtapa::class);
    }
}

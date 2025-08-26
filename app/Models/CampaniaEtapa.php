<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CampaniaEtapa extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function campania() : BelongsTo
    {
        return $this->belongsTo(Campania::class);
    }

    public function etapa() : BelongsTo
    {
        return $this->belongsTo(Etapa::class);
    }

    public function piscinas() : BelongsToMany
    {
        return $this->belongsToMany(Piscina::class, 'campania_etapa_piscinas');
    }
}

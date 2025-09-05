<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaniaEtapa extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function campaniaEspecie() : BelongsTo
    {
        return $this->belongsTo(CampaniaEspecie::class);
    }

    public function etapa() : BelongsTo
    {
        return $this->belongsTo(Etapa::class);
    }

    public function piscina() : belongsTo
    {
        return $this->belongsTo(Piscina::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UbigeoProvincia extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(UbigeoProvincia::class);
    }

    public function distritos(): HasMany
    {
        return $this->hasMany(UbigeoDistrito::class);
    }
}

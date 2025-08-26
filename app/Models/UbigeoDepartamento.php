<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UbigeoDepartamento extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    public function provincias(): HasMany
    {
        return $this->hasMany(UbigeoProvincia::class);
    }
}

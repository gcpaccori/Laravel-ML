<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sistema extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function modulos() {
        return $this->hasMany(Modulo::class);
    }

}

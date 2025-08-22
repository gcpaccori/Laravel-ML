<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionLog extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_activity' => 'datetime',
            'location' => 'json'
        ];
    }

    // Relación con el modelo User
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Observers;

use App\Models\ParametroAgua;
use App\Services\AlarmaParametrosService;

class ParametroAguaObserver
{
    public function created(ParametroAgua $medicion): void
    {
        app(AlarmaParametrosService::class)->evaluarCalidadAgua($medicion);
    }
}

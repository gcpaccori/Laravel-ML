<?php

namespace App\Observers;

use App\Models\ParametroAmbiente;
use App\Services\AlarmaParametrosService;

class ParametroAmbienteObserver
{
    public function created(ParametroAmbiente $medicion): void
    {
        app(AlarmaParametrosService::class)->evaluarCalidadAmbiente($medicion);
    }
}

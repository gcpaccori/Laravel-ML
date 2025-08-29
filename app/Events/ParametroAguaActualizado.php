<?php

namespace App\Events;

use App\Models\ParametroAgua;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow; // ← Cambiar esto

class ParametroAguaActualizado implements ShouldBroadcastNow // ← Cambiar esto
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $parametroAgua;

    public function __construct(ParametroAgua $parametroAgua)
    {
        $this->parametroAgua = $parametroAgua;
    }

    public function broadcastOn()  // ← Sin : array
    {
        return new Channel('parametros-agua'); // ← Canal público como el ejemplo
    }

    public function broadcastAs()
    {
        return 'parametro.actualizado';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => "Parámetros de agua actualizados.",
        ];
    }
}

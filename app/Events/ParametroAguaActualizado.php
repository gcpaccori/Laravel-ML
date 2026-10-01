<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ParametroAguaActualizado implements ShouldBroadcastNow
    // ShouldBroadcast usa worker y cola.
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct()
    {
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

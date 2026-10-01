<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class AlarmaGenerada implements ShouldBroadcast
{
    // ShouldBroadcastNow no usa worker, es inmediato.
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct()
    {
    }

    public function broadcastOn()
    {
        return new Channel('alertas.notificaciones');
    }

    public function broadcastAs()
    {
        return 'alarma.generada';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => "Notificación nueva.",
        ];
    }
}

<?php

namespace App\Events;

use App\Models\Alarma;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlarmaModeloGenerada implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Alarma $alarma)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('alarmas.modelos'),
            new PrivateChannel("alarmas.piscigranja.{$this->alarma->piscigranja_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'alarma.generada';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->alarma->id,
            'piscigranja_id' => $this->alarma->piscigranja_id,
            'piscina_id' => $this->alarma->piscina_id,
            'modulo' => $this->alarma->modulo,
            'parametro' => $this->alarma->parametro,
            'nivel' => $this->alarma->nivel,
            'titulo' => $this->alarma->titulo,
            'mensaje' => $this->alarma->mensaje,
            'estado' => $this->alarma->estado,
            'created_at' => $this->alarma->created_at?->toIso8601String(),
        ];
    }
}

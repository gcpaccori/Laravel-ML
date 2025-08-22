<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuloResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'    => $this->id,
            'sistema_id'  => $this->sistema_id,
            // 'sistema' => $this->sistema,
            'name'  => $this->name,
            'cod_father'  => $this->cod_father,
            'url'  => $this->url,
            'icon'  => $this->icon,
            'order'  => $this->order,
            'active'  => $this->active,
            'acciones' => $this->acciones,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString()
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccionResource extends JsonResource
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
            'accion'  => $this->accion,
            'name'  => $this->name,
            'icon'  => $this->icon,
            'type'   => $this->type,
            'name_funcion' => $this->name_funcion,
            'active'  => $this->active,
            'location'  => $this->location,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}

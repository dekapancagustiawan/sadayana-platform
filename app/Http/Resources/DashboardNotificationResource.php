<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardNotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'type_icon' => $this->type?->icon(),
            'type_color' => $this->type?->color(),
            'title' => $this->title,
            'message' => $this->message,
            'reference_url' => $this->url,
            'is_read' => $this->is_read,
            'read_at' => $this->read_at?->toISOString(),
            'time_ago' => $this->created_at?->diffForHumans(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

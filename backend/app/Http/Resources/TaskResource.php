<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'project_id' => $this->project_id,
            'title' => $this->title,
            'description' => $this->description,
            'deadline' => $this->deadline?->toDateString(),
            'priority' => $this->priority,
            'status' => $this->status,
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ]),
            'client' => $this->when($this->project?->relationLoaded('client'), fn () => [
                'id' => $this->project->client->id,
                'name' => $this->project->client->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

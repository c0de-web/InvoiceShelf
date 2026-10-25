<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EngagementMilestoneResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'name' => $this->name,
            'amount' => $this->amount,
            'percent' => $this->percent !== null ? (float) $this->percent : null,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'status' => $this->status(),
            'is_locked' => $this->isLocked(),
            'settled_at' => $this->settled_at?->format('Y-m-d'),
            'settled_reference' => $this->settled_reference,
            'invoice_id' => $this->invoice_id,
            'invoice' => $this->invoice ? [
                'id' => $this->invoice->id,
                'invoice_number' => $this->invoice->invoice_number,
                'status' => $this->invoice->status,
                'paid_status' => $this->invoice->paid_status,
                'due_amount' => $this->invoice->due_amount,
                'due_date' => $this->invoice->due_date,
            ] : null,
        ];
    }
}

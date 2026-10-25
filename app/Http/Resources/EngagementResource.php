<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EngagementResource extends JsonResource
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
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'contract_value' => $this->contract_value,
            'exchange_rate' => $this->exchange_rate !== null ? (float) $this->exchange_rate : null,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'auto_invoice' => $this->auto_invoice,
            'estimate_id' => $this->estimate_id,
            'customer_id' => $this->customer_id,
            'customer' => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'currency' => $this->customer->currency ? new CurrencyResource($this->customer->currency) : null,
            ],
            'summary' => $this->summary(),
            'milestones' => EngagementMilestoneResource::collection($this->milestones),
            'recurring_invoices' => $this->recurringInvoices->map(fn ($recurring) => [
                'id' => $recurring->id,
                'frequency' => $recurring->frequency,
                'status' => $recurring->status,
                'next_invoice_at' => $recurring->next_invoice_at,
                'total' => $recurring->total,
            ]),
            'invoices' => $this->when($request->route('engagement') !== null, fn () => $this->invoicesQuery()
                ->latest('invoice_date')
                ->get()
                ->map(fn ($invoice) => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date,
                    'due_date' => $invoice->due_date,
                    'total' => $invoice->total,
                    'due_amount' => $invoice->due_amount,
                    'status' => $invoice->status,
                    'paid_status' => $invoice->paid_status,
                ])),
            'created_at' => $this->created_at,
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Work for a customer: a fixed-price project billed by milestones, or a retainer or service
 * billed by its linked recurring invoices. Amounts are in the customer's currency, minor units.
 */
class Engagement extends Model
{
    public const TYPE_PROJECT = 'PROJECT';

    public const TYPE_RETAINER = 'RETAINER';

    public const TYPE_SERVICE = 'SERVICE';

    public const TYPES = [self::TYPE_PROJECT, self::TYPE_RETAINER, self::TYPE_SERVICE];

    public const STATUSES = ['ACTIVE', 'ON_HOLD', 'COMPLETED', 'CANCELLED'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'contract_value' => 'integer',
            'auto_invoice' => 'boolean',
            'start_date' => 'date:Y-m-d',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(EngagementMilestone::class)->orderBy('position');
    }

    public function recurringInvoices(): HasMany
    {
        return $this->hasMany(RecurringInvoice::class);
    }

    public function isProject(): bool
    {
        return $this->type === self::TYPE_PROJECT;
    }

    /**
     * Its invoices: those filed under it, and those its linked recurring invoices generated.
     */
    public function invoicesQuery(): Builder
    {
        return Invoice::query()->where(fn ($query) => $query
            ->where('engagement_id', $this->id)
            ->orWhereIn('recurring_invoice_id', $this->recurringInvoices()->select('id')));
    }

    /**
     * Invoiced, paid and pending, always computed from the records.
     *
     * @return array<string, int|null>
     */
    public function summary(): array
    {
        $invoices = $this->invoicesQuery()->get(['total', 'due_amount']);
        $settled = $this->isProject()
            ? (int) $this->milestones->whereNotNull('settled_at')->sum('amount')
            : 0;

        $invoiced = $settled + (int) $invoices->sum('total');
        $paid = $settled + (int) $invoices->sum(fn ($invoice) => $invoice->total - $invoice->due_amount);
        $contract = $this->isProject() ? (int) $this->contract_value : null;

        return [
            'contract' => $contract,
            'invoiced' => $invoiced,
            'paid' => $paid,
            'pending' => $contract !== null ? max($contract - $paid, 0) : $invoiced - $paid,
            'invoiced_percent' => $contract ? (int) round($invoiced * 100 / $contract) : null,
            'paid_percent' => $contract ? (int) round($paid * 100 / $contract) : null,
        ];
    }

    public function scopeWhereCompany($query)
    {
        $query->where('engagements.company_id', request()->header('company'));
    }

    public function scopeApplyFilters($query, array $filters)
    {
        $filters = collect($filters);

        if ($filters->get('customer_id')) {
            $query->where('customer_id', $filters->get('customer_id'));
        }

        if ($filters->get('type')) {
            $query->where('type', $filters->get('type'));
        }

        if ($filters->get('status')) {
            $query->where('status', $filters->get('status'));
        }

        if ($filters->get('search')) {
            $search = $filters->get('search');
            $query->where(fn ($q) => $q
                ->where('engagements.name', 'LIKE', "%{$search}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'LIKE', "%{$search}%")));
        }
    }
}

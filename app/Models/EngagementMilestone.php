<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One portion of a project's payment schedule. It becomes one invoice, or is recorded as
 * settled before InvoiceShelf (existing projects).
 */
class EngagementMilestone extends Model
{
    public const STATUS_PLANNED = 'PLANNED';

    public const STATUS_INVOICED = 'INVOICED';

    public const STATUS_PAID = 'PAID';

    public const STATUS_SETTLED = 'SETTLED';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'due_date' => 'date:Y-m-d',
            'settled_at' => 'date:Y-m-d',
        ];
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Follows the records: settled, or its invoice's paid status, or planned.
     */
    public function status(): string
    {
        if ($this->settled_at) {
            return self::STATUS_SETTLED;
        }

        if (! $this->invoice) {
            return self::STATUS_PLANNED;
        }

        return $this->invoice->paid_status === Invoice::STATUS_PAID ? self::STATUS_PAID : self::STATUS_INVOICED;
    }

    /**
     * Invoiced or settled: its name and amount are fixed from then on.
     */
    public function isLocked(): bool
    {
        return $this->invoice_id !== null || $this->settled_at !== null;
    }
}

<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\EngagementMilestone;
use App\Models\Invoice;
use App\Support\MoneyConversion;
use App\Support\PublicToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Turns a project milestone into a draft invoice for its amount: one line named after the
 * milestone, filed under the engagement. The same path serves the "Create invoice" button and
 * the daily auto-invoicing of due milestones.
 */
class MilestoneInvoicer
{
    public function invoice(EngagementMilestone $milestone, ?int $creatorId = null): Invoice
    {
        return DB::transaction(function () use ($milestone, $creatorId) {
            $milestone = EngagementMilestone::lockForUpdate()->with('engagement.customer')->findOrFail($milestone->id);

            if ($milestone->isLocked()) {
                throw new InvalidArgumentException("\"{$milestone->name}\" is already invoiced or settled.");
            }

            $engagement = $milestone->engagement;
            $companyId = $engagement->company_id;
            $exchangeRate = (float) ($engagement->exchange_rate ?: 1);

            $serial = (new SerialNumberFormatter)
                ->setModel(new Invoice)
                ->setCompany($companyId)
                ->setCustomer($engagement->customer_id)
                ->setNextNumbers();

            $days = (int) CompanySetting::getSetting('invoice_due_date_days', $companyId) ?: 7;
            $amount = $milestone->amount;

            $invoice = Invoice::create([
                'creator_id' => $creatorId ?? $engagement->creator_id,
                'invoice_date' => Carbon::today()->format('Y-m-d'),
                'due_date' => Carbon::today()->addDays($days)->format('Y-m-d'),
                'status' => Invoice::STATUS_DRAFT,
                'paid_status' => Invoice::STATUS_UNPAID,
                'company_id' => $companyId,
                'customer_id' => $engagement->customer_id,
                'currency_id' => $engagement->customer->currency_id,
                'engagement_id' => $engagement->id,
                'template_name' => 'invoice1',
                'tax_per_item' => 'NO',
                'discount_per_item' => 'NO',
                'discount_type' => 'fixed',
                'discount' => 0,
                'discount_val' => 0,
                'sub_total' => $amount,
                'tax' => 0,
                'total' => $amount,
                'due_amount' => $amount,
                'exchange_rate' => $exchangeRate,
                'base_sub_total' => MoneyConversion::toBaseMinor($amount, $exchangeRate),
                'base_discount_val' => 0,
                'base_tax' => 0,
                'base_total' => MoneyConversion::toBaseMinor($amount, $exchangeRate),
                'base_due_amount' => MoneyConversion::toBaseMinor($amount, $exchangeRate),
                'invoice_number' => $serial->getNextNumber(),
                'sequence_number' => $serial->nextSequenceNumber,
                'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
            ]);
            $invoice->unique_hash = PublicToken::make();
            $invoice->save();

            Invoice::createItems($invoice, [[
                'name' => $milestone->name,
                'description' => $this->description($milestone),
                'quantity' => 1,
                'price' => $amount,
                'discount_type' => 'fixed',
                'discount' => 0,
                'discount_val' => 0,
                'tax' => 0,
                'total' => $amount,
            ]]);

            $milestone->update(['invoice_id' => $invoice->id]);

            return $invoice;
        });
    }

    /**
     * "Custom website · 25% of the contract", or just the project name for a fixed amount.
     */
    private function description(EngagementMilestone $milestone): string
    {
        $project = $milestone->engagement->name;

        return $milestone->percent !== null
            ? sprintf('%s · %s%% of the contract', $project, rtrim(rtrim((string) $milestone->percent, '0'), '.'))
            : $project;
    }
}

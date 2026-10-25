<?php

namespace App\Console\Commands;

use App\Models\CompanySetting;
use App\Models\EngagementMilestone;
use App\Services\MilestoneInvoicer;
use Carbon\Carbon;
use Illuminate\Console\Command;

class InvoiceDueMilestones extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'engagements:invoice-due-milestones';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create draft invoices for due milestones of active projects that auto-invoice';

    /**
     * Execute the console command.
     */
    public function handle(MilestoneInvoicer $invoicer): int
    {
        $milestones = EngagementMilestone::query()
            ->whereNull('invoice_id')
            ->whereNull('settled_at')
            ->whereNotNull('due_date')
            ->whereHas('engagement', fn ($query) => $query->where('auto_invoice', true)->where('status', 'ACTIVE'))
            ->with('engagement')
            ->get();

        foreach ($milestones as $milestone) {
            $timezone = CompanySetting::getSetting('time_zone', $milestone->engagement->company_id) ?: config('app.timezone');

            if ($milestone->due_date->format('Y-m-d') <= Carbon::now($timezone)->format('Y-m-d')) {
                $invoice = $invoicer->invoice($milestone);
                $this->info("{$milestone->engagement->name} – {$milestone->name}: {$invoice->invoice_number}");
            }
        }

        return self::SUCCESS;
    }
}

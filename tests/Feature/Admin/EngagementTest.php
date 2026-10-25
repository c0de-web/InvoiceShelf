<?php

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Engagement;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->owner = User::where('role', 'super admin')->first();
    $this->company = $this->owner->companies()->first();
    $this->withHeaders(['company' => $this->company->id]);
    Sanctum::actingAs($this->owner, ['*']);

    $this->customer = engagementCustomer($this->company);
});

function engagementCustomer(Company $company): Customer
{
    return Customer::factory()->create([
        'company_id' => $company->id,
        'currency_id' => CompanySetting::getSetting('currency', $company->id),
    ]);
}

/**
 * A project for BZ$1,000.00 split 25/25/50, unless overridden.
 */
function projectPayload(Customer $customer, array $overrides = []): array
{
    return [
        'type' => 'PROJECT',
        'customer_id' => $customer->id,
        'name' => 'Custom website',
        'contract_value' => 100000,
        'milestones' => [
            ['name' => 'Deposit on signing', 'percent' => 25],
            ['name' => 'Design approved', 'percent' => 25],
            ['name' => 'Launch', 'percent' => 50],
        ],
        ...$overrides,
    ];
}

function createProject(Customer $customer, array $overrides = []): Engagement
{
    $id = postJson('/api/v1/engagements', projectPayload($customer, $overrides))->assertCreated()->json('data.id');

    return Engagement::with('milestones')->find($id);
}

function invoiceMilestone(Engagement $engagement, int $index): Invoice
{
    $milestone = $engagement->milestones[$index];
    $invoiceId = postJson("/api/v1/engagements/{$engagement->id}/milestones/{$milestone->id}/invoice")
        ->assertCreated()->json('data.invoice_id');

    return Invoice::find($invoiceId);
}

test('a project splits its contract value by percentage', function () {
    $project = createProject($this->customer);

    expect($project->milestones->pluck('amount')->all())->toBe([25000, 25000, 50000])
        ->and($project->milestones->pluck('percent')->map(fn ($p) => (float) $p)->all())->toBe([25.0, 25.0, 50.0]);
});

test('the last milestone absorbs the rounding of percentages', function () {
    $project = createProject($this->customer, ['contract_value' => 100001]);

    expect($project->milestones->pluck('amount')->all())->toBe([25000, 25000, 50001]);
});

test('fixed milestone amounts must add up to the contract value', function () {
    postJson('/api/v1/engagements', projectPayload($this->customer, [
        'milestones' => [['name' => 'Half', 'amount' => 30000], ['name' => 'Rest', 'amount' => 30000]],
    ]))->assertUnprocessable()->assertJsonValidationErrors('milestones');
});

test('a retainer links the customer\'s recurring invoices only', function () {
    $own = RecurringInvoice::factory()->create(['company_id' => $this->company->id, 'customer_id' => $this->customer->id]);
    $other = RecurringInvoice::factory()->create(['company_id' => $this->company->id, 'customer_id' => engagementCustomer($this->company)->id]);

    postJson('/api/v1/engagements', [
        'type' => 'RETAINER', 'customer_id' => $this->customer->id, 'name' => 'General retainer',
        'recurring_invoice_ids' => [$other->id],
    ])->assertUnprocessable()->assertJsonValidationErrors('recurring_invoice_ids.0');

    $id = postJson('/api/v1/engagements', [
        'type' => 'RETAINER', 'customer_id' => $this->customer->id, 'name' => 'General retainer',
        'recurring_invoice_ids' => [$own->id],
    ])->assertCreated()->json('data.id');

    expect($own->fresh()->engagement_id)->toBe($id);
});

test('an existing project records what was settled before InvoiceShelf', function () {
    $project = createProject($this->customer, ['milestones' => [
        ['name' => 'Deposit on signing', 'percent' => 25, 'settled_at' => '2026-08-01', 'settled_reference' => 'Bank transfer 5521'],
        ['name' => 'Design approved', 'percent' => 25],
        ['name' => 'Launch', 'percent' => 50],
    ]]);

    getJson("/api/v1/engagements/{$project->id}")->assertOk()
        ->assertJsonPath('data.milestones.0.status', 'SETTLED')
        ->assertJsonPath('data.summary.paid', 25000)
        ->assertJsonPath('data.summary.pending', 75000)
        ->assertJsonPath('data.summary.paid_percent', 25);
});

test('a milestone becomes a draft invoice for its amount, once', function () {
    $project = createProject($this->customer);
    $invoice = invoiceMilestone($project, 0);

    expect($invoice->total)->toBe(25000)
        ->and($invoice->status)->toBe(Invoice::STATUS_DRAFT)
        ->and($invoice->customer_id)->toBe($this->customer->id)
        ->and($invoice->engagement_id)->toBe($project->id)
        ->and($invoice->items->first()->name)->toBe('Deposit on signing');

    postJson("/api/v1/engagements/{$project->id}/milestones/{$project->milestones[0]->id}/invoice")
        ->assertUnprocessable();
});

test('deleting a milestone invoice puts the milestone back to planned', function () {
    $project = createProject($this->customer);
    $invoice = invoiceMilestone($project, 0);

    postJson('/api/v1/invoices/delete', ['ids' => [$invoice->id]])->assertOk();

    getJson("/api/v1/engagements/{$project->id}")->assertJsonPath('data.milestones.0.status', 'PLANNED');
});

test('a milestone is paid when its invoice is, and part-payments count towards the total', function () {
    $project = createProject($this->customer);
    $deposit = invoiceMilestone($project, 0);
    $design = invoiceMilestone($project, 1);

    $deposit->update(['due_amount' => 0, 'paid_status' => Invoice::STATUS_PAID]);
    $design->update(['due_amount' => 15000, 'paid_status' => Invoice::STATUS_PARTIALLY_PAID]);

    getJson("/api/v1/engagements/{$project->id}")->assertOk()
        ->assertJsonPath('data.milestones.0.status', 'PAID')
        ->assertJsonPath('data.milestones.1.status', 'INVOICED')
        ->assertJsonPath('data.summary.invoiced', 50000)
        ->assertJsonPath('data.summary.paid', 35000)
        ->assertJsonPath('data.summary.pending', 65000);
});

test('the contract value cannot drop below what is already invoiced', function () {
    $project = createProject($this->customer);
    invoiceMilestone($project, 0);
    invoiceMilestone($project, 1);

    putJson("/api/v1/engagements/{$project->id}", projectPayload($this->customer, [
        'contract_value' => 40000,
        'milestones' => [
            ['id' => $project->milestones[0]->id, 'name' => 'Deposit on signing', 'amount' => 25000],
            ['id' => $project->milestones[1]->id, 'name' => 'Design approved', 'amount' => 25000],
        ],
    ]))->assertUnprocessable()->assertJsonValidationErrors('contract_value');
});

test('an invoiced milestone can be neither removed nor changed', function () {
    $project = createProject($this->customer);
    invoiceMilestone($project, 0);

    putJson("/api/v1/engagements/{$project->id}", projectPayload($this->customer, [
        'milestones' => [
            ['id' => $project->milestones[1]->id, 'name' => 'Design approved', 'amount' => 50000],
            ['id' => $project->milestones[2]->id, 'name' => 'Launch', 'amount' => 50000],
        ],
    ]))->assertUnprocessable()->assertJsonValidationErrors('milestones');

    putJson("/api/v1/engagements/{$project->id}", projectPayload($this->customer, [
        'milestones' => [
            ['id' => $project->milestones[0]->id, 'name' => 'Renamed deposit', 'amount' => 25000],
            ['id' => $project->milestones[1]->id, 'name' => 'Design approved', 'amount' => 25000],
            ['id' => $project->milestones[2]->id, 'name' => 'Launch', 'amount' => 50000],
        ],
    ]))->assertUnprocessable()->assertJsonValidationErrors('milestones');
});

test('a planned milestone can be rescheduled and the rest re-split', function () {
    $project = createProject($this->customer);
    invoiceMilestone($project, 0);

    putJson("/api/v1/engagements/{$project->id}", projectPayload($this->customer, [
        'milestones' => [
            ['id' => $project->milestones[0]->id, 'name' => 'Deposit on signing', 'amount' => 25000],
            ['id' => $project->milestones[1]->id, 'name' => 'Design approved', 'amount' => 35000, 'due_date' => '2026-12-01'],
            ['name' => 'Training', 'amount' => 40000],
        ],
    ]))->assertOk();

    expect($project->fresh()->milestones->pluck('amount')->all())->toBe([25000, 35000, 40000]);
});

test('another company\'s engagement or customer is out of reach', function () {
    $other = Company::factory()->create(['owner_id' => User::factory()->create(['role' => 'user'])->id]);
    $other->setupRoles();
    $foreignCustomer = engagementCustomer($other);
    $foreign = Engagement::create([
        'company_id' => $other->id, 'customer_id' => $foreignCustomer->id, 'type' => 'SERVICE', 'name' => 'Theirs',
    ]);

    getJson("/api/v1/engagements/{$foreign->id}")->assertForbidden();
    postJson('/api/v1/engagements', projectPayload($foreignCustomer))
        ->assertUnprocessable()->assertJsonValidationErrors('customer_id');
    expect(collect(getJson('/api/v1/engagements')->json('data'))->pluck('name'))->not->toContain('Theirs');
});

test('an engagement with invoices cannot be deleted', function () {
    $project = createProject($this->customer);
    invoiceMilestone($project, 0);

    deleteJson("/api/v1/engagements/{$project->id}")->assertUnprocessable();

    expect(Engagement::find($project->id))->not->toBeNull();
});

test('due milestones of auto-invoicing projects become invoices; others wait for a click', function () {
    Carbon::setTestNow('2026-11-01');
    $milestones = [
        ['name' => 'Due', 'percent' => 50, 'due_date' => '2026-11-01'],
        ['name' => 'Later', 'percent' => 50, 'due_date' => '2026-12-01'],
    ];
    $auto = createProject($this->customer, ['auto_invoice' => true, 'milestones' => $milestones]);
    $manual = createProject($this->customer, ['name' => 'Manual', 'milestones' => $milestones]);

    Artisan::call('engagements:invoice-due-milestones');

    expect($auto->fresh()->milestones->pluck('invoice_id')->map(fn ($id) => $id !== null)->all())->toBe([true, false])
        ->and($manual->fresh()->milestones->whereNotNull('invoice_id'))->toBeEmpty();
});

test('the list shows each engagement with its figures', function () {
    $project = createProject($this->customer);
    invoiceMilestone($project, 0)->update(['due_amount' => 0, 'paid_status' => Invoice::STATUS_PAID]);

    $row = collect(getJson('/api/v1/engagements')->assertOk()->json('data'))->firstWhere('id', $project->id);

    expect($row['type'])->toBe('PROJECT')
        ->and($row['customer']['id'])->toBe($this->customer->id)
        ->and($row['summary']['paid_percent'])->toBe(25)
        ->and($row['summary']['pending'])->toBe(75000);
});

<?php

use App\Models\CompanySetting;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\BrandColor;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;

use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->owner = User::where('role', 'super admin')->first();
    $this->companyId = $this->owner->companies()->first()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($this->owner, ['*']);
});

function saveBrandColor(?string $color)
{
    return postJson('/api/v1/company/settings', ['settings' => ['brand_color' => $color]]);
}

function invoicePdfHtml(int $companyId): string
{
    $invoice = Invoice::factory()->create(['company_id' => $companyId]);

    request()->merge(['preview' => true]);

    return $invoice->getPDFData()->render();
}

test('an owner saves a brand colour', function () {
    saveBrandColor('#0F766E')->assertOk();

    expect(CompanySetting::getSetting('brand_color', $this->companyId))->toBe('#0F766E');
});

test('a brand colour must be a six-digit hex colour', function (string $color) {
    saveBrandColor($color)->assertUnprocessable();
})->with(['teal', '#0F766', '#0F766EZ', '0F766E', 'rgb(15, 118, 110)']);

test('a brand colour that suits white button text is accepted', function () {
    // #198A75 is 4.26:1 with white: below the small-text AA level, fine for buttons.
    saveBrandColor('#198A75')->assertOk();
});

test('a brand colour white text cannot be read on is refused', function () {
    saveBrandColor('#FACC15')->assertUnprocessable()
        ->assertJsonValidationErrors('settings.brand_color');
});

test('clearing the brand colour brings back the default', function () {
    saveBrandColor('#0F766E')->assertOk();
    saveBrandColor(null)->assertOk();

    expect(BrandColor::forCompany($this->companyId)->primary)->toBe(BrandColor::DEFAULT_PRIMARY);
});

test('only owners can set the brand colour', function () {
    $member = User::factory()->create(['role' => 'user']);
    $member->companies()->attach($this->companyId);
    BouncerFacade::scope()->to($this->companyId);
    BouncerFacade::sync($member)->roles(['super admin']);
    Sanctum::actingAs($member, ['*']);

    saveBrandColor('#0F766E')->assertForbidden();
});

test('invoice pdfs use the company brand colour', function () {
    CompanySetting::setSettings(['brand_color' => '#0F766E'], $this->companyId);

    $html = invoicePdfHtml($this->companyId);

    expect($html)->toContain('#0F766E')
        ->and(strtolower($html))->not->toContain(strtolower(BrandColor::DEFAULT_PRIMARY));
});

test('invoice pdfs keep the default colours without a brand colour', function () {
    expect(invoicePdfHtml($this->companyId))->toContain(BrandColor::DEFAULT_PRIMARY);
});

test('white text contrast is measured as wcag defines it', function () {
    expect(round(BrandColor::contrastWithWhite('#000000'), 1))->toBe(21.0)
        ->and(round(BrandColor::contrastWithWhite('#FFFFFF'), 1))->toBe(1.0)
        ->and(BrandColor::contrastWithWhite('#5851DB'))->toBeGreaterThan(4.5);
});

test('estimate and payment pdfs use the company brand colour', function () {
    CompanySetting::setSettings(['brand_color' => '#0F766E'], $this->companyId);
    request()->merge(['preview' => true]);

    $estimate = Estimate::factory()->create(['company_id' => $this->companyId]);
    $payment = Payment::factory()->create(['company_id' => $this->companyId]);

    expect($estimate->getPDFData()->render())->toContain('#0F766E')
        ->and($payment->getPDFData()->render())->toContain('#0F766E');
});

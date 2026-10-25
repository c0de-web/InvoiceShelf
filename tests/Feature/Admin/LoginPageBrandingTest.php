<?php

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    Storage::fake('public');

    $this->owner = User::where('role', 'super admin')->first();
    $this->company = $this->owner->companies()->first();
    $this->withHeaders(['company' => $this->company->id]);
    Sanctum::actingAs($this->owner, ['*']);
});

function loginTexts(array $overrides = []): array
{
    return [
        'login_page_heading' => 'Billing for C0de Web clients',
        'login_page_description' => 'Invoices, estimates & payments in one place.',
        'copyright_text' => '© C0de Web Ltd',
        ...$overrides,
    ];
}

function loginLogoEnvelope(): string
{
    $image = imagecreatetruecolor(300, 80);
    ob_start();
    imagepng($image);

    return json_encode(['name' => 'login-logo.png', 'data' => 'data:image/png;base64,'.base64_encode(ob_get_clean())]);
}

test('an owner sets the login page texts', function () {
    putJson('/api/v1/branding/login-page', loginTexts())->assertOk();

    expect(Setting::getSetting('login_page_heading'))->toBe('Billing for C0de Web clients')
        ->and(Setting::getSetting('copyright_text'))->toBe('© C0de Web Ltd');
    getJson('/api/v1/bootstrap')->assertJsonPath('global_settings.login_page_heading', 'Billing for C0de Web clients');
});

test('empty login page texts bring back the defaults', function () {
    putJson('/api/v1/branding/login-page', loginTexts())->assertOk();
    putJson('/api/v1/branding/login-page', loginTexts(['login_page_heading' => null]))->assertOk();

    expect(Setting::getSetting('login_page_heading'))->toBeEmpty();
});

test('login page texts have a length limit', function () {
    putJson('/api/v1/branding/login-page', loginTexts(['login_page_heading' => str_repeat('a', 121)]))
        ->assertUnprocessable();
});

test('only owners change the login page', function () {
    $member = User::factory()->create(['role' => 'user']);
    $member->companies()->attach($this->company->id);
    BouncerFacade::scope()->to($this->company->id);
    BouncerFacade::sync($member)->roles(['super admin']);
    Sanctum::actingAs($member, ['*']);

    putJson('/api/v1/branding/login-page', loginTexts())->assertForbidden();
    postJson('/api/v1/branding/login-logo', ['login_logo' => loginLogoEnvelope()])->assertForbidden();
});

test('an owner uploads and removes the login page logo', function () {
    postJson('/api/v1/branding/login-logo', ['login_logo' => loginLogoEnvelope()])->assertOk();
    $path = Setting::getSetting('login_page_logo');

    expect($path)->toStartWith('branding/login-logo')
        ->and(Storage::disk('public')->exists($path))->toBeTrue();

    postJson('/api/v1/branding/login-logo', ['is_login_logo_removed' => true])->assertOk();

    expect(Setting::getSetting('login_page_logo'))->toBeEmpty()
        ->and(Storage::disk('public')->exists($path))->toBeFalse();
});

test('the page hands the login texts to the app as safe javascript', function () {
    putJson('/api/v1/branding/login-page', loginTexts([
        'login_page_heading' => 'Tom & Jerry "quoted" </script><script>alert(1)</script>',
    ]))->assertOk();

    $html = view('app')->render();

    expect($html)->toContain('window.login_page_heading = '.json_encode(
        'Tom & Jerry "quoted" </script><script>alert(1)</script>',
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    ))->not->toContain('<script>alert(1)</script>');
});

test('the login page uses the main company brand colour and links the source code', function () {
    $first = Company::query()->orderBy('id')->first();
    CompanySetting::setSettings(['brand_color' => '#198A75'], $first->id);

    $html = view('app')->render();

    expect($html)->toContain('window.login_brand_color = "#198A75";')
        ->toContain('window.source_code_url = '.json_encode(config('invoiceshelf.source_code_url'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT));
});

test('the page script is valid javascript with every login setting set', function () {
    $first = Company::query()->orderBy('id')->first();
    CompanySetting::setSettings(['brand_color' => '#198A75'], $first->id);
    putJson('/api/v1/branding/login-page', loginTexts())->assertOk();
    postJson('/api/v1/branding/login-logo', ['login_logo' => loginLogoEnvelope()])->assertOk();

    preg_match('#<script type="module">(.*?)</script>\s*</body>#s', view('app')->render(), $match);
    $script = tempnam(sys_get_temp_dir(), 'page').'.mjs';
    file_put_contents($script, str_replace('window.InvoiceShelf.start()', '', $match[1]));

    exec('node --check '.escapeshellarg($script).' 2>&1', $output, $exitCode);

    expect($exitCode)->toBe(0, implode("\n", $output));
});

test('the page script is valid javascript with no login settings', function () {
    preg_match('#<script type="module">(.*?)</script>\s*</body>#s', view('app')->render(), $match);
    $script = tempnam(sys_get_temp_dir(), 'page').'.mjs';
    file_put_contents($script, str_replace('window.InvoiceShelf.start()', '', $match[1]));

    exec('node --check '.escapeshellarg($script).' 2>&1', $output, $exitCode);

    expect($exitCode)->toBe(0, implode("\n", $output));
});

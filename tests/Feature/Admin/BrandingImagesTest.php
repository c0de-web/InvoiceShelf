<?php

use App\Models\Company;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    Storage::fake('public');

    $this->owner = User::where('role', 'super admin')->first();
    $this->company = $this->owner->companies()->first();
    $this->withHeaders(['company' => $this->company->id]);
    Sanctum::actingAs($this->owner, ['*']);
});

/**
 * The JSON envelope the upload component posts: a file name and a base64 data URI.
 */
function uploadEnvelope(int $width, int $height, string $name = 'image.png'): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagepng($image);

    return json_encode(['name' => $name, 'data' => 'data:image/png;base64,'.base64_encode(ob_get_clean())]);
}

/**
 * The HTML page the app is served in (rendered directly: every route to it sits behind the install check).
 */
function appPage(): string
{
    return view('app')->render();
}

function brandingMember(Company $company): User
{
    $member = User::factory()->create(['role' => 'user']);
    $member->companies()->attach($company->id);
    BouncerFacade::scope()->to($company->id);
    BouncerFacade::sync($member)->roles(['super admin']);

    return $member;
}

test('an owner uploads a header logo for the company', function () {
    postJson('/api/v1/company/upload-header-logo', ['header_logo' => uploadEnvelope(400, 100, 'logo.png')])
        ->assertOk();

    expect($this->company->fresh()->header_logo)->toContain('logo.png');
    getJson('/api/v1/bootstrap')->assertJsonPath('current_company.header_logo', $this->company->fresh()->header_logo);
});

test('removing the header logo brings back the default', function () {
    postJson('/api/v1/company/upload-header-logo', ['header_logo' => uploadEnvelope(400, 100)])->assertOk();
    postJson('/api/v1/company/upload-header-logo', ['is_header_logo_removed' => true])->assertOk();

    expect($this->company->fresh()->header_logo)->toBeNull();
});

test('a header logo must be a png or jpg image', function () {
    $svg = json_encode(['name' => 'logo.svg', 'data' => 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg"/>')]);

    postJson('/api/v1/company/upload-header-logo', ['header_logo' => $svg])->assertUnprocessable();
});

test('only owners change the header logo', function () {
    Sanctum::actingAs(brandingMember($this->company), ['*']);

    postJson('/api/v1/company/upload-header-logo', ['header_logo' => uploadEnvelope(400, 100)])->assertForbidden();
});

test('an app icon is cropped square and saved in every favicon size', function () {
    postJson('/api/v1/app-icon', ['app_icon' => uploadEnvelope(600, 400)])->assertOk();

    foreach ([16, 32, 180, 192, 512] as $size) {
        $path = Storage::disk('public')->path("branding/app-icon-{$size}.png");

        [$width, $height] = getimagesize($path);

        expect([$width, $height])->toBe([$size, $size]);
    }

    expect(Setting::getSetting('app_icon_version'))->not->toBeEmpty();
});

test('pages link the uploaded app icon', function () {
    postJson('/api/v1/app-icon', ['app_icon' => uploadEnvelope(64, 64)])->assertOk();

    expect(appPage())->toContain('/storage/branding/app-icon-32.png')
        ->not->toContain('/favicons/favicon-32x32.png');
});

test('without an app icon pages keep the default favicons', function () {
    expect(appPage())->toContain('/favicons/favicon-32x32.png');
});

test('removing the app icon brings back the default favicons', function () {
    postJson('/api/v1/app-icon', ['app_icon' => uploadEnvelope(64, 64)])->assertOk();
    postJson('/api/v1/app-icon', ['is_app_icon_removed' => true])->assertOk();

    expect(Setting::getSetting('app_icon_version'))->toBeEmpty()
        ->and(Storage::disk('public')->exists('branding/app-icon-32.png'))->toBeFalse();
});

test('only owners change the app icon', function () {
    Sanctum::actingAs(brandingMember($this->company), ['*']);

    postJson('/api/v1/app-icon', ['app_icon' => uploadEnvelope(64, 64)])->assertForbidden();
});

test('the app tells the settings page which app icon is current', function () {
    postJson('/api/v1/app-icon', ['app_icon' => uploadEnvelope(64, 64)])->assertOk();

    getJson('/api/v1/bootstrap')->assertJsonPath('global_settings.app_icon_version', Setting::getSetting('app_icon_version'));
});

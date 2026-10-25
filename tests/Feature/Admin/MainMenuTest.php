<?php

use App\Models\Item;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;

use function Pest\Laravel\getJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->owner = User::where('role', 'super admin')->first();
    $this->company = $this->owner->companies()->first();
    $this->withHeaders(['company' => $this->company->id]);
    Sanctum::actingAs($this->owner, ['*']);

    // Menus are only registered at boot on an installed app, which the test database is not.
    (new AppServiceProvider(app()))->addMenus();
});

test('categories are in the main menu next to items and expenses', function () {
    $menu = collect(getJson('/api/v1/bootstrap')->assertOk()->json('main_menu'))->pluck('group', 'link');

    expect($menu['/admin/item-categories'])->toBe($menu['/admin/items'])
        ->and($menu['/admin/expense-categories'])->toBe($menu['/admin/expenses']);
});

test('categories are no longer in the settings menu', function () {
    $links = collect(getJson('/api/v1/bootstrap')->assertOk()->json('setting_menu'))->pluck('link');

    expect($links)->toContain('/admin/settings/tax-types')
        ->not->toContain('/admin/settings/item-categories')
        ->not->toContain('/admin/settings/expense-category');
});

test('sales, catalogue and costs groups have headings', function () {
    $response = getJson('/api/v1/bootstrap')->assertOk();
    $menu = collect($response->json('main_menu'))->pluck('group', 'link');
    $headings = $response->json('config.main_menu_groups');

    expect($headings[$menu['/admin/invoices']])->toBe('navigation.groups.sales')
        ->and($headings[$menu['/admin/items']])->toBe('navigation.groups.catalogue')
        ->and($headings[$menu['/admin/expenses']])->toBe('navigation.groups.costs')
        ->and($headings)->not->toHaveKey($menu['/admin/dashboard'])
        ->and($headings)->not->toHaveKey($menu['/admin/settings']);
});

test('a user who may see items but not expenses gets item categories only', function () {
    $user = User::factory()->create(['role' => 'user']);
    $this->company->users()->attach($user->id);
    BouncerFacade::scope()->to($this->company->id);
    BouncerFacade::allow($user)->to('view-item', Item::class);
    Sanctum::actingAs($user, ['*']);

    $links = collect(getJson('/api/v1/bootstrap')->assertOk()->json('main_menu'))->pluck('link');

    expect($links)->toContain('/admin/item-categories')
        ->not->toContain('/admin/expense-categories')
        ->not->toContain('/admin/expenses');
});

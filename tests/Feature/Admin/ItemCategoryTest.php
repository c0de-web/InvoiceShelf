<?php

use App\Models\Company;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\User;
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
});

function itemCategory(Company $company, string $name, ?ItemCategory $parent = null): ItemCategory
{
    return ItemCategory::create(['company_id' => $company->id, 'name' => $name, 'parent_id' => $parent?->id]);
}

function otherCompany(): Company
{
    $company = Company::factory()->create(['owner_id' => User::factory()->create(['role' => 'user'])->id]);
    $company->setupRoles();

    return $company;
}

test('an owner creates a category and a subcategory', function () {
    $parent = postJson('/api/v1/item-categories', ['name' => 'Hosting'])->assertCreated()->json('data');

    postJson('/api/v1/item-categories', ['name' => 'Web hosting', 'parent_id' => $parent['id']])
        ->assertCreated()
        ->assertJsonPath('data.parent_id', $parent['id']);

    expect(ItemCategory::where('company_id', $this->company->id)->count())->toBe(2);
});

test('categories go two levels deep at most', function () {
    $hosting = itemCategory($this->company, 'Hosting');
    $web = itemCategory($this->company, 'Web hosting', $hosting);

    postJson('/api/v1/item-categories', ['name' => 'Too deep', 'parent_id' => $web->id])
        ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
});

test('a category with subcategories cannot move under another category', function () {
    $hosting = itemCategory($this->company, 'Hosting');
    itemCategory($this->company, 'Web hosting', $hosting);
    $domains = itemCategory($this->company, 'Domains');

    putJson("/api/v1/item-categories/{$hosting->id}", ['name' => 'Hosting', 'parent_id' => $domains->id])
        ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
});

test('a category cannot be its own parent', function () {
    $hosting = itemCategory($this->company, 'Hosting');

    putJson("/api/v1/item-categories/{$hosting->id}", ['name' => 'Hosting', 'parent_id' => $hosting->id])
        ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
});

test('names are unique within the same parent only', function () {
    $hosting = itemCategory($this->company, 'Hosting');
    $workspace = itemCategory($this->company, 'Google Workspace');
    itemCategory($this->company, 'Email', $hosting);

    postJson('/api/v1/item-categories', ['name' => 'Email', 'parent_id' => $hosting->id])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
    postJson('/api/v1/item-categories', ['name' => 'Email', 'parent_id' => $workspace->id])->assertCreated();
    postJson('/api/v1/item-categories', ['name' => 'Hosting'])->assertUnprocessable();
});

test('another company\'s category can be neither a parent nor seen', function () {
    $foreign = itemCategory(otherCompany(), 'Foreign');

    postJson('/api/v1/item-categories', ['name' => 'Mine', 'parent_id' => $foreign->id])
        ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
    getJson("/api/v1/item-categories/{$foreign->id}")->assertForbidden();
    expect(collect(getJson('/api/v1/item-categories')->json('data'))->pluck('name'))->not->toContain('Foreign');
});

test('a category with items cannot be deleted', function () {
    $hosting = itemCategory($this->company, 'Hosting');
    Item::factory()->create(['company_id' => $this->company->id, 'item_category_id' => $hosting->id]);

    deleteJson("/api/v1/item-categories/{$hosting->id}")->assertUnprocessable();

    expect(ItemCategory::find($hosting->id))->not->toBeNull();
});

test('a category with subcategories cannot be deleted', function () {
    $hosting = itemCategory($this->company, 'Hosting');
    itemCategory($this->company, 'Web hosting', $hosting);

    deleteJson("/api/v1/item-categories/{$hosting->id}")->assertUnprocessable();
});

test('an empty category is deleted', function () {
    $hosting = itemCategory($this->company, 'Hosting');

    deleteJson("/api/v1/item-categories/{$hosting->id}")->assertOk();

    expect(ItemCategory::find($hosting->id))->toBeNull();
});

test('the list carries each category\'s parent and item count', function () {
    $hosting = itemCategory($this->company, 'Hosting');
    $web = itemCategory($this->company, 'Web hosting', $hosting);
    Item::factory()->count(2)->create(['company_id' => $this->company->id, 'item_category_id' => $web->id]);

    $web = collect(getJson('/api/v1/item-categories')->assertOk()->json('data'))->firstWhere('name', 'Web hosting');

    expect($web['parent_id'])->toBe($hosting->id)
        ->and($web['items_count'])->toBe(2);
});

test('an item is filed in a category', function () {
    $web = itemCategory($this->company, 'Web hosting', itemCategory($this->company, 'Hosting'));

    $item = postJson('/api/v1/items', [
        'name' => 'Hosting – Standard', 'price' => 25000, 'item_category_id' => $web->id,
    ])->assertOk()->json('data');

    expect($item['item_category_id'])->toBe($web->id)
        ->and($item['category']['name'])->toBe('Web hosting')
        ->and($item['category']['parent_name'])->toBe('Hosting');
});

test('an item cannot be filed in another company\'s category', function () {
    $foreign = itemCategory(otherCompany(), 'Foreign');

    postJson('/api/v1/items', ['name' => 'X', 'price' => 100, 'item_category_id' => $foreign->id])
        ->assertUnprocessable()->assertJsonValidationErrors('item_category_id');
});

test('filtering items by a category includes its subcategories', function () {
    $hosting = itemCategory($this->company, 'Hosting');
    $web = itemCategory($this->company, 'Web hosting', $hosting);
    $domains = itemCategory($this->company, 'Domains');
    Item::factory()->create(['company_id' => $this->company->id, 'name' => 'In parent', 'item_category_id' => $hosting->id]);
    Item::factory()->create(['company_id' => $this->company->id, 'name' => 'In child', 'item_category_id' => $web->id]);
    Item::factory()->create(['company_id' => $this->company->id, 'name' => 'Elsewhere', 'item_category_id' => $domains->id]);

    $names = collect(getJson("/api/v1/items?item_category_id={$hosting->id}&limit=all")->json('data'))->pluck('name');

    expect($names->sort()->values()->all())->toBe(['In child', 'In parent']);
});

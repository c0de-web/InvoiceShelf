<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->mainOwner = User::where('role', 'super admin')->first();
    $this->company = $this->mainOwner->companies()->first();
    $this->withHeaders(['company' => $this->company->id]);
});

/**
 * A user with the Super Admin role in the company, optionally a co-owner of it.
 */
function companyMember(Company $company, bool $coOwner = false): User
{
    $user = User::factory()->create(['role' => 'user']);
    $user->companies()->attach($company->id, ['is_owner' => $coOwner]);

    BouncerFacade::scope()->to($company->id);
    BouncerFacade::sync($user)->roles(['super admin']);

    return $user;
}

function companyInfoPayload(string $name): array
{
    return ['name' => $name, 'address' => ['country_id' => 2]];
}

function memberPayload(User $user, Company $company, array $membership = [], array $overrides = []): array
{
    return [
        'name' => $user->name,
        'email' => $user->email,
        'password' => null,
        'companies' => [['id' => $company->id, 'role' => 'super admin', ...$membership]],
        ...$overrides,
    ];
}

test('a co-owner can change the company information', function () {
    Sanctum::actingAs(companyMember($this->company, coOwner: true), ['*']);

    putJson('api/v1/company', companyInfoPayload('C0de Web Ltd'))->assertOk();

    expect($this->company->fresh()->name)->toBe('C0de Web Ltd');
});

test('a super admin who is not an owner cannot change the company information', function () {
    Sanctum::actingAs(companyMember($this->company), ['*']);

    putJson('api/v1/company', companyInfoPayload('Taken Over Ltd'))->assertForbidden();
});

test('co-ownership is per company', function () {
    $other = Company::factory()->create(['owner_id' => User::factory()->create(['role' => 'user'])->id]);
    $other->setupRoles();
    $coOwner = companyMember($this->company, coOwner: true);
    $coOwner->companies()->attach($other->id);

    Sanctum::actingAs($coOwner, ['*']);

    putJson('api/v1/company', companyInfoPayload('Other Renamed'), ['company' => $other->id])->assertForbidden();
});

test('the main owner can make a user a co-owner and take it back', function () {
    $member = companyMember($this->company);
    Sanctum::actingAs($this->mainOwner, ['*']);

    putJson("/api/v1/users/{$member->id}", memberPayload($member, $this->company, ['is_owner' => true]))->assertOk();
    expect($member->fresh()->ownsCompany($this->company->id))->toBeTrue();

    putJson("/api/v1/users/{$member->id}", memberPayload($member, $this->company, ['is_owner' => false]))->assertOk();
    expect($member->fresh()->ownsCompany($this->company->id))->toBeFalse();
});

test('a co-owner cannot make anyone an owner', function () {
    $member = companyMember($this->company);
    Sanctum::actingAs(companyMember($this->company, coOwner: true), ['*']);

    putJson("/api/v1/users/{$member->id}", memberPayload($member, $this->company, ['is_owner' => true]))
        ->assertUnprocessable();

    expect($member->fresh()->ownsCompany($this->company->id))->toBeFalse();
});

test('a co-owner can still manage ordinary users', function () {
    $member = companyMember($this->company);
    Sanctum::actingAs(companyMember($this->company, coOwner: true), ['*']);

    putJson("/api/v1/users/{$member->id}", memberPayload($member, $this->company, overrides: ['name' => 'Renamed']))
        ->assertOk();

    expect($member->fresh()->name)->toBe('Renamed');
});

test('a co-owner cannot change the main owner\'s account', function () {
    $password = $this->mainOwner->password;
    Sanctum::actingAs(companyMember($this->company, coOwner: true), ['*']);

    putJson("/api/v1/users/{$this->mainOwner->id}", memberPayload(
        $this->mainOwner, $this->company, overrides: ['password' => 'taken-over-password'],
    ))->assertForbidden();

    expect($this->mainOwner->fresh()->password)->toBe($password);
});

test('a co-owner cannot change another co-owner\'s account', function () {
    $otherCoOwner = companyMember($this->company, coOwner: true);
    Sanctum::actingAs(companyMember($this->company, coOwner: true), ['*']);

    putJson("/api/v1/users/{$otherCoOwner->id}", memberPayload($otherCoOwner, $this->company, overrides: ['name' => 'Renamed']))
        ->assertForbidden();
});

test('the main owner can change a co-owner\'s account', function () {
    $coOwner = companyMember($this->company, coOwner: true);
    Sanctum::actingAs($this->mainOwner, ['*']);

    putJson("/api/v1/users/{$coOwner->id}", memberPayload($coOwner, $this->company, ['is_owner' => true], ['name' => 'Renamed']))
        ->assertOk();

    expect($coOwner->fresh()->name)->toBe('Renamed')
        ->and($coOwner->fresh()->ownsCompany($this->company->id))->toBeTrue();
});

test('a co-owner cannot delete the main owner or another co-owner', function () {
    $otherCoOwner = companyMember($this->company, coOwner: true);
    $member = companyMember($this->company);
    Sanctum::actingAs(companyMember($this->company, coOwner: true), ['*']);

    postJson('/api/v1/users/delete', ['users' => [$this->mainOwner->id, $otherCoOwner->id, $member->id]])->assertOk();

    expect(User::find($this->mainOwner->id))->not->toBeNull()
        ->and(User::find($otherCoOwner->id))->not->toBeNull()
        ->and(User::find($member->id))->toBeNull();
});

test('a co-owner cannot delete the company or transfer it', function () {
    $member = companyMember($this->company);
    Sanctum::actingAs(companyMember($this->company, coOwner: true), ['*']);

    postJson('/api/v1/transfer/ownership/'.$member->id)->assertForbidden();
    postJson('/api/v1/companies/delete', [$this->company->name])->assertForbidden();

    expect($this->company->fresh()->owner_id)->toBe($this->mainOwner->id);
});

test('a revoked co-owner loses the owner areas', function () {
    $coOwner = companyMember($this->company, coOwner: true);
    $coOwner->companies()->updateExistingPivot($this->company->id, ['is_owner' => false]);

    Sanctum::actingAs($coOwner->fresh(), ['*']);

    putJson('api/v1/company', companyInfoPayload('Taken Over Ltd'))->assertForbidden();
});

test('a user\'s companies say whether they own them', function () {
    $coOwner = companyMember($this->company, coOwner: true);
    $member = companyMember($this->company);
    Sanctum::actingAs($this->mainOwner, ['*']);

    getJson("/api/v1/users/{$coOwner->id}")->assertJsonPath('data.companies.0.is_owner', true);
    getJson("/api/v1/users/{$member->id}")->assertJsonPath('data.companies.0.is_owner', false);
});

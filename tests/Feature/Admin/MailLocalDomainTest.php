<?php

use App\Models\Setting;
use App\Models\User;
use App\Providers\AppConfigProvider;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::where('role', 'super admin')->first();
    $this->withHeaders(['company' => $user->companies()->first()->id]);
    Sanctum::actingAs($user, ['*']);
});

function configureMailFromDatabase(): void
{
    // boot() skips everything until the installer has marked the database
    // as created, so run the mail step on its own.
    (new ReflectionMethod(AppConfigProvider::class, 'configureMailFromDatabase'))->invoke(new AppConfigProvider(app()));
}

test('smtp saved without a local domain still greets with the app domain', function () {
    // The mail settings form has no local domain field, so a save stores it empty.
    // Symfony would then send EHLO [127.0.0.1], which Google's SMTP relay rejects.
    postJson('/api/v1/mail/config', [
        'mail_driver' => 'smtp',
        'mail_host' => 'smtp-relay.gmail.com',
        'mail_port' => 587,
        'mail_encryption' => 'smtp',
        'from_name' => 'InvoiceShelf',
        'from_mail' => 'hello@example.com',
    ])->assertOk();

    configureMailFromDatabase();

    expect(config('mail.mailers.smtp.local_domain'))->toBe('invoiceshelf.test');
});

test('a stored local domain is used as is', function () {
    Setting::setSettings([
        'mail_driver' => 'smtp',
        'mail_local_domain' => 'mail.example.com',
    ]);

    configureMailFromDatabase();

    expect(config('mail.mailers.smtp.local_domain'))->toBe('mail.example.com');
});

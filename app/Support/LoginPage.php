<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Setting;
use Illuminate\Database\QueryException;

/**
 * What the login page shows: the global login settings and the main (first) company's brand colour,
 * since nobody is signed in yet to say which company it is.
 */
final class LoginPage
{
    public const TEXT_SETTINGS = ['login_page_heading', 'login_page_description', 'copyright_text'];

    /**
     * @return array<string, string|null>
     */
    public static function settings(): array
    {
        try {
            return collect([...self::TEXT_SETTINGS, 'login_page_logo'])
                ->mapWithKeys(fn (string $key) => [$key => Setting::getSetting($key) ?: null])
                ->all();
        } catch (QueryException) {
            // Before installation there is no settings table yet.
            return [];
        }
    }

    public static function brandColor(): ?string
    {
        try {
            $company = Company::query()->orderBy('id')->first();
        } catch (QueryException) {
            return null;
        }

        $color = $company ? BrandColor::forCompany($company->id)->primary : null;

        return $color !== BrandColor::DEFAULT_PRIMARY ? $color : null;
    }
}

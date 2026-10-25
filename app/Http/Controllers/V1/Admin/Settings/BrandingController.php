<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\BrandingImageRequest;
use App\Http\Requests\LoginPageRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Models\Setting;
use App\Support\AppIcon;
use App\Support\LoginPage;
use App\Support\SafeFileName;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class BrandingController extends Controller
{
    /**
     * Upload or remove the company's header logo.
     */
    public function uploadHeaderLogo(BrandingImageRequest $request): CompanyResource
    {
        $company = Company::find($request->header('company'));

        $this->authorize('manage company', $company);

        if ($request->boolean('is_header_logo_removed')) {
            $company->clearMediaCollection('header_logo');
        }

        if ($data = json_decode((string) $request->header_logo)) {
            $company->clearMediaCollection('header_logo');

            $company->addMediaFromBase64($data->data)
                ->usingFileName(SafeFileName::from($data->name))
                ->toMediaCollection('header_logo');
        }

        return new CompanyResource($company->fresh());
    }

    /**
     * Upload or remove the app icon (favicons), shared by every company.
     */
    public function uploadAppIcon(BrandingImageRequest $request): JsonResponse
    {
        $this->authorize('manage settings');

        if ($request->boolean('is_app_icon_removed')) {
            AppIcon::remove();
        }

        if ($data = json_decode((string) $request->app_icon)) {
            AppIcon::store(base64_decode(substr($data->data, strpos($data->data, ',') + 1)));
        }

        return response()->json(['app_icon_version' => AppIcon::version()]);
    }

    /**
     * Set the login page's heading, description and copyright line; empty brings back the default.
     */
    public function updateLoginPage(LoginPageRequest $request): JsonResponse
    {
        $this->authorize('manage settings');

        foreach (LoginPage::TEXT_SETTINGS as $key) {
            Setting::setSetting($key, (string) $request->input($key));
        }

        return response()->json(LoginPage::settings());
    }

    /**
     * Upload or remove the login page logo, stored under a new name each time so browsers don't show a cached one.
     */
    public function uploadLoginLogo(BrandingImageRequest $request): JsonResponse
    {
        $this->authorize('manage settings');

        $data = json_decode((string) $request->login_logo);

        if ($data || $request->boolean('is_login_logo_removed')) {
            if ($current = Setting::getSetting('login_page_logo')) {
                Storage::disk('public')->delete($current);
            }

            Setting::setSetting('login_page_logo', '');
        }

        if ($data) {
            $extension = pathinfo(SafeFileName::from($data->name), PATHINFO_EXTENSION);
            $path = 'branding/login-logo-'.now()->timestamp.'.'.$extension;

            Storage::disk('public')->put($path, base64_decode(substr($data->data, strpos($data->data, ',') + 1)));
            Setting::setSetting('login_page_logo', $path);
        }

        return response()->json(LoginPage::settings());
    }
}

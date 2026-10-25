<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\BrandingImageRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Support\AppIcon;
use App\Support\SafeFileName;
use Illuminate\Http\JsonResponse;

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
}

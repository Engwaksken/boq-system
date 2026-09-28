<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CompanyProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in user's own company profile (used to brand their BOQ PDFs).
 */
class CompanyProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => CompanyProfileService::toArray($request->user()->companyProfile),
        ]);
    }

    /**
     * Create or update. Send multipart/form-data with an optional `logo` file,
     * or `remove_logo=1` to delete the current logo.
     */
    public function update(Request $request, CompanyProfileService $service): JsonResponse
    {
        $profile = $service->save(
            $request->user(),
            $request->only(array_keys(CompanyProfileService::rules())),
            $request->file('logo'),
            $request->boolean('remove_logo'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Company profile saved.',
            'data' => CompanyProfileService::toArray($profile),
        ]);
    }
}

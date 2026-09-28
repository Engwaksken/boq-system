<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Validation and saving for a user's own company profile, shared by web and API.
 * A user can only ever change their own profile.
 */
class CompanyProfileService
{
    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'tin' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'size:2', Rule::exists('countries', 'iso2')],
            'city' => ['nullable', 'string', 'max:150'],
            'physical_address' => ['nullable', 'string', 'max:500'],
            'postal_address' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:40', 'regex:/^\+?[0-9 ()-]{6,40}$/'],
            'alt_telephone' => ['nullable', 'string', 'max:40', 'regex:/^\+?[0-9 ()-]{6,40}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** Raster images only: SVG can carry scripts. */
    public const LOGO_RULES = ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(User $user, array $data, ?UploadedFile $logo = null, bool $removeLogo = false): CompanyProfile
    {
        if (! empty($data['website']) && ! preg_match('#^https?://#i', (string) $data['website'])) {
            $data['website'] = 'https://'.$data['website'];
        }

        $data = Validator::make($data, self::rules(), [
            'telephone.regex' => __('Enter a valid phone number, including the country code.'),
            'alt_telephone.regex' => __('Enter a valid phone number, including the country code.'),
        ])->validate();

        Validator::make(['logo' => $logo], ['logo' => self::LOGO_RULES])->validate();

        $profile = $user->companyProfile ?? new CompanyProfile(['user_id' => $user->id]);
        $profile->fill(collect($data)->map(fn ($v) => $v === '' ? null : $v)->all());

        if ($removeLogo && $profile->logo_path) {
            Storage::disk('public')->delete($profile->logo_path);
            $profile->logo_path = null;
        }

        if ($logo) {
            if ($profile->logo_path) {
                Storage::disk('public')->delete($profile->logo_path);
            }

            // Logos keep transparency; they are resized for PDFs and the web.
            $profile->logo_path = app(FileCompressor::class)->storeImage(
                $logo,
                'company-logos',
                FileCompressor::LOGO_MAX_SIDE,
                keepTransparency: true,
                basename: $user->id.'-'.now()->timestamp,
            );
        }

        $profile->user_id = $user->id;
        $profile->save();
        $user->setRelation('companyProfile', $profile);

        return $profile;
    }

    /** @return array<string, mixed> */
    public static function toArray(?CompanyProfile $profile): ?array
    {
        return $profile ? $profile->only(array_merge(['id'], array_keys(self::rules()))) + ['logo_url' => $profile->logoUrl()] : null;
    }
}

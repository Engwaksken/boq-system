<?php

namespace App\Services;

use App\Mail\BoqSignatureRequested;
use App\Models\Boq;
use App\Models\BoqSignature;
use App\Models\BoqSignedDocument;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Signatures on BOQs: storing drawn or uploaded signature images, the
 * preparer's saved default signature, one-time client signing links and the
 * uploaded copies of the physically signed document.
 *
 * Signature images are normalised to trimmed PNGs with a transparent
 * background (paper white is removed from photos and scans) and kept on the
 * public disk under signatures/. Signed documents are private records on the
 * default disk and are only served through an authorised route.
 */
class BoqSignatureService
{
    public const LINK_DAYS = 14;

    public const IMAGE_MAX_KB = 2048;

    public const DOCUMENT_MAX_KB = 20480;

    /** Longest side a stored signature is scaled to (sharp in print, small on disk). */
    public const SIGNATURE_MAX_SIDE = 1200;

    /** PNG, JPEG or WebP only: SVG (which can carry scripts) is never accepted. */
    public const IMAGE_MIMES = ['image/png', 'image/jpeg', 'image/webp'];

    public const DOCUMENT_MIMES = ['application/pdf', 'image/png', 'image/jpeg', 'image/webp'];

    /** @var list<string> */
    public const IMAGE_RULES = ['file', 'mimes:png,jpg,jpeg,webp', 'mimetypes:image/png,image/jpeg,image/webp', 'max:'.self::IMAGE_MAX_KB];

    /** @var list<string> */
    public const DOCUMENT_RULES = ['file', 'mimes:pdf,png,jpg,jpeg,webp', 'mimetypes:application/pdf,image/png,image/jpeg,image/webp', 'max:'.self::DOCUMENT_MAX_KB];

    public function __construct(private FileCompressor $compressor) {}

    /**
     * Validation rules for the typed details that go with a signature.
     *
     * @return array<string, list<string>>
     */
    public static function detailRules(string $prefix = ''): array
    {
        return [
            $prefix.'name' => ['required', 'string', 'max:150'],
            $prefix.'title' => ['nullable', 'string', 'max:150'],
            $prefix.'date' => ['nullable', 'date', 'before_or_equal:'.now()->addDay()->toDateString()],
        ];
    }

    /* ------------------------------------------------------------------
     | Signature images
     * ------------------------------------------------------------------ */

    /**
     * Stores a signature image and returns [path, method]. The source is a PNG
     * data URL from the drawing pad (or bare base64), or an uploaded image.
     *
     * @return array{0: string, 1: string}
     */
    public function storeSignatureImage(string|UploadedFile $source, string $directory, string $attribute = 'signature'): array
    {
        if ($source instanceof UploadedFile) {
            return [$this->storeUploadedImage($source, $directory, $attribute), BoqSignature::METHOD_UPLOADED];
        }

        return [$this->storeImageBytes($this->decodeDataUrl($source, $attribute), $directory, $attribute), BoqSignature::METHOD_DRAWN];
    }

    /**
     * Image bytes from a "data:image/png;base64,..." URL or a bare base64 string.
     */
    public function decodeDataUrl(string $data, string $attribute = 'signature'): string
    {
        $data = trim($data);

        if (preg_match('#^data:([a-z0-9.+/-]+);base64,#i', $data, $match)) {
            if (! in_array(strtolower($match[1]), self::IMAGE_MIMES, true)) {
                throw ValidationException::withMessages([$attribute => __('The signature must be a PNG, JPG or WebP image.')]);
            }
            $data = substr($data, strlen($match[0]));
        }

        $bytes = base64_decode(preg_replace('/\s+/', '', $data) ?? '', true);

        if ($bytes === false || $bytes === '') {
            throw ValidationException::withMessages([$attribute => __('Draw or upload a signature first.')]);
        }

        if (strlen($bytes) > self::IMAGE_MAX_KB * 1024) {
            throw ValidationException::withMessages([$attribute => __('The signature image may not be larger than 2 MB.')]);
        }

        $this->assertImageMime($bytes, $attribute);

        return $bytes;
    }

    public function storeUploadedImage(UploadedFile $file, string $directory, string $attribute = 'signature'): string
    {
        $path = $file->getRealPath();

        if ($path === false || $file->getSize() > self::IMAGE_MAX_KB * 1024) {
            throw ValidationException::withMessages([$attribute => __('The signature image may not be larger than 2 MB.')]);
        }

        $bytes = (string) file_get_contents($path);
        $this->assertImageMime($bytes, $attribute);

        // Large photos are scaled down first so trimming stays quick.
        $compressed = $this->compressor->image($path, self::SIGNATURE_MAX_SIDE, keepTransparency: true);

        if ($compressed !== null) {
            $bytes = (string) file_get_contents($compressed['path']);
            @unlink($compressed['path']);
        }

        return $this->storeImageBytes($bytes, $directory, $attribute);
    }

    public function storeImageBytes(string $bytes, string $directory, string $attribute = 'signature'): string
    {
        $png = $this->normalise($bytes, $attribute);
        $path = trim($directory, '/').'/'.Str::random(24).'.png';

        Storage::disk('public')->put($path, $png);

        return $path;
    }

    private function assertImageMime(string $bytes, string $attribute): void
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';

        if (! in_array($mime, self::IMAGE_MIMES, true)) {
            throw ValidationException::withMessages([$attribute => __('The signature must be a PNG, JPG or WebP image.')]);
        }
    }

    /**
     * A trimmed PNG with a transparent background: near-white paper becomes
     * transparent, light grey fades out, and the empty margin is cropped.
     */
    public function normalise(string $bytes, string $attribute = 'signature'): string
    {
        $info = @getimagesizefromstring($bytes);
        if ($info === false || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 40_000_000) {
            throw ValidationException::withMessages([$attribute => __('The signature must be a PNG, JPG or WebP image.')]);
        }

        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            throw ValidationException::withMessages([$attribute => __('The signature must be a PNG, JPG or WebP image.')]);
        }

        [$width, $height] = [imagesx($source), imagesy($source)];
        $scale = min(1, self::SIGNATURE_MAX_SIDE / max($width, $height));
        $w = max(1, (int) round($width * $scale));
        $h = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($w, $h);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $w, $h, $width, $height);
        imagedestroy($source);

        $minX = $w;
        $minY = $h;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba = imagecolorat($canvas, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                if ($alpha >= 120) {
                    continue;
                }

                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                $luma = 0.299 * $r + 0.587 * $g + 0.114 * $b;

                if ($luma >= 225) {
                    $alpha = 127;
                } elseif ($luma > 170) {
                    $alpha = max($alpha, (int) round(($luma - 170) / 55 * 127));
                }

                if ($alpha !== (($rgba >> 24) & 0x7F)) {
                    imagesetpixel($canvas, $x, $y, imagecolorallocatealpha($canvas, $r, $g, $b, $alpha));
                }

                if ($alpha < 110) {
                    $minX = min($minX, $x);
                    $maxX = max($maxX, $x);
                    $minY = min($minY, $y);
                    $maxY = max($maxY, $y);
                }
            }
        }

        if ($maxX < 0) {
            imagedestroy($canvas);

            throw ValidationException::withMessages([$attribute => __('The signature is empty. Draw or upload a clear signature.')]);
        }

        $pad = 6;
        $minX = max(0, $minX - $pad);
        $minY = max(0, $minY - $pad);
        $cropW = min($w, $maxX + $pad + 1) - $minX;
        $cropH = min($h, $maxY + $pad + 1) - $minY;

        $cropped = imagecreatetruecolor($cropW, $cropH);
        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);
        imagefill($cropped, 0, 0, imagecolorallocatealpha($cropped, 0, 0, 0, 127));
        imagecopy($cropped, $canvas, 0, 0, $minX, $minY, $cropW, $cropH);
        imagedestroy($canvas);

        ob_start();
        imagepng($cropped, null, 9);
        imagedestroy($cropped);

        return (string) ob_get_clean();
    }

    /* ------------------------------------------------------------------
     | Signing
     * ------------------------------------------------------------------ */

    /**
     * Adds or replaces the preparer or client signature on a BOQ.
     *
     * @param  array{name: string, title?: string|null, date?: string|null}  $details
     */
    public function sign(
        Boq $boq,
        string $role,
        array $details,
        string|UploadedFile $source,
        ?User $user,
        ?Request $request = null,
        ?SignatureRequest $link = null,
    ): BoqSignature {
        [$path, $method] = $this->storeSignatureImage($source, $this->boqDirectory($boq), 'signature');

        return $this->record($boq, $role, $details, $path, $method, $user, $request, $link);
    }

    /**
     * Signs as preparer with the user's saved default signature.
     */
    public function signWithSaved(Boq $boq, User $user, ?Request $request = null, array $details = []): BoqSignature
    {
        if (! $user->signature_path || ! Storage::disk('public')->exists($user->signature_path)) {
            throw ValidationException::withMessages(['signature' => __('You have no saved signature yet. Add one in your profile.')]);
        }

        $path = $this->boqDirectory($boq).'/'.Str::random(24).'.png';
        Storage::disk('public')->copy($user->signature_path, $path);

        return $this->record($boq, BoqSignature::ROLE_PREPARER, [
            'name' => ($details['name'] ?? '') ?: ($user->signature_name ?: $user->name),
            'title' => ($details['title'] ?? '') ?: $user->signature_title,
            'date' => $details['date'] ?? null,
        ], $path, $user->signature_method ?: BoqSignature::METHOD_DRAWN, $user, $request);
    }

    public function remove(BoqSignature $signature): void
    {
        Storage::disk('public')->delete($signature->image_path);
        $signature->delete();
    }

    private function record(Boq $boq, string $role, array $details, string $path, string $method, ?User $user, ?Request $request, ?SignatureRequest $link = null): BoqSignature
    {
        if (! in_array($role, BoqSignature::ROLES, true)) {
            Storage::disk('public')->delete($path);

            throw ValidationException::withMessages(['role' => __('Choose who is signing.')]);
        }

        try {
            [$signature, $replacedPath] = DB::transaction(function () use ($boq, $role, $details, $path, $method, $user, $request, $link) {
                // A link can only be used once, even if two submissions race.
                if ($link !== null) {
                    $claimed = SignatureRequest::whereKey($link->id)
                        ->whereNull('used_at')
                        ->whereNull('revoked_at')
                        ->where('expires_at', '>', now())
                        ->update(['used_at' => now()]);

                    if ($claimed === 0) {
                        throw ValidationException::withMessages(['signature' => __('This signing link has already been used or has expired.')]);
                    }
                }

                $existing = $boq->signatures()->where('role', $role)->first();

                $signature = $boq->signatures()->updateOrCreate(['role' => $role], [
                    'name' => trim((string) $details['name']),
                    'title' => trim((string) ($details['title'] ?? '')) ?: null,
                    'signed_at' => ($details['date'] ?? null) ?: now()->toDateString(),
                    'image_path' => $path,
                    'method' => $method,
                    'ip' => $request?->ip(),
                    'user_agent' => $request ? Str::limit((string) $request->userAgent(), 500, '') : null,
                    'user_id' => $user?->id,
                    'signature_request_id' => $link?->id,
                ]);

                // Once the client has signed, older links are no longer needed.
                if ($role === BoqSignature::ROLE_CLIENT) {
                    $boq->signatureRequests()->open()->update(['revoked_at' => now()]);
                }

                return [$signature, $existing?->image_path];
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($path);

            throw $e;
        }

        if ($replacedPath && $replacedPath !== $path) {
            Storage::disk('public')->delete($replacedPath);
        }

        if ($role === BoqSignature::ROLE_CLIENT) {
            $this->notifyOwner($boq, $signature, $user);
        }

        return $signature;
    }

    private function notifyOwner(Boq $boq, BoqSignature $signature, ?User $actor): void
    {
        $ownerId = $boq->owner_id ?? $boq->project?->user_id;

        if (! $ownerId || $ownerId === $actor?->id) {
            return;
        }

        $locale = User::whereKey($ownerId)->value('locale') ?: config('app.locale');

        try {
            UserNotification::create([
                'user_id' => $ownerId,
                'type' => 'boq_signed',
                'title' => __('BOQ signed by the client', [], $locale),
                'message' => __(':name signed ":boq".', ['name' => $signature->name, 'boq' => $boq->name], $locale),
                'data' => [
                    'boq_id' => $boq->id,
                    'boq_signature_id' => $signature->id,
                    'url' => route('boqs.show', $boq),
                ],
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function boqDirectory(Boq $boq): string
    {
        return 'signatures/boqs/'.$boq->id;
    }

    /* ------------------------------------------------------------------
     | Saved default signature
     * ------------------------------------------------------------------ */

    public function saveDefault(User $user, string|UploadedFile $source, ?string $name, ?string $title): void
    {
        [$path, $method] = $this->storeSignatureImage($source, 'signatures/users/'.$user->id);
        $old = $user->signature_path;

        $user->forceFill([
            'signature_path' => $path,
            'signature_method' => $method,
            'signature_name' => trim((string) $name) ?: null,
            'signature_title' => trim((string) $title) ?: null,
        ])->save();

        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
        }
    }

    public function updateDefaultDetails(User $user, ?string $name, ?string $title): void
    {
        $user->forceFill([
            'signature_name' => trim((string) $name) ?: null,
            'signature_title' => trim((string) $title) ?: null,
        ])->save();
    }

    public function removeDefault(User $user): void
    {
        if ($user->signature_path) {
            Storage::disk('public')->delete($user->signature_path);
        }

        $user->forceFill(['signature_path' => null, 'signature_method' => null])->save();
    }

    /* ------------------------------------------------------------------
     | Client signing links
     * ------------------------------------------------------------------ */

    /**
     * The link the client can still use, if any.
     */
    public function activeRequest(Boq $boq): ?SignatureRequest
    {
        return $boq->signatureRequests()->open()->latest('id')->first();
    }

    /**
     * A new one-time signing link (older unused links stop working).
     */
    public function createRequest(Boq $boq, ?User $user, ?string $email = null): SignatureRequest
    {
        $token = Str::random(48);
        $expires = now()->addDays(self::LINK_DAYS);

        return DB::transaction(function () use ($boq, $user, $email, $token, $expires) {
            $boq->signatureRequests()->open()->update(['revoked_at' => now()]);

            return $boq->signatureRequests()->create([
                'user_id' => $user?->id,
                'token_hash' => SignatureRequest::hashToken($token),
                'url' => URL::temporarySignedRoute('boqs.client-sign', $expires, ['token' => $token]),
                'email' => $email,
                'expires_at' => $expires,
            ]);
        });
    }

    /**
     * The open link, or a new one when there is none.
     */
    public function currentOrNewRequest(Boq $boq, ?User $user): SignatureRequest
    {
        return $this->activeRequest($boq) ?? $this->createRequest($boq, $user);
    }

    public function findRequest(string $token): ?SignatureRequest
    {
        return SignatureRequest::with('boq.project')->where('token_hash', SignatureRequest::hashToken($token))->first();
    }

    public function revokeRequests(Boq $boq): void
    {
        $boq->signatureRequests()->open()->update(['revoked_at' => now()]);
    }

    public function emailRequest(Boq $boq, SignatureRequest $link, User $sender, string $to, ?string $message = null): void
    {
        $boq->loadMissing('project');

        Mail::to($to)->send(new BoqSignatureRequested(
            boq: $boq,
            sender: $sender,
            url: (string) $link->url,
            expiresAt: $link->expires_at,
            personalMessage: $message,
        ));

        $link->forceFill(['email' => $to])->save();
    }

    public function requestMessage(Boq $boq, string $url): string
    {
        $boq->loadMissing('project');
        $company = $boq->brandingIdentity()['company_name'] ?? null;

        return trim(implode("\n", array_filter([
            __('Please review and sign the Bill of Quantities: :name', ['name' => $boq->name]),
            $boq->project ? __('Project: :project', ['project' => $boq->project->name]) : null,
            __('Reference: :ref', ['ref' => $boq->reference]),
            $company ? __('From: :company', ['company' => $company]) : null,
            __('Sign here (valid :days days): :link', ['days' => self::LINK_DAYS, 'link' => $url]),
        ])));
    }

    public function whatsappUrl(Boq $boq, string $url): string
    {
        return 'https://wa.me/?text='.rawurlencode($this->requestMessage($boq, $url));
    }

    /* ------------------------------------------------------------------
     | Signed copies (records)
     * ------------------------------------------------------------------ */

    public function storeSignedDocument(Boq $boq, UploadedFile $file, ?User $user): BoqSignedDocument
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file->getRealPath()) ?: '';

        if (! in_array($mime, self::DOCUMENT_MIMES, true)) {
            throw ValidationException::withMessages(['document' => __('Upload a PDF or a photo (PNG, JPG or WebP).')]);
        }

        $disk = (string) config('filesystems.default', 'local');
        $directory = 'boq-signed-documents/'.$boq->id;

        if ($mime === 'application/pdf') {
            $path = $file->storeAs($directory, Str::random(32).'.pdf', $disk);
        } else {
            // Phone photos are scaled to scan size and re-encoded, like BOQ scans.
            $path = $this->compressor->storeImage($file, $directory, FileCompressor::SCAN_MAX_SIDE, false, $disk, Str::random(32));
        }

        $storedMime = $mime === 'application/pdf' ? $mime : ((new \finfo(FILEINFO_MIME_TYPE))->buffer((string) Storage::disk($disk)->get($path)) ?: $mime);

        return $boq->signedDocuments()->create([
            'user_id' => $user?->id,
            'disk' => $disk,
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName() ?: 'signed-boq', 200, ''),
            'size' => (int) Storage::disk($disk)->size($path),
            'mime' => $storedMime,
        ]);
    }

    public function deleteSignedDocument(BoqSignedDocument $document): void
    {
        Storage::disk($document->disk ?: config('filesystems.default'))->delete($document->path);
        $document->delete();
    }

    /**
     * Download name: the original name, with the extension of the stored file
     * (a photo may have been re-encoded as JPEG).
     */
    public function downloadName(BoqSignedDocument $document): string
    {
        $extension = pathinfo($document->path, PATHINFO_EXTENSION);
        $base = pathinfo($document->original_name, PATHINFO_FILENAME) ?: 'signed-boq';

        return Str::slug($base, '-').($extension ? '.'.$extension : '');
    }
}

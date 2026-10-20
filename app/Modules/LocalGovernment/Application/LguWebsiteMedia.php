<?php

namespace App\Modules\LocalGovernment\Application;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class LguWebsiteMedia
{
    public function response(int $municipalityId, ?string $path, string $kind): BinaryFileResponse
    {
        $folder = $kind === 'logo' ? 'lgu-logos' : 'lgu-covers';
        abort_unless(preg_match('~^'.$folder.'/'.$municipalityId.'/[a-zA-Z0-9]+\.(png|jpe?g|webp)$~D', (string) $path), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);
        $mime = $disk->mimeType($path);
        abort_unless(in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true), 404);

        // Never cache public bytes: unpublishing must also withdraw their URLs.
        return response()->file($disk->path($path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="lgu-'.$kind.'.'.pathinfo($path, PATHINFO_EXTENSION).'"',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

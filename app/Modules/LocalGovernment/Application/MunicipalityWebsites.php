<?php

namespace App\Modules\LocalGovernment\Application;

use App\Models\Municipality;
use App\Models\MunicipalityWebsite;
use App\Support\AuditTrail;
use App\Support\ConcurrentWrite;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class MunicipalityWebsites
{
    public function __construct(private ConcurrentWrite $writes) {}

    public function version(Municipality $municipality, ?MunicipalityWebsite $website): string
    {
        return $website ? ConcurrentWrite::version($website)
            : hash_hmac('sha256', 'new-lgu-website:'.$municipality->getKey(), (string) config('app.key'));
    }

    public function save(Municipality $municipality, array $data, ?UploadedFile $cover): void
    {
        $newPath = $cover?->store('lgu-covers/'.$municipality->getKey(), 'local');
        if ($cover && ! $newPath) {
            throw new RuntimeException('The website cover could not be stored.');
        }
        $oldPath = null;
        try {
            $this->writes->transaction(function () use ($municipality, $data, $newPath, &$oldPath) {
                $currentMunicipality = Municipality::query()->whereKey($municipality->getKey())->lockForUpdate()->firstOrFail();
                $website = MunicipalityWebsite::query()->where('municipality_id', $municipality->getKey())->first();
                $persist = function (MunicipalityWebsite $current) use ($currentMunicipality, $data, $newPath, &$oldPath) {
                    if ($current->exists && $current->slug !== $data['slug']) {
                        throw ValidationException::withMessages(['slug' => 'The saved website address cannot be changed here. This preserves shared links.']);
                    }
                    // Public profile fields and logo are intentionally shared with IDs.
                    if ($data['is_published'] && (! $currentMunicipality->is_active
                        || ! $currentMunicipality->supervisingProvince?->is_active
                        || ! $currentMunicipality->profile()->exists())) {
                        throw ValidationException::withMessages(['is_published' => 'Save an active LGU office profile before publishing its website.']);
                    }
                    $before = $current->only(['slug', 'is_published']);
                    $current->fill(array_intersect_key($data, array_flip($current->getFillable())));
                    $current->services = array_values($data['services'] ?? []);
                    $current->announcements = array_values($data['announcements'] ?? []);
                    $current->municipality_id = $currentMunicipality->getKey();
                    $current->slug = $data['slug'];
                    if ($newPath || ($data['remove_cover'] ?? false)) {
                        $oldPath = $current->cover_path;
                        $current->cover_path = $newPath ?: null;
                    }
                    $current->save();
                    AuditTrail::record('updated', 'LGU websites', 'Saved website settings for '.$currentMunicipality->name.'.', [
                        'auditable' => $current, 'old_values' => $before, 'new_values' => $current->only(['slug', 'is_published']),
                        'metadata' => ['cover_changed' => (bool) ($newPath || ($data['remove_cover'] ?? false)), 'service_count' => count($current->services), 'announcement_count' => count($current->announcements)],
                    ]);
                };
                if ($website) {
                    $this->writes->execute($website, $data['_record_version'], $persist);
                } else {
                    if (! hash_equals($this->version($municipality, null), $data['_record_version'])) {
                        throw ValidationException::withMessages(['_record_version' => 'Website settings changed. Reload and review the latest values.']);
                    }
                    $persist(new MunicipalityWebsite);
                }
            });
        } catch (Throwable $exception) {
            if ($newPath) {
                $this->removeFile($newPath);
            }
            // The database unique constraint settles simultaneous slug claims.
            if ($exception instanceof QueryException && MunicipalityWebsite::query()->where('slug', $data['slug'])->where('municipality_id', '!=', $municipality->getKey())->exists()) {
                throw ValidationException::withMessages(['slug' => 'This website address is already in use. Choose another address.']);
            }
            throw $exception;
        }
        if ($oldPath) {
            $this->removeFile($oldPath);
        }
    }

    private function removeFile(string $path): void
    {
        // Storage cleanup is best-effort after a commit or failed upload.
        try {
            Storage::disk('local')->delete($path);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}

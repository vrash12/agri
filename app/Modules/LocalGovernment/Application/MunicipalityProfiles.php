<?php

namespace App\Modules\LocalGovernment\Application;

use App\Models\Municipality;
use App\Models\MunicipalityProfile;
use App\Support\AuditTrail;
use App\Support\ConcurrentWrite;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class MunicipalityProfiles
{
    public function __construct(private ConcurrentWrite $writes) {}

    public function version(Municipality $municipality, ?MunicipalityProfile $profile): string
    {
        return $profile ? ConcurrentWrite::version($profile)
            : hash_hmac('sha256', 'new-lgu-profile:'.$municipality->getKey(), (string) config('app.key'));
    }

    public function save(Municipality $municipality, array $data, ?UploadedFile $logo): void
    {
        // File I/O happens before the short DB transaction. Failed writes discard only
        // their own new file; a successfully replaced logo is removed after commit.
        $newPath = $logo?->store('lgu-logos/'.$municipality->getKey(), 'local');
        if ($logo && ! $newPath) {
            throw new RuntimeException('The LGU logo could not be stored.');
        }
        $oldPath = null;
        try {
            $this->writes->transaction(function () use ($municipality, $data, $newPath, &$oldPath) {
                Municipality::query()->whereKey($municipality->getKey())->lockForUpdate()->firstOrFail();
                $profile = MunicipalityProfile::query()->where('municipality_id', $municipality->getKey())->first();
                $persist = function (MunicipalityProfile $current) use ($municipality, $data, $newPath, &$oldPath) {
                    $before = $current->only($current->getFillable());
                    $previousLogo = $current->logo_path;
                    $current->fill(array_intersect_key($data, array_flip($current->getFillable())));
                    $current->municipality_id = $municipality->getKey();
                    if ($newPath || ($data['remove_logo'] ?? false)) {
                        $oldPath = $current->logo_path;
                        $current->logo_path = $newPath ?: null;
                    }
                    $current->save();
                    AuditTrail::record('updated', 'LGU settings', 'Updated the LGU profile for '.$municipality->name.'.', [
                        'auditable' => $current, 'old_values' => $before, 'new_values' => $current->only($current->getFillable()),
                        'metadata' => ['logo_changed' => $previousLogo !== $current->logo_path],
                    ]);
                };
                if ($profile) {
                    $this->writes->execute($profile, $data['_record_version'], $persist);
                } else {
                    if (! hash_equals($this->version($municipality, null), $data['_record_version'])) {
                        throw ValidationException::withMessages(['_record_version' => 'The LGU settings changed. Reload this page and review the latest details.']);
                    }
                    $persist(new MunicipalityProfile);
                }
            });
        } catch (Throwable $exception) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            throw $exception;
        }
        if ($oldPath) {
            // Cleanup is best-effort: a storage outage must not undo saved settings.
            try {
                Storage::disk('local')->delete($oldPath);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}

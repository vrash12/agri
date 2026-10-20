<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMunicipalityProfileRequest;
use App\Models\Municipality;
use App\Models\MunicipalityProfile;
use App\Modules\Farmers\Application\FarmerCardSignatory;
use App\Modules\LocalGovernment\Application\MunicipalityProfiles;
use App\Support\MunicipalityAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MunicipalityProfileController extends Controller
{
    public function __construct(private MunicipalityAccess $access, private MunicipalityProfiles $profiles) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', MunicipalityProfile::class);
        $ready = Schema::hasTable('municipality_profiles');
        $websiteReady = Schema::hasTable('municipality_websites');
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $query = $this->access->scopeMunicipalities(Municipality::query()->active(), $request->user())
            ->whereHas('supervisingProvince', fn ($query) => $query->active())
            ->with('supervisingProvince:id,name');
        if ($ready) {
            $query->with('profile');
        }
        if ($websiteReady) {
            $query->with('website');
        }
        if ($term = trim($filters['q'] ?? '')) {
            $term = '%'.addcslashes($term, '\\%_').'%';
            $query->where(fn ($query) => $query->where('name', 'like', $term)
                ->orWhereHas('supervisingProvince', fn ($query) => $query->where('name', 'like', $term)));
        }

        return view('settings.lgu.index', ['municipalities' => $query->orderBy('name')->paginate(20)->withQueryString(), 'ready' => $ready, 'websiteReady' => $websiteReady]);
    }

    public function edit(Request $request, Municipality $municipality, FarmerCardSignatory $signatories): View
    {
        $profile = $this->authorizedProfile($request, $municipality);

        return view('settings.lgu.edit', [
            'municipality' => $municipality->load('supervisingProvince'), 'profile' => $profile,
            'version' => $this->profiles->version($municipality, $profile->exists ? $profile : null),
            'signatory' => $signatories->forMunicipality($municipality),
            'websiteReady' => PublicLguWebsiteController::ready(),
        ]);
    }

    public function update(UpdateMunicipalityProfileRequest $request, Municipality $municipality): RedirectResponse
    {
        $this->authorizedProfile($request, $municipality, 'update');
        $this->profiles->save($municipality, $request->validated(), $request->file('logo'));

        return redirect()->route('settings.lgu.edit', $municipality)->with('success', 'LGU settings saved. Farmer IDs now use these office details.');
    }

    public function logo(Request $request, Municipality $municipality): BinaryFileResponse
    {
        $profile = $this->authorizedProfile($request, $municipality);
        $path = (string) $profile->logo_path;
        abort_unless(preg_match('~^lgu-logos/'.$municipality->getKey().'/[a-zA-Z0-9]+\.(png|jpe?g|webp)$~', $path), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);
        $mime = $disk->mimeType($path);
        abort_unless(in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true), 404);

        return response()->file($disk->path($path), [
            'Content-Type' => $mime, 'Content-Disposition' => 'inline; filename="lgu-logo.'.pathinfo($path, PATHINFO_EXTENSION).'"',
            'Content-Security-Policy' => "default-src 'none'; sandbox", 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizedProfile(Request $request, Municipality $municipality, string $ability = 'view'): MunicipalityProfile
    {
        // Scope before loading protected profile fields or files, including new profiles.
        $scoped = $this->access->scopeMunicipalities(Municipality::query()->active(), $request->user())
            ->whereKey($municipality->getKey())->whereHas('supervisingProvince', fn ($query) => $query->active())->exists();
        abort_unless($scoped, 404);
        $subject = new MunicipalityProfile;
        $subject->municipality_id = $municipality->getKey();
        $this->authorize($ability, $subject);
        abort_unless(Schema::hasTable('municipality_profiles'), 503, 'LGU settings are awaiting the database update.');

        return MunicipalityProfile::query()->where('municipality_id', $municipality->getKey())->first() ?? $subject;
    }
}

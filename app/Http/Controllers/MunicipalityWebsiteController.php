<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMunicipalityWebsiteRequest;
use App\Models\Municipality;
use App\Models\MunicipalityWebsite;
use App\Modules\LocalGovernment\Application\LguWebsiteAddress;
use App\Modules\LocalGovernment\Application\LguWebsiteMedia;
use App\Modules\LocalGovernment\Application\MunicipalityWebsites;
use App\Support\MunicipalityAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MunicipalityWebsiteController extends Controller
{
    public function __construct(private MunicipalityAccess $access, private MunicipalityWebsites $websites) {}

    public function edit(Request $request, Municipality $municipality): View
    {
        $website = $this->authorizedWebsite($request, $municipality);
        $municipality->load(['supervisingProvince', 'profile']);
        if (! $website->exists) {
            $website->slug = Str::limit(Str::slug($municipality->name.'-'.$municipality->supervisingProvince->name), 53, '').'-'.$municipality->getKey();
            $website->headline = 'Welcome to '.$municipality->name;
        }

        return view('settings.lgu.website', [
            'municipality' => $municipality, 'website' => $website,
            'version' => $this->websites->version($municipality, $website->exists ? $website : null),
            'domain' => LguWebsiteAddress::domain(), 'publicUrl' => LguWebsiteAddress::publicUrl($website),
        ]);
    }

    public function update(UpdateMunicipalityWebsiteRequest $request, Municipality $municipality): RedirectResponse
    {
        $this->authorizedWebsite($request, $municipality, 'update');
        $this->websites->save($municipality, $request->validated(), $request->file('cover'));

        return redirect()->route('settings.lgu.website.edit', $municipality)
            ->with('success', $request->boolean('is_published') ? 'LGU website published. Saved changes are now public.' : 'LGU website saved as unpublished. Only authorized staff can preview it.');
    }

    public function preview(Request $request, Municipality $municipality, PublicLguWebsiteController $public): View
    {
        $website = $this->authorizedWebsite($request, $municipality);
        abort_unless($website->exists, 404);

        return $public->page($website, true);
    }

    public function media(Request $request, Municipality $municipality, string $kind, LguWebsiteMedia $media): BinaryFileResponse
    {
        $website = $this->authorizedWebsite($request, $municipality);
        abort_unless($website->exists, 404);

        return $media->response($municipality->getKey(), $kind === 'logo' ? $municipality->profile?->logo_path : $website->cover_path, $kind);
    }

    private function authorizedWebsite(Request $request, Municipality $municipality, string $ability = 'view'): MunicipalityWebsite
    {
        abort_unless($this->access->scopeMunicipalities(Municipality::query()->active(), $request->user())
            ->whereKey($municipality->getKey())->whereHas('supervisingProvince', fn ($query) => $query->active())->exists(), 404);
        $subject = new MunicipalityWebsite;
        $subject->municipality_id = $municipality->getKey();
        $this->authorize($ability, $subject);
        abort_unless(PublicLguWebsiteController::ready(), 503, 'LGU websites are awaiting the database update.');

        return MunicipalityWebsite::query()->where('municipality_id', $municipality->getKey())->first() ?? $subject;
    }
}

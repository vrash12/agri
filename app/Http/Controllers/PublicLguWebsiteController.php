<?php

namespace App\Http\Controllers;

use App\Models\MunicipalityWebsite;
use App\Modules\LocalGovernment\Application\LguWebsiteAddress;
use App\Modules\LocalGovernment\Application\LguWebsiteMedia;
use App\Modules\LocalGovernment\Application\PublishedLguWebsite;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicLguWebsiteController extends Controller
{
    public function __construct(private PublishedLguWebsite $websites) {}

    public static function ready(): bool
    {
        return PublishedLguWebsite::ready();
    }

    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $sites = new LengthAwarePaginator([], 0, 12, 1, ['path' => $request->url()]);
        if (self::ready()) {
            $query = MunicipalityWebsite::query()->publiclyVisible()
                ->select(['id', 'municipality_id', 'slug', 'tagline'])
                ->with(['municipality:id,name,province_id', 'municipality.supervisingProvince:id,name', 'municipality.profile:id,municipality_id,logo_path']);
            if ($term = trim($filters['q'] ?? '')) {
                $term = '%'.addcslashes($term, '\\%_').'%';
                $query->whereHas('municipality', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', $term)
                    ->orWhereHas('supervisingProvince', fn ($query) => $query->where('name', 'like', $term))));
            }
            $sites = $query->orderBy('slug')->paginate(12)->withQueryString();
        }

        return view('public-lgu.index', ['sites' => $sites, 'search' => $filters['q'] ?? '']);
    }

    public function show(Request $request, string $slug): View
    {
        return $this->page($this->websites->find($slug), false, (bool) $request->attributes->get('lgu_website_slug'));
    }

    public function subdomain(Request $request): View
    {
        return $this->show($request, (string) $request->attributes->get('lgu_website_slug'));
    }

    public function media(Request $request, string $slug, string $kind, LguWebsiteMedia $media): BinaryFileResponse
    {
        $site = $this->websites->find($slug);

        return $media->response($site->municipality_id, $kind === 'logo' ? $site->municipality->profile->logo_path : $site->cover_path, $kind);
    }

    public function subdomainMedia(Request $request, string $kind, LguWebsiteMedia $media): BinaryFileResponse
    {
        abort_unless($request->attributes->has('lgu_website_slug'), 404);

        return $this->media($request, $request->attributes->get('lgu_website_slug'), $kind, $media);
    }

    public function page(MunicipalityWebsite $site, bool $preview = false, bool $subdomain = false): View
    {
        $site->loadMissing(['municipality.supervisingProvince', 'municipality.profile']);
        $municipality = $site->municipality;
        $profile = $municipality->profile;
        $mediaUrl = fn ($kind) => $preview
            ? route('settings.lgu.website.media', [$municipality, $kind])
            : ($subdomain ? '/lgu-media/'.$kind : route('lgu.media', [$site->slug, $kind], false));

        return view('public-lgu.show', [
            'site' => $site, 'municipality' => $municipality, 'profile' => $profile, 'preview' => $preview,
            'logoUrl' => $profile?->logo_path ? $mediaUrl('logo') : null,
            'coverUrl' => $site->cover_path ? $mediaUrl('cover') : null,
            'publicUrl' => LguWebsiteAddress::publicUrl($site),
            'officeLoginUrl' => LguWebsiteAddress::loginUrl($site),
            'mapQuery' => $profile?->latitude !== null && $profile?->longitude !== null
                ? $profile->latitude.','.$profile->longitude : $profile?->office_address,
        ]);
    }
}

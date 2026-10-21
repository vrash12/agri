<?php

namespace App\Http\Controllers;

use App\Modules\LocalGovernment\Application\LguWebsiteAddress;
use App\Modules\LocalGovernment\Application\PublishedLguWebsite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class LguLoginController extends Controller
{
    public function show(string $slug, PublishedLguWebsite $websites): View
    {
        $website = $websites->find($slug);

        return view('auth.login', [
            'lguWebsite' => $website,
            'lguLogoUrl' => $website->municipality->profile->logo_path
                ? LguWebsiteAddress::centralUrl('lgu.media', [$website->slug, 'logo']) : null,
        ]);
    }

    public function login(Request $request, string $slug, PublishedLguWebsite $websites, AuthController $authentication): RedirectResponse
    {
        // Resolve from the fixed route, never a submitted municipality identifier.
        return $authentication->login($request, $websites->find($slug));
    }
}

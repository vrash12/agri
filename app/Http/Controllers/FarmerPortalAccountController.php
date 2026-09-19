<?php

namespace App\Http\Controllers;

use App\Http\Requests\DisableFarmerPortalAccountRequest;
use App\Http\Requests\IssueFarmerPortalActivationRequest;
use App\Models\Farmer;
use App\Models\FarmerPortalAccount;
use App\Support\ConcurrentWrite;
use App\Support\FarmerPortalAccounts;
use Illuminate\Http\Response;

class FarmerPortalAccountController extends Controller
{
    public function __construct(private FarmerPortalAccounts $accounts)
    {
    }

    public function show(Farmer $farmer): Response
    {
        $this->authorize('update', $farmer);
        $account = FarmerPortalAccount::query()->where('farmer_id', $farmer->id)->first();

        return $this->manage($farmer, $account);
    }

    public function issue(IssueFarmerPortalActivationRequest $request, Farmer $farmer): Response
    {
        $result = $this->accounts->issue($farmer, $request->user(), $request->validated('_record_version'));

        // The code is deliberately rendered once, never placed in the URL or session.
        return $this->manage($farmer->refresh(), $result['account'], $result['activationCode'], 'Activation code issued. Give it directly to the verified farmer.');
    }

    public function disable(DisableFarmerPortalAccountRequest $request, Farmer $farmer): \Illuminate\Http\RedirectResponse
    {
        $account = $this->accounts->disable($farmer, $request->user(), $request->validated('_record_version'));

        return redirect()->route('farmers.portal-account.show', $farmer)->with('success', 'Farmer portal access is disabled. Existing sessions have been revoked.');
    }

    private function manage(Farmer $farmer, ?FarmerPortalAccount $account, ?string $activationCode = null, ?string $status = null): Response
    {
        $farmer->load('municipality');
        $version = ConcurrentWrite::version($account ?? $farmer);

        return response()->view('farmer_portal.staff-account', compact('farmer', 'account', 'version', 'activationCode', 'status'))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Referrer-Policy', 'no-referrer');
    }
}

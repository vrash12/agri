<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivateFarmerPortalRequest;
use App\Http\Requests\FarmerPortalLoginRequest;
use App\Support\FarmerPortalAuthentication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FarmerPortalAuthController extends Controller
{
    public function showLogin()
    {
        return Auth::guard('farmer')->check() ? redirect()->route('farmer-portal.home') : view('farmer_portal.login');
    }

    public function showActivation()
    {
        return view('farmer_portal.activate');
    }

    public function login(FarmerPortalLoginRequest $request, FarmerPortalAuthentication $authentication)
    {
        $authentication->login($request);

        return redirect()->route('farmer-portal.home');
    }

    public function activate(ActivateFarmerPortalRequest $request, FarmerPortalAuthentication $authentication)
    {
        $authentication->activate($request);

        return redirect()->route('farmer-portal.login')->with('success', 'Your password is ready. Sign in to view your farmer records.');
    }

    public function logout(Request $request, FarmerPortalAuthentication $authentication)
    {
        $authentication->logout($request);

        return redirect()->route('farmer-portal.login')->with('success', 'You have signed out.');
    }
}

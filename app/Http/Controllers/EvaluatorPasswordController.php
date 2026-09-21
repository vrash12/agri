<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EvaluatorPasswordController extends Controller
{
    public function edit(Request $request)
    {
        abort_unless($request->user()->isGisEvaluator(), 403);

        return view('auth.evaluator-password');
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->isGisEvaluator(), 403);
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:15', 'max:72', 'confirmed', 'different:current_password'],
        ]);
        if (strlen($data['password']) > 72) {
            throw ValidationException::withMessages(['password' => 'Use no more than 72 bytes for your password.']);
        }
        DB::transaction(function () use ($request, $data) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($user->isGisEvaluator() && $user->hasUsableScope(), 403);
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
            }
            $user->forceFill([
                'password' => Hash::make($data['password']),
                'evaluation_password_pending' => false,
                'remember_token' => Str::random(60),
            ])->save();
            AuditTrail::record('password_changed', 'GIS evaluation', 'Evaluator changed their password.');
        });
        $request->session()->regenerate();

        return redirect()->route('municipality-boundaries.index')->with('success', 'Your password has been changed.');
    }
}

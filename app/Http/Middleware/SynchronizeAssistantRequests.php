<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class SynchronizeAssistantRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user(), 401);
        try {
            // Chat is read-only: never acquire a farmer/municipality write lock
            // or the account's operational-write lock while a provider responds.
            $lock = Cache::lock(
                'assistant:inflight:v1:'.hash('sha256', (string) $request->user()->getKey()),
                max(30, min(90, (int) config('assistant.timeout_seconds', 20) + 10))
            );
            $acquired = $lock->get();
        } catch (Throwable $exception) {
            Log::warning('Assistant request coordination unavailable.', ['exception_type' => $exception::class]);

            return response()->json(['message' => 'The Assistant is temporarily unavailable. Try again shortly.'], 503);
        }
        if (! $acquired) {
            return response()->json(['message' => 'Your earlier Assistant question is still being answered. Please wait a moment.'], 409);
        }
        try {
            // Idle activity is committed before the slow read. The session
            // middleware skips the late whole-session save and cookie refresh.
            // JSON chat validation never flashes input or mutates session state.
            if ($request->hasSession()) {
                try {
                    $request->session()->save();
                    $request->attributes->set('assistant_session_committed', true);
                } catch (Throwable $exception) {
                    // Suppress the session middleware's second save attempt too;
                    // a failed commit must not proceed to queries or inference.
                    $request->attributes->set('assistant_session_committed', true);
                    Log::warning('Assistant session activity could not be saved.', ['exception_type' => $exception::class]);

                    return response()->json(['message' => 'The Assistant is temporarily unavailable. Please sign in again if this continues.'], 503);
                }
            }

            return $next($request);
        } finally {
            try {
                $lock->release();
            } catch (Throwable $exception) {
                // An acquired lock expires even if the cache fails at release;
                // no operational state was changed by this endpoint.
                Log::warning('Assistant request coordination release failed.', ['exception_type' => $exception::class]);
            }
        }
    }
}

<?php

namespace App\Http\Middleware;

use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpFoundation\Response;

final class StartApplicationSession extends StartSession
{
    protected function saveSession($request)
    {
        if (! $this->assistantCommitted($request)) {
            parent::saveSession($request);
        }
    }

    protected function addCookieToResponse(Response $response, Session $session)
    {
        if (! $this->assistantCommitted(request())) {
            parent::addCookieToResponse($response, $session);
        }
    }

    private function assistantCommitted(Request $request): bool
    {
        // A slow read-only reply must not restore an old session or overwrite
        // the cookie/flash changes made by a concurrent logout or office edit.
        return $request->routeIs('assistant.message')
            && $request->attributes->get('assistant_session_committed') === true;
    }
}

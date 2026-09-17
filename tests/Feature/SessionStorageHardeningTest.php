<?php

namespace Tests\Feature;

use Illuminate\Session\EncryptedStore;
use Tests\TestCase;

/**
 * A session identifies a signed-in member of staff, so what is written to disk and
 * what is handed to the browser both matter.
 */
class SessionStorageHardeningTest extends TestCase
{
    public function test_session_payloads_are_encrypted_at_rest(): void
    {
        // With the file driver these sit in storage/framework/sessions.
        $this->assertTrue(config('session.encrypt'));
        $this->assertInstanceOf(EncryptedStore::class, app('session.store'));
    }

    public function test_the_session_cookie_is_not_readable_by_scripts_and_does_not_travel_cross_site(): void
    {
        $cookie = $this->sessionCookie();

        $this->assertTrue($cookie->isHttpOnly(), 'A script must not be able to read the session cookie.');
        $this->assertSame('lax', $cookie->getSameSite());
    }

    public function test_the_session_cookie_is_marked_secure_when_configured_for_https(): void
    {
        config(['session.secure' => true]);

        $this->assertTrue(
            $this->sessionCookie()->isSecure(),
            'SESSION_SECURE_COOKIE=true must keep the cookie off plain connections.'
        );
    }

    private function sessionCookie(): \Symfony\Component\HttpFoundation\Cookie
    {
        $response = $this->get(route('login'));
        $name = config('session.cookie');

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        $this->fail('The response did not set the session cookie ['.$name.'].');
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Every response carries the browser-side protections, and the policy has to keep
 * the interface's own map, CDN, and preview behaviour working.
 */
class SecurityHeadersTest extends TestCase
{
    public function test_protective_headers_are_sent_on_every_response(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertSame('same-origin-allow-popups', $response->headers->get('Cross-Origin-Opener-Policy'));
    }

    public function test_framing_is_limited_to_this_application_rather_than_blocked_outright(): void
    {
        $response = $this->get(route('login'));

        // DENY would break the Backup Folder preview, which frames this
        // application's own file stream to display a PDF.
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertStringContainsString("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_only_the_browser_features_the_interface_uses_are_permitted(): void
    {
        $policy = $this->get(route('login'))->headers->get('Permissions-Policy');

        // The public land map offers to centre on the visitor, and two screens copy text.
        $this->assertStringContainsString('geolocation=(self)', $policy);
        $this->assertStringContainsString('fullscreen=(self)', $policy);
        $this->assertStringContainsString('clipboard-write=(self)', $policy);
        $this->assertStringContainsString('camera=()', $policy);
        $this->assertStringContainsString('microphone=()', $policy);
    }

    public function test_the_policy_still_allows_the_maps_and_libraries_the_interface_loads(): void
    {
        $policy = $this->get(route('login'))->headers->get('Content-Security-Policy');

        foreach ([
            'https://maps.googleapis.com',
            'https://cdn.jsdelivr.net',
            'https://cdnjs.cloudflare.com',
            'https://cdn.datatables.net',
            'https://code.jquery.com',
        ] as $host) {
            $this->assertStringContainsString($host, $policy, $host.' must stay loadable.');
        }

        $this->assertStringContainsString('https://fonts.googleapis.com', $policy);
        $this->assertStringContainsString('https://fonts.gstatic.com', $policy);
        // Blob URLs back the client-side exports and the map libraries.
        $this->assertStringContainsString('blob:', $policy);
    }

    public function test_the_policy_closes_the_openings_that_do_not_need_to_be_open(): void
    {
        $policy = $this->get(route('login'))->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("base-uri 'self'", $policy);
        $this->assertStringContainsString("form-action 'self'", $policy);
        $this->assertStringContainsString("default-src 'self'", $policy);
    }

    public function test_strict_transport_security_is_only_sent_over_https(): void
    {
        $this->assertNull(
            $this->get(route('login'))->headers->get('Strict-Transport-Security'),
            'A plain HTTP environment must not be pinned to a certificate it does not have.'
        );

        $secure = $this->get('https://localhost/login');
        $this->assertStringContainsString('max-age=31536000', $secure->headers->get('Strict-Transport-Security'));
        $this->assertStringContainsString('includeSubDomains', $secure->headers->get('Strict-Transport-Security'));
    }

    public function test_the_policy_can_be_reported_only_or_switched_off_without_a_code_change(): void
    {
        config(['security.csp_report_only' => true]);
        $reporting = $this->get(route('login'));
        $this->assertNull($reporting->headers->get('Content-Security-Policy'));
        $this->assertNotNull($reporting->headers->get('Content-Security-Policy-Report-Only'));

        config(['security.csp_report_only' => false, 'security.csp_enabled' => false]);
        $off = $this->get(route('login'));
        $this->assertNull($off->headers->get('Content-Security-Policy'));
        $this->assertNull($off->headers->get('Content-Security-Policy-Report-Only'));
        // The headers that cannot break a screen are still sent.
        $this->assertSame('nosniff', $off->headers->get('X-Content-Type-Options'));
    }
}

<?php

namespace Tests\Feature;

use App\Platform\Observability\UsageMetrics;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UsageMetricsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'observability.enabled' => true,
            'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Cache::flush();
        Http::preventStrayRequests();
        $this->travelTo(now()->utc()->setDate(2026, 10, 18)->setTime(12, 0));
    }

    public function test_provider_events_count_real_attempts_and_failures_without_urls_or_credentials(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::sequence()->push(['ok' => true])->push([], 503),
            'sh.dataspace.copernicus.eu/*' => Http::failedConnection(),
            'untracked.example/*' => Http::response([])]);
        Http::get('https://api.open-meteo.com/v1/forecast?apikey=private-key&latitude=15.7');
        Http::get('https://api.open-meteo.com/v1/forecast');
        try {
            Http::get('https://sh.dataspace.copernicus.eu/statistics/v1');
        } catch (ConnectionException) {
        }
        Http::get('https://untracked.example/private-farmer');
        $metrics = app(UsageMetrics::class);
        $metrics->flush();
        $day = $metrics->snapshot()['periods']['day'];
        $this->assertEquals(2, $day['provider.open-meteo.attempts']);
        $this->assertEquals(2, $day['provider.open-meteo.completed']);
        $this->assertEquals(1, $day['provider.open-meteo.failed']);
        $this->assertEquals(1, $day['provider.sentinel-statistics.attempts']);
        $this->assertEquals(1, $day['provider.sentinel-statistics.failed']);
        foreach (['private-key', 'latitude', 'private-farmer', 'untracked.example', 'https://'] as $secret) {
            $this->assertStringNotContainsString($secret, json_encode($day));
        }
    }

    public function test_sql_is_reduced_to_counts_and_time_and_http_uses_fixed_workflow_groups(): void
    {
        $metrics = app(UsageMetrics::class);
        $metrics->beginRequest();
        Event::dispatch(new QueryExecuted('select * from private_farmers where password = ?', ['private-value'], 245, DB::connection()));
        $metrics->response('farmers.show', 1250, 200);
        $metrics->beginRequest();
        $metrics->query(12);
        $metrics->response('unrecognized-private-route', 50, 503);
        $metrics->flush();
        $day = $metrics->snapshot()['periods']['day'];
        $this->assertEquals(1, $day['db.farmers.count']);
        $this->assertEquals(1, $day['db.farmers.slow']);
        $this->assertEquals(245, $day['db.farmers.max_ms']);
        $this->assertEquals(1, $day['http.farmers.slow']);
        $this->assertEquals(1, $day['http.other.errors']);
        foreach (['private_farmers', 'private-value', 'password', 'unrecognized-private-route'] as $private) {
            $this->assertStringNotContainsString($private, json_encode($day));
        }
    }

    public function test_http_integration_measures_rendered_responses_and_queries(): void
    {
        Route::get('/_metrics-fixture', function () {
            DB::select('select 1');

            return response()->json(['ok' => true]);
        })->name('farmers.metrics-fixture');
        $this->getJson('/_metrics-fixture')->assertOk();
        $metrics = app(UsageMetrics::class);
        $metrics->flush();
        $day = $metrics->snapshot()['periods']['day'];
        $this->assertEquals(1, $day['http.farmers.count']);
        $this->assertGreaterThanOrEqual(1, $day['db.farmers.count']);
        $this->assertGreaterThan(0, $day['http.farmers.ms']);
    }

    public function test_flush_is_idempotent_counters_roll_over_and_unknown_dimensions_are_ignored(): void
    {
        $metrics = app(UsageMetrics::class);
        $metrics->cache('assistant-help', true);
        $metrics->cache('assistant-help', false);
        $metrics->tokens(128, 29);
        $metrics->map('crop-editor-2d', 'created');
        $metrics->cache('private-name', true);
        $metrics->map('unknown', 'created');
        $metrics->providerAttempt('unknown');
        $metrics->flush();
        $metrics->flush();
        $day = $metrics->snapshot()['periods']['day'];
        $this->assertEquals(128, $day['ai.input_tokens']);
        $this->assertEquals(29, $day['ai.output_tokens']);
        $this->assertEquals(1, $day['cache.assistant-help.hits']);
        $this->assertEquals(1, $day['cache.assistant-help.misses']);
        $this->assertStringNotContainsString('private-name', json_encode($day));
        $this->travel(1)->days();
        $this->assertSame([], $metrics->snapshot()['periods']['day']);
        $this->assertEquals(128, $metrics->snapshot()['periods']['month']['ai.input_tokens']);
        $this->travelTo(now()->utc()->setDate(2026, 11, 1));
        $this->assertSame([], $metrics->snapshot()['periods']['month']);
    }

    public function test_disabled_and_failed_telemetry_never_requires_an_external_service(): void
    {
        $metrics = app(UsageMetrics::class);
        config(['observability.enabled' => false]);
        $metrics->providerAttempt('openai');
        $metrics->flush();
        $this->assertSame(['enabled' => false, 'periods' => null], $metrics->snapshot());
        config(['observability.enabled' => true]);
        Cache::shouldReceive('lock')->andThrow(new \RuntimeException('private-cache-password'));
        Cache::shouldReceive('get')->andThrow(new \RuntimeException('private-cache-password'));
        $metrics->providerAttempt('openai');
        $metrics->flush();
        $this->assertNull($metrics->snapshot()['periods']);
        Http::assertNothingSent();
    }
}

<?php

namespace Tests\Unit;

use App\Support\LocalTime;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LocalTimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.timezone', 'UTC');
        config()->set('app.display_timezone', 'Asia/Manila');
    }

    public function test_it_displays_utc_timestamps_in_philippine_time(): void
    {
        $storedAt = Carbon::create(2026, 8, 24, 1, 15, 10, 'UTC');

        $this->assertSame(
            '2026-08-24 09:15:10 +08:00',
            LocalTime::fromUtc($storedAt)?->format('Y-m-d H:i:s P')
        );
    }

    public function test_it_converts_a_philippine_calendar_day_to_utc_query_boundaries(): void
    {
        $this->assertSame(
            '2026-08-23 16:00:00',
            LocalTime::utcStartOfLocalDay('2026-08-24')->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            '2026-08-24 15:59:59',
            LocalTime::utcEndOfLocalDay('2026-08-24')->format('Y-m-d H:i:s')
        );
    }
}

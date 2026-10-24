<?php

namespace Tests\Unit;

use App\Support\ReportDateSql;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class ReportDateSqlTest extends TestCase
{
    public function test_sqlite_completed_years_match_birthdays_nulls_leap_dates_and_future_dates(): void
    {
        $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
        $dates = [null, '2000-02-29', $today->format('Y-m-d')];
        foreach (['-18 years', '-18 years +1 day', '-18 years -1 day', '+1 day', '+2 years -1 day', '+2 years +1 day'] as $offset) {
            $dates[] = $today->modify($offset)->format('Y-m-d');
        }
        foreach ($dates as $date) {
            $expected = $date === null ? null : (int) (new DateTimeImmutable($date, new DateTimeZone('UTC')))->diff($today)->format('%r%y');
            $actual = DB::selectOne('SELECT '.ReportDateSql::completedYears('birthday').' AS years FROM (SELECT ? AS birthday)', [$date])->years;
            $this->assertSame($expected, $actual, (string) $date);
        }
    }

    public function test_mysql_keeps_its_existing_native_age_expression(): void
    {
        DB::setDefaultConnection('mysql');
        $this->assertSame('TIMESTAMPDIFF(YEAR, `date_of_birth`, CURDATE())', ReportDateSql::completedYears('date_of_birth'));
    }

    public function test_only_fixed_column_identifiers_are_accepted(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ReportDateSql::completedYears('birthday); SELECT 1');
    }
}

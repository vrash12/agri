<?php

namespace Tests\Unit;

use App\Support\CsvExport;
use PHPUnit\Framework\TestCase;

/**
 * Exported values are opened in spreadsheets by staff in other offices, so a cell
 * that looks like a formula must arrive as text.
 */
class CsvExportTest extends TestCase
{
    /** @dataProvider formulaStarters */
    public function test_a_value_a_spreadsheet_would_execute_is_neutralised(string $value): void
    {
        $this->assertSame("'".$value, CsvExport::value($value));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function formulaStarters(): array
    {
        return [
            'equals' => ['=1+1'],
            'plus' => ['+1'],
            'minus' => ['-1'],
            'at sign' => ['@SUM(A1)'],
            'command execution attempt' => ['=cmd|\' /C calc\'!A0'],
            'hyperlink attempt' => ['=HYPERLINK("http://example.test","click")'],
        ];
    }

    /** @dataProvider ordinaryValues */
    public function test_ordinary_values_are_left_exactly_as_they_are(mixed $value, string $expected): void
    {
        $this->assertSame($expected, CsvExport::value($value));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function ordinaryValues(): array
    {
        return [
            'a name' => ['Dela Cruz, Juan', 'Dela Cruz, Juan'],
            'an accented municipality' => ['Doña Remedios Trinidad', 'Doña Remedios Trinidad'],
            'a number' => [1234, '1234'],
            'a decimal' => [12.5, '12.5'],
            'a date' => ['2026-09-17', '2026-09-17'],
            // A minus inside the value is fine; only a leading one is a formula.
            'an internal hyphen' => ['PREVIEW-001', 'PREVIEW-001'],
            'null' => [null, ''],
            'empty' => ['', ''],
        ];
    }

    public function test_a_whole_row_is_rendered_at_once(): void
    {
        $this->assertSame(
            ['Juan', "'=2+2", '15.5', ''],
            CsvExport::row(['Juan', '=2+2', 15.5, null])
        );
    }
}

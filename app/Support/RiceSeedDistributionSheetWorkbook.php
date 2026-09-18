<?php

namespace App\Support;

use App\Models\RiceDistributionBatch;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Draws the Rice Seed Distribution Sheet as a wide, printable `.xlsx` worksheet:
 * merged season headings above their own columns, a repeated header block on every
 * printed page, and a blank signature column for the recipient to sign at release.
 *
 * Every cell - title, heading, label and value - is written through
 * `CsvExport::value()` and as an explicit string. A spreadsheet treats a cell that
 * begins with `=`, `+`, `-` or `@` as a formula, and these sheets carry free text
 * typed by staff, so a released file must not be able to execute anything when a
 * colleague opens it. The cost is that figures are stored as text, which suits a
 * sheet that is printed and signed rather than re-totalled in Excel.
 */
class RiceSeedDistributionSheetWorkbook
{
    /**
     * PhpSpreadsheet holds the whole workbook in memory, so a sheet this wide has
     * to be capped. Beyond this the operator is sent to the streaming CSV export
     * instead of being given a failed request or an exhausted worker.
     */
    public const MAX_ROWS = 5000;

    public function __construct(private RiceSeedDistributionSheet $sheet)
    {
    }

    /**
     * Render the batch to a temporary `.xlsx` file.
     *
     * @return array{path: string, filename: string, rows: int}
     */
    public function write(RiceDistributionBatch $batch): array
    {
        $this->guardSize($batch);

        $columns = $this->sheet->columns($batch);
        $lastColumn = Coordinate::stringFromColumnIndex(count($columns));

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Distribution Sheet');

        $row = $this->writeTitles($worksheet, $batch, $lastColumn);
        $row++; // spacer between the titles and the grouped headings

        $groupRow = $row;
        $headerRow = $row + 1;

        $this->writeGroupHeadings($worksheet, $batch, $groupRow);
        $this->writeColumnHeaders($worksheet, $columns, $headerRow);

        $firstDataRow = $headerRow + 1;
        $dataRow = $firstDataRow;

        $printedRows = $this->sheet->stream(
            $batch,
            function (array $values) use ($worksheet, $columns, &$dataRow): void {
                $this->writeRow($worksheet, $columns, $values, $dataRow);
                $dataRow++;
            }
        );

        if ($printedRows === 0) {
            $worksheet->mergeCells('A'.$dataRow.':'.$lastColumn.$dataRow);
            $this->put($worksheet, 'A'.$dataRow, 'No assistance releases are grouped into this sheet yet.');
            $worksheet->getStyle('A'.$dataRow)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $lastDataRow = $dataRow;
        } else {
            $this->writeRow(
                $worksheet,
                $columns,
                $this->sheet->totalsRow($this->sheet->totals($batch), $columns),
                $dataRow
            );
            $worksheet->getStyle('A'.$dataRow.':'.$lastColumn.$dataRow)
                ->getFont()
                ->setBold(true);
            $lastDataRow = $dataRow;
        }

        $this->applyLayout(
            $worksheet,
            $columns,
            $lastColumn,
            $groupRow,
            $headerRow,
            $lastDataRow
        );

        $path = tempnam(sys_get_temp_dir(), 'rice_sheet_');

        if ($path === false) {
            throw ValidationException::withMessages([
                'batch' => 'The sheet could not be prepared on the server. Please try again.',
            ]);
        }

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return [
            'path' => $path,
            'filename' => $this->filename($batch),
            'rows' => $printedRows,
        ];
    }

    public function filename(RiceDistributionBatch $batch): string
    {
        $parts = array_filter([
            'rice-seed-distribution-sheet',
            $batch->municipality?->name,
            $batch->reference,
            $batch->plantingSeasonHeading(),
        ]);

        return str(implode(' ', $parts))->slug().'_'.now()->format('Ymd_His').'.xlsx';
    }

    private function guardSize(RiceDistributionBatch $batch): void
    {
        if ($this->sheet->releases($batch)->count() <= self::MAX_ROWS) {
            return;
        }

        throw ValidationException::withMessages([
            'batch' => 'This sheet has more than '.number_format(self::MAX_ROWS)
                .' releases, which is too large for one printable worksheet. Split it into smaller sheets, or use the assistance CSV export for the full list.',
        ]);
    }

    private function writeTitles(
        Worksheet $worksheet,
        RiceDistributionBatch $batch,
        string $lastColumn
    ): int {
        $row = 1;

        foreach ($this->sheet->titles($batch) as $index => $title) {
            $worksheet->mergeCells('A'.$row.':'.$lastColumn.$row);
            $this->put($worksheet, 'A'.$row, $title);
            $worksheet->getStyle('A'.$row)
                ->getFont()
                ->setBold($index === 0)
                ->setSize($index === 0 ? 14 : 11);
            $worksheet->getStyle('A'.$row)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        return $row;
    }

    private function writeGroupHeadings(
        Worksheet $worksheet,
        RiceDistributionBatch $batch,
        int $row
    ): void {
        $columnIndex = 1;

        foreach ($this->sheet->groups($batch) as $group) {
            $span = count($group['columns']);
            $first = Coordinate::stringFromColumnIndex($columnIndex);
            $last = Coordinate::stringFromColumnIndex($columnIndex + $span - 1);

            if ($span > 1) {
                $worksheet->mergeCells($first.$row.':'.$last.$row);
            }

            $this->put($worksheet, $first.$row, $group['heading']);
            $worksheet->getStyle($first.$row.':'.$last.$row)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);

            $columnIndex += $span;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     */
    private function writeColumnHeaders(Worksheet $worksheet, array $columns, int $row): void
    {
        foreach ($columns as $index => $column) {
            $this->put(
                $worksheet,
                Coordinate::stringFromColumnIndex($index + 1).$row,
                (string) $column['label']
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<string, string>  $values
     */
    private function writeRow(
        Worksheet $worksheet,
        array $columns,
        array $values,
        int $row
    ): void {
        foreach ($columns as $index => $column) {
            $this->put(
                $worksheet,
                Coordinate::stringFromColumnIndex($index + 1).$row,
                $values[(string) $column['key']] ?? ''
            );
        }
    }

    /**
     * The single guarded write used by every cell in the workbook.
     */
    private function put(Worksheet $worksheet, string $coordinate, mixed $value): void
    {
        $worksheet->setCellValueExplicit(
            $coordinate,
            CsvExport::value($value),
            DataType::TYPE_STRING
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     */
    private function applyLayout(
        Worksheet $worksheet,
        array $columns,
        string $lastColumn,
        int $groupRow,
        int $headerRow,
        int $lastDataRow
    ): void {
        foreach ($columns as $index => $column) {
            $worksheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))
                ->setWidth((float) ($column['width'] ?? 16));
        }

        $worksheet->getStyle('A'.$groupRow.':'.$lastColumn.$headerRow)
            ->getFont()
            ->setBold(true);
        $worksheet->getStyle('A'.$headerRow.':'.$lastColumn.$headerRow)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $worksheet->getRowDimension($headerRow)->setRowHeight(34);

        $worksheet->getStyle('A'.$groupRow.':'.$lastColumn.$lastDataRow)
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        $worksheet->freezePane('A'.($headerRow + 1));

        $pageSetup = $worksheet->getPageSetup();
        $pageSetup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_LEGAL);
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(0);
        // Long recipient lists run over several printed pages, and a page without
        // its season headings cannot be read or signed correctly.
        $pageSetup->setRowsToRepeatAtTopByStartAndEnd(1, $headerRow);
        $worksheet->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.3)->setRight(0.3);
    }
}

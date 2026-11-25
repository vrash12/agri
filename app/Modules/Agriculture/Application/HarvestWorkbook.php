<?php

namespace App\Modules\Agriculture\Application;

use App\Models\Farmer;
use App\Models\HarvestRecord;
use App\Models\Municipality;
use App\Models\User;
use App\Support\AuditTrail;
use App\Support\ConcurrentWrite;
use App\Support\CsvExport;
use App\Support\MunicipalityAccess;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The Excel template and all-or-nothing bulk import for directly entered harvests.
 *
 * A municipality reporting a season of corn, vegetables or fish usually has the
 * figures in a spreadsheet already. Typing them into the single-record form one by
 * one is what kept those commodities out of the production report; this lets the
 * office upload the sheet instead, under the same field rules the form applies.
 *
 * The import is deliberately strict and atomic. Every row is validated, and every
 * farmer reference resolved, before anything is written. One bad row rejects the
 * whole file with a list of what to fix, so an office never has to work out which
 * half of a workbook made it in. Rows that exactly match a harvest already recorded
 * are skipped and counted, which makes uploading the same workbook twice a no-op.
 *
 * Imported harvests are never linked to a parcel or planted section. Those links are
 * checked against a specific saved parcel and season on the single-record form, and
 * a spreadsheet has no safe way to name one.
 */
final class HarvestWorkbook
{
    /**
     * PhpSpreadsheet holds the whole workbook in memory and every row is validated
     * before the write, so one upload is capped. A larger season is split into files.
     */
    public const MAX_ROWS = 2000;

    public const SHEET = 'Harvests';

    public const VALUES_SHEET = 'Allowed values';

    /**
     * Template heading => harvest field, in the template's column order.
     *
     * `farmer_reference` is resolved to a farmer in the target municipality; it is
     * never stored as typed.
     */
    public const COLUMNS = [
        'Farmer ID' => 'farmer_reference',
        'Commodity' => 'commodity',
        'Variety' => 'variety',
        'Season' => 'season',
        'Harvest Year' => 'harvest_year',
        'Date Harvested' => 'date_harvested',
        'Area Harvested (ha)' => 'area_harvested_ha',
        'Quantity' => 'quantity',
        'Unit' => 'quantity_unit',
        'Notes' => 'notes',
    ];

    /** Columns whose values the shared rules require, so the heading must exist. */
    private const REQUIRED_COLUMNS = ['Commodity', 'Harvest Year', 'Quantity', 'Unit'];

    private const COLUMN_WIDTHS = [22, 18, 20, 14, 13, 15, 19, 12, 16, 40];

    /** Enough to show the pattern of a mistake without burying the page. */
    private const MAX_LISTED_ROWS = 20;

    /** Headings are read from row 1 only, and only this far across. */
    private const MAX_HEADER_COLUMNS = 200;

    /** Farmer columns are varchar(255); a longer reference cannot match a farmer. */
    private const MAX_REFERENCE_LENGTH = 255;

    private const LOOKUP_CHUNK = 500;

    private const INSERT_CHUNK = 250;

    /** Days between Excel's 1900 and 1904 date systems. */
    private const MAC_1904_OFFSET_DAYS = 1462;

    private const NOTHING_IMPORTED = 'No harvests were imported.';

    public function __construct(
        private MunicipalityAccess $municipalityAccess,
        private ConcurrentWrite $writes,
        private ValidationFactory $validation,
    ) {}

    /**
     * Render the blank import template to a temporary `.xlsx` file.
     *
     * The Harvests sheet carries headings only. An example row would be imported as a
     * real harvest the first time someone forgot to delete it.
     *
     * @return array{path: string, filename: string}
     */
    public function template(): array
    {
        $spreadsheet = new Spreadsheet;

        try {
            $harvests = $spreadsheet->getActiveSheet();
            $harvests->setTitle(self::SHEET);
            $this->writeHeadings($harvests);

            $this->writeAllowedValues($spreadsheet->createSheet());
            $spreadsheet->setActiveSheetIndex(0);

            $path = tempnam(sys_get_temp_dir(), 'harvest_template_');
            if ($path === false) {
                throw ValidationException::withMessages([
                    'file' => 'The template could not be prepared on the server. Please try again.',
                ]);
            }

            (new Xlsx($spreadsheet))->save($path);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        return ['path' => $path, 'filename' => 'harvest-records-import-template.xlsx'];
    }

    /**
     * Import a harvest workbook into one municipality, all rows or none.
     *
     * @return array{rows: int, created: int, already_recorded: int, repeated_in_file: int}
     */
    public function import(User $user, string $path, int $municipalityId): array
    {
        Gate::forUser($user)->authorize('import', HarvestRecord::class);

        // The controller resolved this already. Resolving again keeps the service from
        // trusting a municipality id if it is ever called from somewhere that did not.
        $municipalityId = $this->municipalityAccess->resolveForWrite($user, $municipalityId);

        [$rows, $calendar] = $this->readRows($path);
        $candidates = $this->prepare($user, $municipalityId, $rows, $calendar);

        $result = $this->writes->transaction(
            fn (): array => $this->persist($user, $municipalityId, $candidates)
        );

        // Counts only: the rows carry farmer identifiers and free-text notes, and the
        // audit log is not the place to copy them.
        AuditTrail::record(
            'imported',
            'Harvest records',
            $user->name.' imported harvest records from an Excel workbook.',
            [
                'actor' => $user,
                'municipality_id' => $municipalityId,
                'metadata' => $result,
            ]
        );

        return $result;
    }

    /**
     * Read the non-blank harvest rows, keyed by their Excel row number, with the
     * workbook's date system.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    private function readRows(string $path): array
    {
        try {
            // Values only: formatting is irrelevant to the import and skipping it keeps
            // the reader's memory use down.
            $spreadsheet = IOFactory::load($path, IReader::READ_DATA_ONLY, [IOFactory::READER_XLSX, IOFactory::READER_XLS]);
        } catch (SpreadsheetException) {
            throw ValidationException::withMessages([
                'file' => 'The workbook could not be read. Open it in Excel, save a new .xlsx copy, and upload it again. '.self::NOTHING_IMPORTED,
            ]);
        }

        try {
            $calendar = $spreadsheet->getExcelCalendar();
            $sheet = $spreadsheet->getSheetByName(self::SHEET) ?? $spreadsheet->getSheet(0);
            $rows = $this->extractRows($sheet);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => 'The '.self::SHEET.' sheet has no harvest rows below the headings. '.self::NOTHING_IMPORTED,
            ]);
        }

        return [$rows, $calendar];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractRows(Worksheet $sheet): array
    {
        $fields = $this->mapHeadings($sheet);
        $cells = $sheet->getCellCollection();
        $rows = [];

        // Walk only the cells that exist. A stray value in row 100,000 must not make
        // the import allocate every empty row above it.
        foreach ($cells->getCoordinates() as $coordinate) {
            [$column, $row] = Coordinate::coordinateFromString($coordinate);
            $row = (int) $row;
            $field = $fields[$column] ?? null;

            if ($field === null || $row < 2) {
                continue;
            }

            $cell = $cells->get($coordinate);
            $value = $cell === null ? null : $this->cellValue($cell);
            if ($value === null) {
                continue;
            }

            $rows[$row][$field] = $value;

            if (count($rows) > self::MAX_ROWS) {
                throw ValidationException::withMessages([
                    'file' => 'This workbook has more than '.number_format(self::MAX_ROWS).' harvest rows. '
                        .'Split it into files of up to '.number_format(self::MAX_ROWS).' rows and upload each one. '
                        .self::NOTHING_IMPORTED,
                ]);
            }
        }

        ksort($rows);

        return $rows;
    }

    /**
     * Map column letters to fields from the headings in row 1.
     *
     * @return array<string, string>
     */
    private function mapHeadings(Worksheet $sheet): array
    {
        $known = [];
        foreach (self::COLUMNS as $heading => $field) {
            $known[$this->matchKey($heading)] = [$heading, $field];
        }

        $cells = $sheet->getCellCollection();
        $lastColumn = min(
            Coordinate::columnIndexFromString($sheet->getHighestDataColumn(1)),
            self::MAX_HEADER_COLUMNS
        );

        $fields = [];
        $found = [];
        for ($index = 1; $index <= $lastColumn; $index++) {
            $column = Coordinate::stringFromColumnIndex($index);
            $cell = $cells->get($column.'1');
            $heading = $cell === null ? null : $this->text($this->cellValue($cell));
            $match = $heading === null ? null : ($known[$this->matchKey($heading)] ?? null);

            // Extra columns are ignored, so an office can keep a farmer-name column
            // beside the ID for its own checking.
            if ($match === null) {
                continue;
            }

            [$label, $field] = $match;
            if (isset($found[$label])) {
                throw ValidationException::withMessages([
                    'file' => 'The column heading "'.$label.'" appears more than once on the '.self::SHEET
                        .' sheet. Keep one column for each heading. '.self::NOTHING_IMPORTED,
                ]);
            }

            $found[$label] = true;
            $fields[$column] = $field;
        }

        $missing = array_values(array_diff(self::REQUIRED_COLUMNS, array_keys($found)));
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => 'The '.self::SHEET.' sheet is missing the '.(count($missing) === 1 ? 'column' : 'columns').' '
                    .implode(', ', $missing).'. Keep the headings from the template in row 1. '.self::NOTHING_IMPORTED,
            ]);
        }

        return $fields;
    }

    /**
     * Validate every row and resolve every farmer, or reject the whole file.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{key: string, payload: array<string, mixed>}>
     */
    private function prepare(User $user, int $municipalityId, array $rows, int $calendar): array
    {
        $errors = [];
        $prepared = [];

        // Built once: every row is judged against the same year bound, and the rule
        // arrays are not rebuilt two thousand times.
        $rules = HarvestFieldRules::rules();
        $messages = HarvestFieldRules::messages();
        $attributes = $this->attributeNames();

        foreach ($rows as $rowNumber => $cells) {
            [$data, $problems] = $this->normalize($cells, $calendar);

            $validator = $this->validation->make($data, $rules, $messages, $attributes);
            $validator->after(function (Validator $validator) use ($data): void {
                HarvestFieldRules::rejectDateOutsideReportedYear($validator, $data['date_harvested'], $data['harvest_year']);
            });

            if ($validator->fails()) {
                array_push($problems, ...$validator->errors()->all());
            }

            if ($problems !== []) {
                $errors[$rowNumber] = $problems;
            }

            $prepared[$rowNumber] = $data;
        }

        $references = array_values(array_filter(array_column($prepared, 'farmer_reference')));
        $farmers = $this->resolveFarmers($user, $municipalityId, $references);

        foreach ($prepared as $rowNumber => &$data) {
            $reference = $data['farmer_reference'];
            $data['farmer_id'] = null;

            if ($reference === null) {
                continue;
            }

            $match = $farmers[$this->referenceKey($reference)] ?? null;
            if ($match === null) {
                // The same words whether the farmer does not exist or belongs to another
                // municipality, so the import cannot be used to probe other registries.
                $errors[$rowNumber][] = 'No farmer registered in this municipality has this Farmer ID. '
                    .'Use the AgriLGU ID, FFRS or RSBSA number of a farmer registered here, or leave Farmer ID blank.';
            } elseif ($match === false) {
                $errors[$rowNumber][] = 'This Farmer ID matches more than one farmer in this municipality. '
                    .'Use the farmer\'s AgriLGU ID (AGRI-F-…) instead.';
            } else {
                $data['farmer_id'] = $match;
            }
        }
        unset($data);

        if ($errors !== []) {
            $this->reject($errors);
        }

        $candidates = [];
        foreach ($prepared as $data) {
            $payload = $this->payload($data, $municipalityId, $user);
            $candidates[] = ['key' => $this->duplicateKey($payload), 'payload' => $payload];
        }

        return $candidates;
    }

    /**
     * Turn raw cells into the values the shared rules validate.
     *
     * @param  array<string, mixed>  $cells
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function normalize(array $cells, int $calendar): array
    {
        $problems = [];

        $date = null;
        if (isset($cells['date_harvested'])) {
            $date = $this->dateValue($cells['date_harvested'], $calendar);
            if ($date === null) {
                $problems[] = 'Date Harvested must be an Excel date or written as YYYY-MM-DD.';
            }
        }

        $reference = $this->text($cells['farmer_reference'] ?? null);
        if ($reference !== null && mb_strlen($reference) > self::MAX_REFERENCE_LENGTH) {
            $problems[] = 'Farmer ID is too long to be an AgriLGU ID, FFRS or RSBSA number.';
            $reference = null;
        }

        return [[
            'farmer_reference' => $reference,
            'commodity' => $this->vocabulary($cells['commodity'] ?? null, HarvestRecord::COMMODITY_LABELS),
            'variety' => $this->text($cells['variety'] ?? null),
            'season' => $this->vocabulary($cells['season'] ?? null, HarvestRecord::SEASON_LABELS),
            'harvest_year' => $this->wholeNumber($cells['harvest_year'] ?? null),
            'date_harvested' => $date,
            'area_harvested_ha' => $cells['area_harvested_ha'] ?? null,
            'quantity' => $cells['quantity'] ?? null,
            'quantity_unit' => $this->vocabulary($cells['quantity_unit'] ?? null, HarvestRecord::QUANTITY_UNIT_LABELS),
            'notes' => $this->text($cells['notes'] ?? null),
        ], $problems];
    }

    /**
     * Resolve each distinct reference to exactly one farmer in the target municipality.
     *
     * Returns, per normalized reference, the farmer id, `false` when more than one
     * farmer matches, and no entry when none does.
     *
     * @param  array<int, string>  $references
     * @return array<string, int|false>
     */
    private function resolveFarmers(User $user, int $municipalityId, array $references): array
    {
        $references = array_values(array_unique($references));
        $byValue = [];
        $byId = [];

        foreach (array_chunk($references, self::LOOKUP_CHUNK) as $chunk) {
            $ids = array_values(array_filter(array_map(fn (string $reference) => $this->agriLguId($reference), $chunk)));

            // Scoped first, then narrowed to the target municipality, so a reference
            // can only ever reach a farmer this account may already see and write to.
            $farmers = $this->municipalityAccess
                ->scope(Farmer::query(), $user)
                ->where('municipality_id', $municipalityId)
                ->where(function (Builder $query) use ($chunk, $ids): void {
                    $query->whereIn('ffrs', $chunk)->orWhereIn('rsbsa_no', $chunk);
                    if ($ids !== []) {
                        $query->orWhereIn('id', $ids);
                    }
                })
                ->get(['id', 'ffrs', 'rsbsa_no']);

            foreach ($farmers as $farmer) {
                $byId[(int) $farmer->id] = true;
                foreach ([$farmer->ffrs, $farmer->rsbsa_no] as $value) {
                    if (is_string($value) && trim($value) !== '') {
                        $byValue[$this->referenceKey($value)][(int) $farmer->id] = true;
                    }
                }
            }
        }

        $resolved = [];
        foreach ($references as $reference) {
            $matches = $byValue[$this->referenceKey($reference)] ?? [];
            $id = $this->agriLguId($reference);
            if ($id !== null && isset($byId[$id])) {
                $matches[$id] = true;
            }

            if ($matches === []) {
                continue;
            }

            $resolved[$this->referenceKey($reference)] = count($matches) === 1 ? (int) array_key_first($matches) : false;
        }

        return $resolved;
    }

    /**
     * Write the new rows, skipping exact duplicates, inside the caller's transaction.
     *
     * @param  array<int, array{key: string, payload: array<string, mixed>}>  $candidates
     * @return array{rows: int, created: int, already_recorded: int, repeated_in_file: int}
     */
    private function persist(User $user, int $municipalityId, array $candidates): array
    {
        // Serializes imports into one municipality, so two uploads of the same workbook
        // cannot both pass the duplicate check before either has committed. Parsing and
        // validation already happened outside, so the lock covers only the inserts.
        Municipality::query()->whereKey($municipalityId)->lockForUpdate()->firstOrFail(['id']);

        $existing = $this->existingKeys($user, $municipalityId, $candidates);
        $seen = [];
        $insert = [];
        $already = 0;
        $repeated = 0;

        foreach ($candidates as $candidate) {
            if (isset($existing[$candidate['key']])) {
                $already++;
            } elseif (isset($seen[$candidate['key']])) {
                $repeated++;
            } else {
                $seen[$candidate['key']] = true;
                $insert[] = $candidate['payload'];
            }
        }

        $timestamp = (new HarvestRecord)->freshTimestampString();
        foreach (array_chunk($insert, self::INSERT_CHUNK) as $chunk) {
            // One summary audit event covers the import, so the rows are inserted in
            // bulk rather than one model save (and one audit row) at a time.
            HarvestRecord::query()->insert(array_map(
                fn (array $payload): array => $payload + ['created_at' => $timestamp, 'updated_at' => $timestamp],
                $chunk
            ));
        }

        return [
            'rows' => count($candidates),
            'created' => count($insert),
            'already_recorded' => $already,
            'repeated_in_file' => $repeated,
        ];
    }

    /**
     * The duplicate keys of direct harvests already recorded in this municipality.
     *
     * Projected harvests are excluded: they belong to an assistance release and are
     * rewritten from it, so they say nothing about whether a direct entry exists.
     *
     * @param  array<int, array{key: string, payload: array<string, mixed>}>  $candidates
     * @return array<string, true>
     */
    private function existingKeys(User $user, int $municipalityId, array $candidates): array
    {
        $wanted = array_flip(array_column($candidates, 'key'));
        $payloads = array_column($candidates, 'payload');
        $found = [];

        $records = $this->municipalityAccess
            ->scope(HarvestRecord::query(), $user)
            ->where('municipality_id', $municipalityId)
            ->whereNull('rice_seed_distribution_id')
            ->whereIn('commodity', array_values(array_unique(array_column($payloads, 'commodity'))))
            ->whereIn('harvest_year', array_values(array_unique(array_column($payloads, 'harvest_year'))))
            ->select(['id', 'farmer_id', 'commodity', 'variety', 'season', 'harvest_year', 'date_harvested', 'quantity', 'quantity_unit'])
            ->toBase()
            ->lazyById(1000, 'id');

        foreach ($records as $record) {
            $key = $this->duplicateKey((array) $record);
            if (isset($wanted[$key])) {
                $found[$key] = true;
            }
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data, int $municipalityId, User $user): array
    {
        // Parcel and planted-section links are left unset on purpose; see the class
        // comment. Decimals are fixed to the column scale so the stored value and the
        // duplicate key are the same number.
        return [
            'municipality_id' => $municipalityId,
            'farmer_id' => $data['farmer_id'],
            'commodity' => $data['commodity'],
            'variety' => $data['variety'],
            'season' => $data['season'],
            'harvest_year' => (int) $data['harvest_year'],
            'date_harvested' => $data['date_harvested'],
            'area_harvested_ha' => $data['area_harvested_ha'] === null
                ? null
                : number_format((float) $data['area_harvested_ha'], 4, '.', ''),
            'quantity' => number_format((float) $data['quantity'], 3, '.', ''),
            'quantity_unit' => $data['quantity_unit'],
            'notes' => $data['notes'],
            'recorded_by' => $user->id,
        ];
    }

    /**
     * What makes two harvests the same harvest for re-upload purposes.
     *
     * @param  array<string, mixed>  $record
     */
    private function duplicateKey(array $record): string
    {
        $date = $record['date_harvested'] ?? null;

        return json_encode([
            $record['farmer_id'] === null ? null : (int) $record['farmer_id'],
            (string) $record['commodity'],
            trim((string) ($record['variety'] ?? '')),
            (string) ($record['season'] ?? ''),
            (int) $record['harvest_year'],
            // Stored dates may carry a midnight time depending on the driver.
            $date === null ? '' : substr((string) $date, 0, 10),
            number_format((float) $record['quantity'], 3, '.', ''),
            (string) $record['quantity_unit'],
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<int, array<int, string>>  $errors
     */
    private function reject(array $errors): never
    {
        ksort($errors);

        // Messages name the row and the rule, never the cell value: rows carry farmer
        // identifiers that should not be echoed back into a page or a log.
        $lines = [self::NOTHING_IMPORTED.' Correct the rows below and upload the workbook again.'];
        foreach (array_slice($errors, 0, self::MAX_LISTED_ROWS, true) as $rowNumber => $messages) {
            $lines[] = 'Row '.$rowNumber.': '.implode(' ', array_unique($messages));
        }

        $remaining = count($errors) - self::MAX_LISTED_ROWS;
        if ($remaining > 0) {
            $lines[] = 'And '.number_format($remaining).' more '.($remaining === 1 ? 'row' : 'rows').' with problems.';
        }

        throw ValidationException::withMessages(['file' => $lines]);
    }

    /**
     * A cell's value as typed, or the result Excel last saved for a formula.
     *
     * Formulas from an uploaded file are never evaluated on the server: the cached
     * result is what the operator saw, and running the calculation engine on an
     * untrusted workbook is work (and attack surface) the import does not need.
     */
    private function cellValue(Cell $cell): mixed
    {
        $value = $cell->getDataType() === DataType::TYPE_FORMULA
            ? $cell->getOldCalculatedValue()
            : $cell->getValue();

        if ($value instanceof RichText) {
            $value = $value->getPlainText();
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        // A number typed into a text column (a variety called 216, a numeric FFRS)
        // arrives as a float; PHP renders whole floats without a trailing ".0".
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Accept a list's code or its label, in any case and spacing.
     *
     * An unrecognized value is passed through unchanged, so the shared `in` rule
     * reports it with the same message the form uses.
     *
     * @param  array<string, string>  $labels
     */
    private function vocabulary(mixed $value, array $labels): ?string
    {
        $value = $this->text($value);
        if ($value === null) {
            return null;
        }

        $needle = $this->matchKey($value);
        foreach ($labels as $code => $label) {
            if ($needle === $this->matchKey($code) || $needle === $this->matchKey($label)) {
                return $code;
            }
        }

        return $value;
    }

    private function wholeNumber(mixed $value): mixed
    {
        // Excel stores every number as a float; 2026.0 is the year 2026.
        if (is_float($value) && is_finite($value) && floor($value) === $value) {
            return (int) $value;
        }

        return is_string($value) ? trim($value) : $value;
    }

    /**
     * A harvest date as Y-m-d, or null when the cell is not a readable date.
     *
     * Only Excel's own dates and unambiguous YYYY-MM-DD text are accepted. A typed
     * "03/04/2026" means March or April depending on who typed it, and guessing would
     * file the harvest under the wrong month.
     */
    private function dateValue(mixed $value, int $calendar): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            $serial = (float) $value;

            // Excel's calendar runs from serial 1 to 2958465 (9999-12-31).
            if (! is_finite($serial) || $serial < 1 || $serial >= 2958466) {
                return null;
            }

            // Computed directly rather than through PhpSpreadsheet's process-wide
            // calendar setting, so a workbook saved in the 1904 date system (older
            // Mac Excel) cannot shift its dates by four years.
            $days = (int) floor($serial) + ($calendar === ExcelDate::CALENDAR_MAC_1904 ? self::MAC_1904_OFFSET_DAYS : 0);

            return (new DateTimeImmutable('1899-12-30', new DateTimeZone('UTC')))
                ->modify('+'.$days.' days')
                ->format('Y-m-d');
        }

        if (is_string($value)
            && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts) === 1
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return $value;
        }

        return null;
    }

    /**
     * Parse an AgriLGU farmer ID (AGRI-F-000123, or a legacy PAIS-FRM-… card).
     *
     * Mirrors `App\Modules\Farmers\Application\FarmerIdentifier::parse()`. Importing it
     * would make Agriculture depend on Farmers, which already depends on Agriculture
     * through Land, and ModularArchitectureTest rejects that cycle. HarvestImportTest
     * imports IDs produced by `FarmerIdentifier::format()` to keep the two in step.
     */
    private function agriLguId(string $reference): ?int
    {
        if (preg_match('/^(?:AGRI-F|PAIS-FRM)-([0-9]+)$/iD', trim($reference), $matches) !== 1) {
            return null;
        }

        $digits = ltrim($matches[1], '0');
        if ($digits === '') {
            return null;
        }

        $id = filter_var($digits, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : $id;
    }

    /**
     * Farmer references compare without regard to case or surrounding space, as the
     * MySQL collation the registry is stored under already does.
     */
    private function referenceKey(string $reference): string
    {
        return mb_strtoupper(trim($reference));
    }

    private function matchKey(string $value): string
    {
        return (string) preg_replace('/\s+/u', '', mb_strtolower($value));
    }

    /**
     * @return array<string, string>
     */
    private function attributeNames(): array
    {
        return array_flip(array_diff_key(self::COLUMNS, ['Farmer ID' => true]));
    }

    private function writeHeadings(Worksheet $sheet): void
    {
        // Column-level formats, so nothing is created below the headings: the Farmer
        // ID stays text (FFRS and RSBSA numbers keep their leading zeros) and a date
        // typed into Date Harvested displays the way the import reads it.
        $sheet->getStyle('A:A')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('F:F')->getNumberFormat()->setFormatCode('yyyy-mm-dd');

        $index = 1;
        foreach (array_keys(self::COLUMNS) as $position => $heading) {
            $column = Coordinate::stringFromColumnIndex($index);
            $this->put($sheet, $column.'1', $heading);
            $sheet->getColumnDimension($column)->setWidth(self::COLUMN_WIDTHS[$position] ?? 16);
            $index++;
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count(self::COLUMNS));
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
    }

    private function writeAllowedValues(Worksheet $sheet): void
    {
        $sheet->setTitle(self::VALUES_SHEET);

        $lines = [
            'How to fill in the '.self::SHEET.' sheet',
            'Enter one harvest per row, starting on row 2. Keep the headings in row 1 as they are.',
            'Required: Commodity, Harvest Year, Quantity and Unit. Every other column may be left blank.',
            'Farmer ID: the farmer\'s AgriLGU ID (for example AGRI-F-000123), FFRS number or RSBSA number. The farmer must be registered in the municipality chosen when uploading. Leave it blank for a municipality-wide figure.',
            'Commodity, Season and Unit: type either the code or the label listed below.',
            'Harvest Year: a year such as 2026. Date Harvested: an Excel date or YYYY-MM-DD, not in the future and within one year of the Harvest Year.',
            'Area Harvested (ha) and Quantity: numbers only. Quantities are never converted between units.',
            'Up to '.number_format(self::MAX_ROWS).' rows per file. If any row has a problem, nothing is imported; fix the rows listed and upload again.',
            'A row matching a harvest already recorded (same farmer, commodity, variety, season, year, date, quantity and unit) is skipped, so uploading the same file twice adds nothing.',
            'Imported harvests are not linked to a parcel or planted section. Columns not listed here are ignored.',
        ];

        $row = 1;
        foreach ($lines as $line) {
            $this->put($sheet, 'A'.$row, $line);
            $row++;
        }
        $sheet->getStyle('A1')->getFont()->setBold(true);

        $row++;
        $tableHeading = $row;
        foreach (['List', 'Code', 'Label'] as $position => $heading) {
            $this->put($sheet, Coordinate::stringFromColumnIndex($position + 1).$row, $heading);
        }
        $sheet->getStyle('A'.$tableHeading.':C'.$tableHeading)->getFont()->setBold(true);
        $row++;

        foreach ([
            'Commodity' => HarvestRecord::COMMODITY_LABELS,
            'Season' => HarvestRecord::SEASON_LABELS,
            'Unit' => HarvestRecord::QUANTITY_UNIT_LABELS,
        ] as $list => $labels) {
            foreach ($labels as $code => $label) {
                $this->put($sheet, 'A'.$row, $list);
                $this->put($sheet, 'B'.$row, $code);
                $this->put($sheet, 'C'.$row, $label);
                $row++;
            }
        }

        $sheet->getColumnDimension('A')->setWidth(16);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(24);
    }

    /**
     * Every template cell is written as guarded, explicit text.
     */
    private function put(Worksheet $sheet, string $coordinate, string $value): void
    {
        $sheet->setCellValueExplicit($coordinate, CsvExport::value($value), DataType::TYPE_STRING);
    }
}

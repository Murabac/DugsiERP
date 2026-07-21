<?php

namespace App\Support;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\StudentStatus;
use App\Enums\WaitlistStatus;
use App\Models\ClassWaitlist;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Options as XlsxReaderOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as PhpSpreadsheetXlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class StudentBulkImport
{
    public const MAX_ROWS = 500;

    /** @var list<string> */
    public const HEADERS = [
        'full_name',
        'dob',
        'gender',
        'city',
        'address',
        'previous_school',
        'guardian_name',
        'guardian_phone',
        'relationship',
        'enrollment_date',
    ];

    public static function downloadTemplate(SchoolClass $schoolClass): BinaryFileResponse
    {
        $filename = 'student-bulk-template-'.$schoolClass->form_level.$schoolClass->section.'.xlsx';
        $path = tempnam(sys_get_temp_dir(), 'dugsi-bulk-');
        $xlsxPath = $path.'.xlsx';
        @unlink($path);

        $spreadsheet = self::buildValidatedTemplate($schoolClass);
        (new PhpSpreadsheetXlsxWriter($spreadsheet))->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();

        return response()
            ->download($xlsxPath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    /**
     * Excel template with dropdowns + date/text validation (PhpSpreadsheet).
     */
    private static function buildValidatedTemplate(SchoolClass $schoolClass): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $birthYears = AcademicYear::birthYearBounds();
        $lastDataRow = self::MAX_ROWS + 1; // row 1 = headers

        $genders = array_map(fn (Gender $g) => $g->label(), Gender::cases());
        $relationships = array_map(fn (GuardianRelationship $r) => $r->label(), GuardianRelationship::cases());
        $cities = SomalilandCities::all();

        // Hidden lists sheet for dropdown sources
        $lists = $spreadsheet->getActiveSheet();
        $lists->setTitle('Lists');
        $lists->setCellValue('A1', 'gender');
        foreach ($genders as $i => $label) {
            $lists->setCellValue('A'.($i + 2), $label);
        }
        $lists->setCellValue('B1', 'relationship');
        foreach ($relationships as $i => $label) {
            $lists->setCellValue('B'.($i + 2), $label);
        }
        $lists->setCellValue('C1', 'city');
        foreach ($cities as $i => $label) {
            $lists->setCellValue('C'.($i + 2), $label);
        }
        $lists->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        $genderRange = 'Lists!$A$2:$A$'.(count($genders) + 1);
        $relationshipRange = 'Lists!$B$2:$B$'.(count($relationships) + 1);
        $cityRange = 'Lists!$C$2:$C$'.(count($cities) + 1);

        // Students entry sheet
        $students = $spreadsheet->createSheet(0);
        $students->setTitle('Students');
        $spreadsheet->setActiveSheetIndex(0);

        foreach (self::HEADERS as $col => $header) {
            $students->setCellValue([$col + 1, 1], $header);
        }

        $students->getStyle('A1:J1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E3A6E'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $students->freezePane('A2');
        $students->setAutoFilter('A1:J1');

        foreach (range('A', 'J') as $col) {
            $students->getColumnDimension($col)->setAutoSize(true);
        }

        // Force text columns so Excel does not turn phones into numbers / scientific notation.
        foreach (['A', 'E', 'F', 'G', 'H'] as $col) {
            $students->getStyle("{$col}2:{$col}{$lastDataRow}")
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        $students->getStyle("B2:B{$lastDataRow}")
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_DATE_YYYYMMDD);
        $students->getStyle("J2:J{$lastDataRow}")
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_DATE_YYYYMMDD);

        // Prefill a few empty typed cells so phone stays text when typing.
        for ($row = 2; $row <= min(25, $lastDataRow); $row++) {
            $students->setCellValueExplicit("H{$row}", '', DataType::TYPE_STRING);
        }

        self::applyListValidation($students, "C2:C{$lastDataRow}", $genderRange, 'Choose Male or Female.');
        self::applyListValidation($students, "I2:I{$lastDataRow}", $relationshipRange, 'Choose a relationship from the list.');
        self::applyListValidation($students, "D2:D{$lastDataRow}", $cityRange, 'Choose a Somaliland city (or leave blank).', allowBlank: true);

        self::applyDateValidation(
            $students,
            "B2:B{$lastDataRow}",
            sprintf('%04d-01-01', $birthYears['min']),
            sprintf('%04d-12-31', $birthYears['max']),
            'Enter date of birth as YYYY-MM-DD (or use the Excel date picker).'
        );
        self::applyDateValidation(
            $students,
            "J2:J{$lastDataRow}",
            '2000-01-01',
            now()->addYear()->format('Y-m-d'),
            'Enter enrollment date as YYYY-MM-DD (optional).',
            allowBlank: true
        );

        self::applyTextLengthValidation($students, "A2:A{$lastDataRow}", 255, 'Student full name is required (max 255 characters).', allowBlank: false);
        self::applyTextLengthValidation($students, "G2:G{$lastDataRow}", 255, 'Guardian name is required (max 255 characters).', allowBlank: false);
        self::applyTextLengthValidation($students, "H2:H{$lastDataRow}", 32, 'Guardian phone is required (max 32 characters).', allowBlank: false);
        self::applyTextLengthValidation($students, "E2:E{$lastDataRow}", 255, 'Address max 255 characters.', allowBlank: true);
        self::applyTextLengthValidation($students, "F2:F{$lastDataRow}", 255, 'Previous school max 255 characters.', allowBlank: true);

        // Instructions sheet
        $help = $spreadsheet->createSheet();
        $help->setTitle('Instructions');
        $helpRows = [
            ['Dugsi ERP — Bulk student upload'],
            ['Class', $schoolClass->displayName()],
            ['Academic year', AcademicYear::current()],
            [''],
            ['How to fill the Students sheet'],
            ['1. Stay on the Students sheet. Do not rename or delete the header row.'],
            ['2. Use the dropdowns for gender, city, and relationship — do not type free text.'],
            ['3. Enter dates as YYYY-MM-DD or use Excel’s date picker.'],
            ['4. Enter phone numbers as text (may start with +). Do not use formulas.'],
            ['5. Upload at most '.self::MAX_ROWS.' students. Extra rows are rejected.'],
            ['6. If the same name and date of birth already exist at the school this year, the row is skipped.'],
            [''],
            ['Column', 'Rules'],
            ['full_name', 'Required text — student full name'],
            ['dob', 'Required date between '.$birthYears['min'].' and '.$birthYears['max']],
            ['gender', 'Dropdown: '.implode(', ', $genders)],
            ['city', 'Dropdown — Somaliland cities (optional)'],
            ['address', 'Optional text — address'],
            ['previous_school', 'Optional text — previous school'],
            ['guardian_name', 'Required text — parent/guardian name'],
            ['guardian_phone', 'Required text / phone'],
            ['relationship', 'Dropdown: '.implode(', ', $relationships)],
            ['enrollment_date', 'Optional date (if blank, today is used)'],
            [''],
            ['Note', 'Excel warns on invalid cells. The server still validates every row on upload.'],
        ];
        foreach ($helpRows as $i => $row) {
            foreach ($row as $j => $value) {
                $help->setCellValue([$j + 1, $i + 1], $value);
            }
        }
        $help->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $help->getColumnDimension('A')->setWidth(22);
        $help->getColumnDimension('B')->setWidth(78);

        return $spreadsheet;
    }

    private static function applyListValidation(
        Worksheet $sheet,
        string $range,
        string $formulaRange,
        string $error,
        bool $allowBlank = false,
    ): void {
        $validation = $sheet->getCell(explode(':', $range)[0])->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank($allowBlank);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setFormula1($formulaRange);
        $validation->setErrorTitle('Invalid value');
        $validation->setError($error);
        $validation->setPromptTitle('Choose from list');
        $validation->setPrompt($error);
        $sheet->setDataValidation($range, $validation);
    }

    private static function applyDateValidation(
        Worksheet $sheet,
        string $range,
        string $minYmd,
        string $maxYmd,
        string $error,
        bool $allowBlank = false,
    ): void {
        $validation = $sheet->getCell(explode(':', $range)[0])->getDataValidation();
        $validation->setType(DataValidation::TYPE_DATE);
        $validation->setOperator(DataValidation::OPERATOR_BETWEEN);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank($allowBlank);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        // Excel date serials for bounds
        $validation->setFormula1((string) self::excelDateSerial($minYmd));
        $validation->setFormula2((string) self::excelDateSerial($maxYmd));
        $validation->setErrorTitle('Invalid date');
        $validation->setError($error);
        $validation->setPromptTitle('Date');
        $validation->setPrompt($error);
        $sheet->setDataValidation($range, $validation);
    }

    private static function applyTextLengthValidation(
        Worksheet $sheet,
        string $range,
        int $maxLength,
        string $error,
        bool $allowBlank = true,
    ): void {
        $validation = $sheet->getCell(explode(':', $range)[0])->getDataValidation();
        $validation->setType(DataValidation::TYPE_TEXTLENGTH);
        $validation->setOperator(DataValidation::OPERATOR_LESSTHANOREQUAL);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank($allowBlank);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setFormula1((string) $maxLength);
        $validation->setErrorTitle('Invalid text');
        $validation->setError($error);
        $validation->setPromptTitle('Text');
        $validation->setPrompt($error);
        $sheet->setDataValidation($range, $validation);
    }

    private static function excelDateSerial(string $ymd): int
    {
        $date = Carbon::createFromFormat('Y-m-d', $ymd)->startOfDay();
        $excelEpoch = Carbon::create(1899, 12, 30)->startOfDay();

        return (int) $excelEpoch->diffInDays($date, false);
    }

    /**
     * Detect spreadsheet kind from file contents (not the client filename).
     *
     * @return 'xlsx'|'csv'
     */
    public static function detectFormat(string $absolutePath): string
    {
        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            throw new \InvalidArgumentException('Could not read the uploaded file.');
        }

        $header = (string) fread($handle, 8);
        fclose($handle);

        // XLSX is a ZIP package (PK\x03\x04).
        if (str_starts_with($header, "PK\x03\x04") || str_starts_with($header, "PK\x05\x06") || str_starts_with($header, "PK\x07\x08")) {
            return 'xlsx';
        }

        return 'csv';
    }

    /**
     * @return array{imported: int, waitlisted: int, skipped: int, duplicates: int, errors: list<string>}
     */
    public static function import(SchoolClass $schoolClass, string $absolutePath, ?string $format = null): array
    {
        $format ??= self::detectFormat($absolutePath);
        $rows = self::readRows($absolutePath, $format);

        if (count($rows) > self::MAX_ROWS) {
            throw new \InvalidArgumentException(
                'Too many rows ('.count($rows).'). Upload at most '.self::MAX_ROWS.' students at a time.'
            );
        }

        $academicYear = AcademicYear::current();
        $birthYears = AcademicYear::birthYearBounds();
        $defaultEnrollmentDate = now()->toDateString();
        $existingKeys = self::existingSchoolStudentKeys($academicYear);

        $imported = 0;
        $waitlisted = 0;
        $skipped = 0;
        $duplicates = 0;
        $errors = [];
        /** @var array<string, true> $seenInFile */
        $seenInFile = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2; // header is row 1

            try {
                if (self::rowIsEmpty($row) || self::rowIsInstructions($row)) {
                    continue;
                }

                $parsed = self::parseRow($row, $birthYears, $defaultEnrollmentDate);
            } catch (Throwable $e) {
                $skipped++;
                $errors[] = "Row {$line}: ".$e->getMessage();

                continue;
            }

            $fingerprint = self::duplicateKey($parsed['full_name'], $parsed['dob']);

            if (isset($seenInFile[$fingerprint])) {
                $duplicates++;
                $errors[] = "Row {$line}: duplicate of an earlier row in this file (same name + date of birth) — skipped.";

                continue;
            }
            $seenInFile[$fingerprint] = true;

            if (isset($existingKeys[$fingerprint])) {
                $duplicates++;
                $errors[] = "Row {$line}: {$parsed['full_name']} ({$parsed['dob']}) already exists in the school this year — skipped.";

                continue;
            }

            try {
                $onWaitlist = DB::transaction(function () use ($schoolClass, $parsed, $academicYear, $fingerprint, &$existingKeys) {
                    $class = SchoolClass::query()
                        ->whereKey($schoolClass->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    // Re-check inside the lock in case of concurrent uploads.
                    if (isset($existingKeys[$fingerprint]) || self::studentExistsInSchoolYear($academicYear, $parsed['full_name'], $parsed['dob'])) {
                        return null;
                    }

                    $onWaitlist = $class->isFull();

                    $student = Student::query()->create([
                        'student_code' => Student::nextStudentCode(),
                        'full_name' => $parsed['full_name'],
                        'dob' => $parsed['dob'],
                        'gender' => $parsed['gender'],
                        'city' => $parsed['city'],
                        'address' => $parsed['address'],
                        'previous_school' => $parsed['previous_school'],
                        'status' => $onWaitlist ? StudentStatus::Waitlisted : StudentStatus::Active,
                        'need_based_discount_amount' => 0,
                    ]);

                    Guardian::query()->create([
                        'student_id' => $student->id,
                        'full_name' => $parsed['guardian_name'],
                        'phone' => $parsed['guardian_phone'],
                        'relationship' => $parsed['relationship'],
                        'is_primary' => true,
                    ]);

                    if ($onWaitlist) {
                        ClassWaitlist::query()->create([
                            'student_id' => $student->id,
                            'class_id' => $class->id,
                            'academic_year' => $academicYear,
                            'position' => $class->nextWaitlistPosition(),
                            'status' => WaitlistStatus::Waiting,
                        ]);
                    } else {
                        Enrollment::query()->create([
                            'student_id' => $student->id,
                            'class_id' => $class->id,
                            'academic_year' => $academicYear,
                            'roll_number' => $class->nextRollNumber(),
                            'enrollment_date' => $parsed['enrollment_date'],
                            'status' => StudentStatus::Active,
                        ]);

                        try {
                            MonthlyInvoiceGenerator::ensureForStudent(
                                $student->load('primaryGuardian'),
                                $class,
                            );
                        } catch (Throwable) {
                            // Fee not configured — admission still succeeds.
                        }
                    }

                    $existingKeys[$fingerprint] = true;

                    return $onWaitlist;
                });

                if ($onWaitlist === null) {
                    $duplicates++;
                    $existingKeys[$fingerprint] = true;
                    $errors[] = "Row {$line}: {$parsed['full_name']} ({$parsed['dob']}) already exists in the school this year — skipped.";
                } elseif ($onWaitlist) {
                    $waitlisted++;
                } else {
                    $imported++;
                }
            } catch (Throwable $e) {
                $skipped++;
                $errors[] = "Row {$line}: Could not import this student.";
                report($e);
            }
        }

        return compact('imported', 'waitlisted', 'skipped', 'duplicates', 'errors');
    }

    private static function duplicateKey(string $fullName, string $dob): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($fullName)) ?? '').'|'.$dob;
    }

    /**
     * Preload name+DOB keys for students already enrolled or waitlisted this academic year (any class).
     *
     * @return array<string, true>
     */
    private static function existingSchoolStudentKeys(string $academicYear): array
    {
        $keys = [];

        $students = Student::query()
            ->where(function ($q) use ($academicYear) {
                $q->whereHas('enrollments', fn ($e) => $e->where('academic_year', $academicYear))
                    ->orWhereHas('waitlistEntries', function ($w) use ($academicYear) {
                        $w->where('academic_year', $academicYear)
                            ->where('status', WaitlistStatus::Waiting);
                    });
            })
            ->get(['full_name', 'dob']);

        foreach ($students as $student) {
            if ($student->dob === null) {
                continue;
            }
            $keys[self::duplicateKey($student->full_name, $student->dob->toDateString())] = true;
        }

        return $keys;
    }

    private static function studentExistsInSchoolYear(string $academicYear, string $fullName, string $dob): bool
    {
        $key = self::duplicateKey($fullName, $dob);

        $students = Student::query()
            ->whereDate('dob', $dob)
            ->where(function ($q) use ($academicYear) {
                $q->whereHas('enrollments', fn ($e) => $e->where('academic_year', $academicYear))
                    ->orWhereHas('waitlistEntries', function ($w) use ($academicYear) {
                        $w->where('academic_year', $academicYear)
                            ->where('status', WaitlistStatus::Waiting);
                    });
            })
            ->get(['full_name', 'dob']);

        foreach ($students as $student) {
            if (self::duplicateKey($student->full_name, $student->dob->toDateString()) === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function readRows(string $absolutePath, string $format): array
    {
        /** @var ReaderInterface $reader */
        $reader = $format === 'csv'
            ? new CsvReader
            : new XlsxReader(new XlsxReaderOptions(SHOULD_FORMAT_DATES: true));

        try {
            $reader->open($absolutePath);
        } catch (Throwable $e) {
            // Fallback if ZIP sniff was wrong (rare) or CSV sniffed as xlsx.
            if ($format === 'xlsx') {
                $reader = new CsvReader;
                $reader->open($absolutePath);
            } else {
                throw new \InvalidArgumentException(
                    'Could not read the file. Upload a valid .xlsx or .csv template.'
                );
            }
        }

        $headers = null;
        /** @var array<int, string|null> $headerMap index => column key */
        $headerMap = [];
        $rows = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $values = $row->toArray();

                    if ($headers === null) {
                        $headerMap = self::buildHeaderMap($values);
                        if ($headerMap === []) {
                            throw new \InvalidArgumentException(
                                'Invalid template. First row must include columns: '.implode(', ', self::HEADERS)
                            );
                        }
                        $headers = true;

                        continue;
                    }

                    $assoc = [];
                    foreach ($headerMap as $index => $key) {
                        if ($key === null) {
                            continue;
                        }
                        $assoc[$key] = $values[$index] ?? null;
                    }
                    $rows[] = $assoc;
                }

                break; // first sheet only (Students)
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    /**
     * Map each column index to a header key (or null for ignored/blank columns).
     *
     * @param  list<mixed>  $values
     * @return array<int, string|null>
     */
    private static function buildHeaderMap(array $values): array
    {
        $map = [];
        $found = [];

        foreach ($values as $index => $value) {
            if ($value instanceof DateTimeInterface) {
                $map[$index] = null;

                continue;
            }

            $key = strtolower(trim((string) $value));
            $key = str_replace([' ', '-'], '_', $key);

            if ($key === '' || str_starts_with($key, 'instruction')) {
                $map[$index] = null;

                continue;
            }

            $map[$index] = $key;
            $found[] = $key;
        }

        foreach (['full_name', 'dob', 'gender', 'guardian_name', 'guardian_phone', 'relationship'] as $required) {
            if (! in_array($required, $found, true)) {
                return [];
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (self::cellHasValue($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function rowIsInstructions(array $row): bool
    {
        $first = $row['full_name'] ?? null;
        if ($first instanceof DateTimeInterface || ! is_scalar($first)) {
            return false;
        }

        return str_starts_with(strtolower(trim((string) $first)), 'instructions');
    }

    private static function cellHasValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if ($value instanceof DateTimeInterface) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_scalar($value)) {
            return trim((string) $value) !== '';
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{min: int, max: int}  $birthYears
     * @return array{
     *     full_name: string,
     *     dob: string,
     *     gender: Gender,
     *     city: ?string,
     *     address: ?string,
     *     previous_school: ?string,
     *     guardian_name: string,
     *     guardian_phone: string,
     *     relationship: GuardianRelationship,
     *     enrollment_date: string
     * }
     */
    private static function parseRow(array $row, array $birthYears, string $defaultEnrollmentDate): array
    {
        $fullName = self::asTrimmedString($row['full_name'] ?? null);
        if ($fullName === '') {
            throw new \InvalidArgumentException('full_name is required.');
        }

        $dob = self::parseStrictDate($row['dob'] ?? null, 'dob');

        if ($dob->year < $birthYears['min'] || $dob->year > $birthYears['max'] || $dob->isFuture()) {
            throw new \InvalidArgumentException(
                'dob year must be between '.$birthYears['min'].' and '.$birthYears['max'].'.'
            );
        }

        $gender = self::parseGender(self::asTrimmedString($row['gender'] ?? null));
        $relationship = self::parseRelationship(self::asTrimmedString($row['relationship'] ?? null));

        $guardianName = self::asTrimmedString($row['guardian_name'] ?? null);
        $guardianPhone = self::asTrimmedString($row['guardian_phone'] ?? null);
        if ($guardianName === '' || $guardianPhone === '') {
            throw new \InvalidArgumentException('guardian_name and guardian_phone are required.');
        }

        $city = self::asTrimmedString($row['city'] ?? null);
        if ($city !== '' && ! in_array($city, SomalilandCities::all(), true)) {
            throw new \InvalidArgumentException('city must be a known Somaliland city (or leave blank).');
        }

        $enrollmentRaw = $row['enrollment_date'] ?? null;
        if (! self::cellHasValue($enrollmentRaw)) {
            $enrollmentDate = $defaultEnrollmentDate;
        } else {
            $enrollmentDate = self::parseStrictDate($enrollmentRaw, 'enrollment_date')->toDateString();
        }

        $address = self::asTrimmedString($row['address'] ?? null);
        $previous = self::asTrimmedString($row['previous_school'] ?? null);

        return [
            'full_name' => $fullName,
            'dob' => $dob->toDateString(),
            'gender' => $gender,
            'city' => $city !== '' ? $city : null,
            'address' => $address !== '' ? $address : null,
            'previous_school' => $previous !== '' ? $previous : null,
            'guardian_name' => $guardianName,
            'guardian_phone' => $guardianPhone,
            'relationship' => $relationship,
            'enrollment_date' => $enrollmentDate,
        ];
    }

    private static function asTrimmedString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            throw new \InvalidArgumentException('Expected text, got a date value.');
        }

        if (! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }

    private static function parseStrictDate(mixed $value, string $field): Carbon
    {
        try {
            if ($value instanceof DateTimeInterface) {
                return Carbon::instance(\DateTimeImmutable::createFromInterface($value))->startOfDay();
            }

            // Excel serial day count (OpenSpout may return int/float when dates are not formatted).
            if (is_int($value) || (is_float($value) && floor($value) === $value)) {
                $serial = (int) $value;
                if ($serial < 20000 || $serial > 80000) {
                    throw new \InvalidArgumentException("{$field} must be YYYY-MM-DD.");
                }

                // Excel's day 0 is 1899-12-30 (with the 1900 leap-year bug convention).
                return Carbon::create(1899, 12, 30)->addDays($serial)->startOfDay();
            }

            $raw = trim((string) $value);
            if ($raw === '') {
                throw new \InvalidArgumentException("{$field} is required.");
            }

            // Strict ISO date only for strings (avoid 01/02/2010 ambiguity).
            // Also accept datetime strings produced by SHOULD_FORMAT_DATES (take the date part).
            if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $m) === 1) {
                $raw = $m[1];
            }

            $dob = Carbon::createFromFormat('Y-m-d', $raw);
            if ($dob === false || $dob->format('Y-m-d') !== $raw) {
                throw new \InvalidArgumentException("{$field} must be YYYY-MM-DD.");
            }

            return $dob->startOfDay();
        } catch (Throwable $e) {
            if ($e instanceof \InvalidArgumentException) {
                throw $e;
            }

            throw new \InvalidArgumentException("{$field} must be YYYY-MM-DD.");
        }
    }

    private static function parseGender(string $value): Gender
    {
        $v = strtolower(trim($value));

        return match ($v) {
            'male', 'm' => Gender::Male,
            'female', 'f' => Gender::Female,
            default => throw new \InvalidArgumentException('gender must be Male or Female.'),
        };
    }

    private static function parseRelationship(string $value): GuardianRelationship
    {
        $v = strtolower(trim($value));

        return match ($v) {
            'father' => GuardianRelationship::Father,
            'mother' => GuardianRelationship::Mother,
            'uncle' => GuardianRelationship::Uncle,
            'aunt' => GuardianRelationship::Aunt,
            'sibling' => GuardianRelationship::Sibling,
            'other' => GuardianRelationship::Other,
            default => throw new \InvalidArgumentException(
                'relationship must be Father, Mother, Uncle, Aunt, Sibling, or Other.'
            ),
        };
    }
}

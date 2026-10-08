<?php

namespace App\Services\Subscribers;

use App\Models\Stop;
use App\Models\Subscriber;
use App\Support\PhoneNumber;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use RuntimeException;
use ZipArchive;

/**
 * Reads the agency's client list (Excel .xlsx/.xls/.ods or CSV): name,
 * phone, and optionally the boarding point. Nothing is written here: each
 * line gets a status for the preview, the import writes the "new" ones.
 *
 * Columns are found by their heading ("Nom", "Téléphone", "Point de
 * montée"…, any case or accents); without headings, column A = name and
 * column B = phone.
 */
class ClientListReader
{
    public const MAX_ROWS = 2000;

    private const MAX_UNCOMPRESSED_BYTES = 50 * 1024 * 1024;

    private const NAME_HEADINGS = ['NOM', 'NOMS', 'NOMETPRENOM', 'NOMETPRENOMS', 'NOMPRENOM', 'NOMPRENOMS', 'NOMCOMPLET', 'CLIENT', 'CLIENTS', 'ABONNE', 'ABONNES', 'NOMDUCLIENT', 'NOMDELABONNE'];

    private const PHONE_HEADINGS = ['TELEPHONE', 'TEL', 'NUMERO', 'NUMERODETELEPHONE', 'CONTACT', 'PHONE', 'MOBILE', 'CELLULAIRE', 'NTELEPHONE', 'NUMTEL'];

    private const STOP_HEADINGS = ['POINTDEMONTEE', 'MONTEE', 'ARRET', 'LIEUDEMONTEE', 'POINT', 'STATION'];

    /**
     * @return list<array{line: int, name: string, phone: string|null, stop_id: int|null, stop: string|null, status: 'new'|'duplicate'|'error', message: string|null}>
     */
    public function read(string $path): array
    {
        $rows = $this->rows($path);
        [$columns, $start] = $this->columns($rows);

        $stops = Stop::query()->get(['id', 'name'])->keyBy(fn (Stop $stop) => self::key($stop->name));
        $existing = Subscriber::query()->get(['full_name', 'phone'])
            ->map(fn (Subscriber $s) => self::key($s->full_name).'|'.$s->phone)
            ->flip();
        $seen = [];
        $lines = [];

        foreach (array_slice($rows, $start, null, true) as $index => $row) {
            $name = self::clean($row[$columns['name']] ?? null);
            $rawPhone = self::clean($row[$columns['phone']] ?? null);
            $rawStop = $columns['stop'] !== null ? self::clean($row[$columns['stop']] ?? null) : null;

            if ($name === '' && $rawPhone === '' && ($rawStop ?? '') === '') {
                continue; // empty line
            }

            if (count($lines) >= self::MAX_ROWS) {
                break;
            }

            $line = ['line' => $index + 1, 'name' => $name, 'phone' => null, 'stop_id' => null, 'stop' => $rawStop ?: null, 'status' => 'new', 'message' => null];

            if (mb_strlen($name) < 2) {
                $lines[] = ['status' => 'error', 'message' => 'Nom manquant.'] + $line;

                continue;
            }

            if ($rawPhone !== '') {
                $phone = PhoneNumber::normalize(self::phoneDigits($rawPhone));
                if ($phone === null) {
                    $lines[] = ['status' => 'error', 'message' => 'Numéro « '.$rawPhone.' » invalide (10 chiffres attendus, ex. 07 08 26 44 21).'] + $line;

                    continue;
                }
                $line['phone'] = $phone;
            }

            if ($rawStop) {
                $stop = $stops->get(self::key($rawStop));
                if ($stop === null) {
                    $lines[] = ['status' => 'error', 'message' => 'Point de montée « '.$rawStop.' » inconnu. Ajoutez-le dans Lignes, zones et trajets, ou laissez la case vide.'] + $line;

                    continue;
                }
                $line['stop_id'] = $stop->id;
                $line['stop'] = $stop->name;
            }

            $key = self::key($name).'|'.$line['phone'];
            if ($existing->has($key)) {
                $line['status'] = 'duplicate';
                $line['message'] = 'Déjà enregistré (même nom et même numéro).';
            } elseif (isset($seen[$key])) {
                $line['status'] = 'duplicate';
                $line['message'] = 'En double dans le fichier (ligne '.$seen[$key].').';
            } else {
                $seen[$key] = $line['line'];
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function rows(string $path): array
    {
        self::guardAgainstZipBomb($path);

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        // Only the useful part of the sheet is loaded (a huge file cannot exhaust memory).
        $reader->setReadFilter(new class(self::MAX_ROWS + 20) implements IReadFilter
        {
            public function __construct(private readonly int $maxRow) {}

            /**
             * @param  string  $columnAddress
             * @param  int  $row
             * @param  string  $worksheetName
             */
            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return $row <= $this->maxRow && strlen($columnAddress) === 1 && $columnAddress <= 'J';
            }
        });

        if ($reader instanceof Csv) {
            $reader->setInputEncoding(Csv::GUESS_ENCODING);
            $reader->setFallbackEncoding('Windows-1252');
            $reader->setDelimiter(self::csvDelimiter($path));
        }

        // Raw values, formulas never evaluated; 0708264421 stored as a number keeps its digits (phoneDigits).
        return array_values($reader->load($path)->getActiveSheet()->toArray(null, false, false, false));
    }

    /**
     * An .xlsx/.ods is a zip: refuse one that would expand to more than 50 MB.
     */
    private static function guardAgainstZipBomb(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return; // not a zip (CSV, old .xls): read as is
        }

        $size = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $size += (int) ($zip->statIndex($i)['size'] ?? 0);
        }
        $zip->close();

        if ($size > self::MAX_UNCOMPRESSED_BYTES) {
            throw new RuntimeException('File too large once uncompressed.');
        }
    }

    /**
     * Columns of name, phone and stop, and the first data line.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{0: array{name: int, phone: int, stop: int|null}, 1: int}
     */
    private function columns(array $rows): array
    {
        foreach (array_slice($rows, 0, 10, true) as $index => $row) {
            $keys = array_map(fn ($cell) => self::key((string) $cell), $row);
            $name = self::find($keys, self::NAME_HEADINGS);
            $phone = self::find($keys, self::PHONE_HEADINGS);

            if ($name !== null && $phone !== null) {
                return [['name' => $name, 'phone' => $phone, 'stop' => self::find($keys, self::STOP_HEADINGS)], $index + 1];
            }
        }

        return [['name' => 0, 'phone' => 1, 'stop' => null], 0];
    }

    /**
     * @param  array<int, string>  $keys
     * @param  list<string>  $headings
     */
    private static function find(array $keys, array $headings): ?int
    {
        foreach ($keys as $column => $key) {
            if ($key !== '' && (in_array($key, $headings, true) || collect($headings)->contains(fn ($h) => str_starts_with($key, $h) && strlen($h) >= 4))) {
                return $column;
            }
        }

        return null;
    }

    private static function csvDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        $first = $handle !== false ? (string) fgets($handle) : '';
        if ($handle !== false) {
            fclose($handle);
        }

        return substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
    }

    /**
     * Excel turns 0708264421 into the number 708264421: put the leading 0 back.
     */
    private static function phoneDigits(string $value): string
    {
        $digits = (string) preg_replace('/\D+/', '', $value);

        return strlen($digits) === 9 ? '0'.$digits : $value;
    }

    private static function clean(mixed $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }

    private static function key(string $value): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(Str::ascii($value)));
    }
}

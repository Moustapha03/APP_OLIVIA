<?php

namespace App\Console\Commands;

use App\Actions\Passes\IssuePass;
use App\Enums\FareType;
use App\Enums\PassChannel;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Stop;
use App\Models\Subscriber;
use App\Services\Receipts\ReceiptNumbers;
use App\Support\PhoneNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Imports the agency's receipts history (their Excel/CSV export):
 *
 *   "N°";"Date";"Abonné";"Téléphone";"Point de montée";"Mois";"Formule";"Paiement";"Réf.";"Montant FCFA"
 *
 * - creates the subscribers (no photo: to take at the next visit);
 * - creates paid payments with their ORIGINAL receipt number, and the
 *   subscriptions of the month; amounts kept as paid (a difference with
 *   the grid is flagged as negotiated);
 * - idempotent: a receipt number already in the database is skipped;
 * - --dry-run: checks everything, writes nothing.
 * The receipt numbering then continues after the highest imported number.
 */
class ImportReceipts extends Command
{
    protected $signature = 'receipts:import {file : CSV file (separator ;)} {--dry-run : check only, write nothing}';

    protected $description = 'Importe l’historique des reçus d’abonnement de l’agence (CSV)';

    private const MONTHS = [
        'janvier' => 1, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6,
        'juillet' => 7, 'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'decembre' => 12,
    ];

    private const FORMULAS = [
        'MATINSOIR' => FareType::Monthly,
        'MATINETSOIR' => FareType::Monthly,
        'MATINSIMPLE' => FareType::MonthlyMorning,
        'MATIN' => FareType::MonthlyMorning,
        'SOIRSIMPLE' => FareType::MonthlyEvening,
        'SOIR' => FareType::MonthlyEvening,
    ];

    private const METHODS = [
        'ESPECES' => PaymentMethod::Cash,
        'CASH' => PaymentMethod::Cash,
        'WAVE' => PaymentMethod::Wave,
        'ORANGEMONEY' => PaymentMethod::OrangeMoney,
        'OM' => PaymentMethod::OrangeMoney,
        'MTN' => PaymentMethod::MtnMomo,
        'MTNMOMO' => PaymentMethod::MtnMomo,
        'MOOV' => PaymentMethod::MoovMoney,
        'MOOVMONEY' => PaymentMethod::MoovMoney,
    ];

    public function handle(IssuePass $issuePass, ReceiptNumbers $receipts): int
    {
        $rows = $this->readCsv((string) $this->argument('file'));
        $stops = Stop::query()->with('zone')->get()->keyBy(fn (Stop $stop) => self::key($stop->name));
        $dryRun = (bool) $this->option('dry-run');

        $errors = [];
        $parsed = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2; // header = line 1

            if (trim($row['N°'] ?? '') === '' || mb_strtoupper(trim($row['Réf.'] ?? '')) === 'TOTAL') {
                continue;
            }

            try {
                $parsed[] = $this->parse($row, $stops->all()) + ['line' => $line];
            } catch (RuntimeException $e) {
                $errors[] = "Ligne {$line} ({$row['N°']}) : ".$e->getMessage();
            }
        }

        if ($errors !== []) {
            $this->error('Import refusé : corrigez ces lignes (ou ajoutez les points de montée dans l’admin).');
            foreach ($errors as $error) {
                $this->line('  - '.$error);
            }

            return self::FAILURE;
        }

        $existing = Payment::query()->whereIn('receipt_number', array_column($parsed, 'number'))->pluck('receipt_number')->all();
        $todo = array_values(array_filter($parsed, fn (array $r) => ! in_array($r['number'], $existing, true)));

        $this->table(
            ['N°', 'Abonné', 'Point de montée', 'Mois', 'Formule', 'Paiement', 'Montant', 'Grille'],
            array_map(fn (array $r) => [
                $r['number'], $r['name'], $r['stop']->name, $r['month']->translatedFormat('F Y'), $r['formula']->shortLabel(),
                $r['method']->label(), $r['amount'], $r['list_amount'] === $r['amount'] ? '=' : $r['list_amount'],
            ], $todo),
        );
        $this->info(count($todo).' reçu(s) à importer, '.count($existing).' déjà présent(s).');

        if ($dryRun || $todo === []) {
            $this->comment($dryRun ? 'Mode essai : rien n’a été enregistré.' : 'Rien à importer.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($todo, $issuePass, $receipts) {
            foreach ($todo as $row) {
                $subscriber = $this->subscriber($row);

                $payment = new Payment;
                $payment->forceFill([
                    'reference' => 'TT'.Str::upper((string) Str::ulid()),
                    'receipt_number' => $row['number'],
                    'subscriber_id' => $subscriber->id,
                    'purpose' => $row['formula'],
                    'zone_id' => $row['stop']->zone_id,
                    'stop_id' => $row['stop']->id,
                    'fare_id' => $row['fare_id'],
                    'period_month' => $row['month'],
                    'list_amount' => $row['list_amount'],
                    'amount' => $row['amount'],
                    'discount_reason' => $row['list_amount'] !== $row['amount'] ? 'Montant de l’historique importé (à confirmer avec l’agence)' : null,
                    'currency' => 'XOF',
                    'gateway' => 'import',
                    'payment_method' => $row['method'],
                    'external_reference' => $row['reference'],
                    'status' => PaymentStatus::Succeeded,
                    'confirmed_at' => $row['date'],
                    'created_at' => $row['date'],
                    'updated_at' => now(),
                ])->save();

                $issuePass->handle($payment, PassChannel::Counter);

                if (preg_match('/^(BA|TJ)-(\d{4})-(\d+)$/', $row['number'], $m) === 1) {
                    $receipts->bumpTo($m[1], (int) $m[2], (int) $m[3]);
                }
            }

            activity('audit')->event('receipts.imported')
                ->withProperties(['count' => count($todo), 'numbers' => array_column($todo, 'number')])
                ->log('Import de l’historique des reçus');
        });

        $this->info(count($todo).' reçu(s) importé(s). Pensez à prendre la photo des abonnés à leur prochain passage.');

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, string>>
     */
    private function readCsv(string $file): array
    {
        if (! is_readable($file)) {
            throw new RuntimeException("Fichier introuvable : {$file}");
        }

        $content = (string) file_get_contents($file);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content; // BOM

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252'); // Excel "CSV (;)"
        }

        $lines = preg_split('/\r\n|\n|\r/', trim($content)) ?: [];
        $header = array_map('trim', str_getcsv((string) array_shift($lines), ';'));

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line, ';');
            $rows[] = array_combine($header, array_pad(array_map('trim', $cells), count($header), ''));
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, Stop>  $stops
     * @return array<string, mixed>
     */
    private function parse(array $row, array $stops): array
    {
        $stop = $stops[self::key($row['Point de montée'] ?? '')] ?? null;
        if ($stop === null) {
            throw new RuntimeException("point de montée inconnu « {$row['Point de montée']} »");
        }

        $formula = self::FORMULAS[self::key($row['Formule'] ?? '')] ?? null;
        if ($formula === null) {
            throw new RuntimeException("formule inconnue « {$row['Formule']} »");
        }

        $method = self::METHODS[self::key($row['Paiement'] ?? '')] ?? null;
        if ($method === null) {
            throw new RuntimeException("paiement inconnu « {$row['Paiement']} »");
        }

        $date = Carbon::createFromFormat('d/m/Y', $row['Date'] ?? '');
        if ($date === null) {
            throw new RuntimeException("date invalide « {$row['Date']} »");
        }

        $month = $this->month($row['Mois'] ?? '');
        $amount = (int) preg_replace('/\D+/', '', $row['Montant FCFA'] ?? '');
        if ($amount <= 0) {
            throw new RuntimeException('montant invalide');
        }

        $fare = $stop->zone->fareOn($formula, $month);
        $reference = trim($row['Réf.'] ?? '');

        return [
            'number' => trim($row['N°']),
            'date' => $date->setTime(12, 0),
            'name' => preg_replace('/\s+/', ' ', trim($row['Abonné'] ?? '')),
            'phone' => PhoneNumber::normalize($row['Téléphone'] ?? null),
            'stop' => $stop,
            'month' => $month,
            'formula' => $formula,
            'method' => $method,
            'reference' => $reference === '' ? null : strtoupper($reference),
            'amount' => $amount,
            'list_amount' => $fare->amount ?? $amount,
            'fare_id' => $fare?->id,
        ];
    }

    private function month(string $value): Carbon
    {
        if (preg_match('/^\s*(\S+)\s+(\d{4})\s*$/u', Str::ascii(mb_strtolower($value)), $m) !== 1 || ! isset(self::MONTHS[$m[1]])) {
            throw new RuntimeException("mois invalide « {$value} »");
        }

        return Carbon::create((int) $m[2], self::MONTHS[$m[1]], 1)->startOfDay();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function subscriber(array $row): Subscriber
    {
        $existing = Subscriber::query()
            ->where('phone', $row['phone'])
            ->get()
            ->first(fn (Subscriber $s) => self::key($s->full_name) === self::key($row['name']));

        if ($existing !== null) {
            return $existing;
        }

        $subscriber = new Subscriber(['full_name' => $row['name'], 'phone' => $row['phone'], 'stop_id' => $row['stop']->id]);
        $subscriber->save();

        return $subscriber;
    }

    /**
     * "Carrefour AÉROPORT" → "CARREFOURAEROPORT", "Matin & soir" → "MATINSOIR".
     */
    private static function key(string $value): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(Str::ascii($value)));
    }
}

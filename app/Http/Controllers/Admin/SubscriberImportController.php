<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use App\Services\Subscribers\ClientListReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Client list from Excel, in two steps: upload → preview (new, already
 * known, errors with their line) → confirm. Only the "new" lines are
 * created; the file itself is never stored.
 */
class SubscriberImportController extends Controller
{
    private const SESSION = 'client_import';

    public function create(): View
    {
        Gate::authorize('create', Subscriber::class);

        return view('admin.subscribers.import', ['lines' => null]);
    }

    public function preview(Request $request, ClientListReader $reader): View
    {
        Gate::authorize('create', Subscriber::class);

        $request->validate([
            // An .xlsx is a zip for PHP: the extension is checked here, the content by the reader.
            'file' => ['required', 'file', 'max:5120', 'extensions:xlsx,xls,ods,csv,txt'],
        ], [
            'file.required' => 'Choisissez le fichier Excel de la liste des clients.',
            'file.extensions' => 'Le fichier doit être un fichier Excel (.xlsx, .xls) ou CSV.',
            'file.max' => 'Le fichier est trop lourd (5 Mo maximum).',
        ]);

        try {
            $lines = $reader->read((string) $request->file('file')?->getRealPath());
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => 'Ce fichier ne peut pas être lu. Enregistrez-le en .xlsx depuis Excel et réessayez.']);
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['file' => 'Aucun client trouvé dans ce fichier (colonnes attendues : Nom, Téléphone).']);
        }

        // Only what will be created is kept, until the confirmation.
        $token = (string) Str::uuid();
        $request->session()->put(self::SESSION, [
            'token' => $token,
            'file' => (string) $request->file('file')?->getClientOriginalName(),
            'rows' => array_values(array_map(
                fn (array $line) => ['name' => $line['name'], 'phone' => $line['phone'], 'stop_id' => $line['stop_id']],
                array_filter($lines, fn (array $line) => $line['status'] === 'new'),
            )),
        ]);

        return view('admin.subscribers.import', [
            'lines' => $lines,
            'token' => $token,
            'file' => $request->file('file')?->getClientOriginalName(),
            'counts' => array_count_values(array_column($lines, 'status')) + ['new' => 0, 'duplicate' => 0, 'error' => 0],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Subscriber::class);

        $import = $request->session()->pull(self::SESSION);
        abort_unless(is_array($import) && hash_equals((string) ($import['token'] ?? ''), $request->string('token')->toString()), 419, 'Import expiré : renvoyez le fichier.');

        $created = DB::transaction(function () use ($import, $request) {
            $created = 0;

            foreach ($import['rows'] as $row) {
                // Added meanwhile (another agent, double click): skipped.
                $exists = Subscriber::query()->where('full_name', $row['name'])->where('phone', $row['phone'])->exists();
                if ($exists) {
                    continue;
                }

                Subscriber::query()->create(['full_name' => $row['name'], 'phone' => $row['phone'], 'stop_id' => $row['stop_id']]);
                $created++;
            }

            activity('subscribers')->causedBy($request->user())->event('subscribers.imported')
                ->withProperties(['file' => $import['file'], 'created' => $created])
                ->log('Import de la liste des clients');

            return $created;
        });

        return redirect()->route('admin.subscribers.index')->with('success', $created.' client(s) ajouté(s) depuis « '.$import['file'].' ». Pensez à prendre leur photo à leur prochain passage.');
    }

    /**
     * Empty list to fill in Excel. Phone column as text: Excel keeps the leading 0.
     */
    public function template(): StreamedResponse
    {
        Gate::authorize('create', Subscriber::class);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Clients');
        $sheet->fromArray([['Nom et prénom', 'Téléphone', 'Point de montée (facultatif)'], ['KOUASSI RODOLPHE', '07 08 26 44 21', 'Zouglou']]);
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $sheet->getStyle('B:B')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        foreach (['A' => 32, 'B' => 18, 'C' => 30] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        return response()->streamDownload(fn () => (new Xlsx($spreadsheet))->save('php://output'), 'modele-liste-clients.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}

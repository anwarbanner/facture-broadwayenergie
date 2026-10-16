<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Services\Import\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Excel import: mass import of products / services / categories / contacts (template -> preview -> confirm),
 * and lines of a document form filled from an Excel file.
 */
class ImportController extends Controller
{
    /** Where to go once the import is done. */
    private const LISTS = [
        'products' => 'erp.products.index',
        'services' => 'erp.services.index',
        'categories' => 'erp.categories.index',
        'contacts' => 'erp.contacts.index',
    ];

    private const FILE_RULES = ['required', 'file', 'mimes:xlsx,xls,csv,ods,txt', 'max:10240'];

    public function __construct(private ImportService $imports)
    {
    }

    public function index()
    {
        return view('erp.imports.index', ['types' => ImportService::TYPES]);
    }

    public function template(string $type)
    {
        abort_unless(isset(ImportService::TYPES[$type]), 404);

        return $this->xlsx($this->imports->template($type), 'modele-import-' . $type . '.xlsx');
    }

    public function preview(Request $request, string $type)
    {
        abort_unless(isset(ImportService::TYPES[$type]), 404);
        $request->validate(['file' => self::FILE_RULES], [
            'file.required' => 'Choisissez le fichier Excel a importer.',
            'file.mimes' => 'Le fichier doit etre un Excel (.xlsx, .xls), un CSV ou un .ods.',
            'file.max' => 'Le fichier ne doit pas depasser 10 Mo.',
        ]);

        try {
            $read = $this->imports->read($request->file('file')->getRealPath(), ImportService::TYPES[$type][1]);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'Ce fichier ne peut pas etre lu. Enregistrez-le au format Excel (.xlsx) et reessayez.']);
        }
        if ($read['missing']) {
            return back()->withErrors(['file' => 'Colonne(s) obligatoire(s) absente(s) : ' . implode(', ', $read['missing']) . '. Partez du modele a telecharger.']);
        }
        if (!$read['rows']) {
            return back()->withErrors(['file' => 'Le fichier ne contient aucune ligne a importer (sous la ligne des titres).']);
        }

        $rows = $this->imports->analyse($type, $read['rows']);
        $token = (string) Str::uuid();
        Cache::put($this->cacheKey($token), ['type' => $type, 'rows' => $rows, 'file' => $request->file('file')->getClientOriginalName()], now()->addHour());

        return view('erp.imports.preview', [
            'type' => $type,
            'label' => ImportService::TYPES[$type][0],
            'token' => $token,
            'rows' => $rows,
            'file' => $request->file('file')->getClientOriginalName(),
            'unknown' => $read['unknown'],
            'counts' => collect($rows)->countBy('action')->all() + ['create' => 0, 'update' => 0, 'error' => 0],
        ]);
    }

    public function confirm(Request $request, string $type)
    {
        $data = $request->validate(['token' => ['required', 'uuid']]);
        $pending = Cache::pull($this->cacheKey($data['token']));
        if (!$pending || $pending['type'] !== $type) {
            return redirect()->route('erp.imports.index')->withErrors(['file' => 'Cet apercu a expire (1 heure). Envoyez le fichier a nouveau.']);
        }

        $counts = $this->imports->apply($type, $pending['rows']);
        $label = mb_strtolower(ImportService::TYPES[$type][0]);

        return redirect()->route(self::LISTS[$type])->with('success', "Import termine ({$pending['file']}) : {$counts['created']} {$label} cree(s), {$counts['updated']} mis a jour"
            . ($counts['skipped'] ? ", {$counts['skipped']} ligne(s) ignoree(s) a cause d'erreurs." : '.'));
    }

    /** Document forms: "Importer des lignes (Excel)" -> JSON lines for erp-documents.js. */
    public function lines(Request $request)
    {
        $data = $request->validate(['file' => self::FILE_RULES, 'side' => ['nullable', 'in:sales,purchase']]);

        try {
            return response()->json($this->imports->documentLines($request->file('file')->getRealPath(), $data['side'] ?? 'sales'));
        } catch (\Throwable $e) {
            return response()->json(['lines' => [], 'warnings' => ['Ce fichier ne peut pas etre lu. Enregistrez-le au format Excel (.xlsx).']], 422);
        }
    }

    public function linesTemplate()
    {
        return $this->xlsx($this->imports->linesTemplate(), 'modele-lignes-document.xlsx');
    }

    private function cacheKey(string $token): string
    {
        return 'import:' . Auth::id() . ':' . $token;
    }

    private function xlsx(Spreadsheet $spreadsheet, string $file)
    {
        return response()->streamDownload(fn () => (new Xlsx($spreadsheet))->save('php://output'), $file,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}

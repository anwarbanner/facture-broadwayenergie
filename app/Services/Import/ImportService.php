<?php

namespace App\Services\Import;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Products;
use App\Models\Service;
use App\Services\Stock\StockService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Mass import from Excel / CSV (products, services, categories, contacts).
 *
 * 1. template(): an .xlsx with the expected columns, two examples and a help sheet;
 * 2. analyse(): reads the file, checks every row and says what will happen (create / update / error);
 * 3. apply(): writes the valid rows in one transaction.
 * Existing records are updated, not duplicated: products by reference (or name), services and categories by
 * name, contacts by type + company name. Stock goes through StockService (initial stock of new products only).
 */
class ImportService
{
    public const MAX_ROWS = 5000;

    /** type => [label, [key => [column title, required, help, example 1, example 2]]] */
    public const TYPES = [
        'products' => ['Produits', [
            'reference' => ['Reference', false, 'Code unique du produit ; sert a retrouver un produit deja cree', 'POMPE-100', 'VANNE-25'],
            'name' => ['Nom', true, 'Nom du produit', 'Pompe 100 L/min', 'Vanne 25 mm'],
            'designation' => ['Designation', false, 'Libelle detaille (facultatif)', 'Pompe centrifuge inox', ''],
            'category' => ['Categorie', false, 'Nom de la categorie ; creee si elle n\'existe pas', 'Pompes', 'Robinetterie'],
            'sale_price' => ['Prix de vente HT', true, 'Nombre, ex. 1250,50', '1250', '85,5'],
            'cost_price' => ['Prix d\'achat HT', false, 'Nombre (0 si vide)', '980', '52'],
            'unit' => ['Unite', false, 'piece, m, kg, l... (piece si vide)', 'piece', 'piece'],
            'stock' => ['Stock initial', false, 'Pour un NOUVEAU produit seulement (le stock d\'un produit existant ne change pas)', '10', '40'],
            'min_stock' => ['Stock minimum', false, 'Seuil d\'alerte de stock bas', '2', '5'],
            'status' => ['Statut', false, 'actif ou inactif (actif si vide)', 'actif', 'actif'],
        ]],
        'services' => ['Services', [
            'name' => ['Nom', true, 'Nom du service ; sert a retrouver un service deja cree', 'Installation', 'Maintenance annuelle'],
            'price' => ['Prix HT', true, 'Nombre, ex. 350', '350', '1200'],
            'hours' => ['Duree (heures)', false, 'Nombre d\'heures (1 si vide)', '2', '8'],
            'description' => ['Description', false, 'Texte libre', 'Pose et mise en service', ''],
            'status' => ['Statut', false, 'actif ou inactif (actif si vide)', 'actif', 'actif'],
        ]],
        'categories' => ['Categories', [
            'name' => ['Nom', true, 'Nom de la categorie ; sert a retrouver une categorie deja creee', 'Pompes', 'Pompes immergees'],
            'parent' => ['Categorie parente', false, 'Nom d\'une categorie existante ou presente plus haut dans le fichier', '', 'Pompes'],
            'description' => ['Description', false, 'Texte libre', 'Toutes les pompes', ''],
            'active' => ['Active', false, 'oui ou non (oui si vide)', 'oui', 'oui'],
        ]],
        'contacts' => ['Contacts', [
            'type' => ['Type', true, 'client ou fournisseur', 'client', 'fournisseur'],
            'company' => ['Societe', true, 'Nom de la societe ou du client ; avec le type, sert a retrouver un contact deja cree', 'Hotel Atlas', 'Grossiste Nord'],
            'person' => ['Contact', false, 'Nom de la personne a contacter', 'Karim Alami', ''],
            'email' => ['E-mail', false, '', 'contact@hotel-atlas.ma', ''],
            'phone' => ['Telephone', false, '', '0522 00 00 00', ''],
            'mobile' => ['Mobile', false, '', '0661 00 00 00', ''],
            'address' => ['Adresse', false, '', '12 bd Zerktouni', ''],
            'city' => ['Ville', false, '', 'Casablanca', 'Tanger'],
            'postal_code' => ['Code postal', false, '', '20000', ''],
            'country' => ['Pays', false, '', 'Maroc', 'Maroc'],
            'tax_id' => ['ICE', false, 'Identifiant fiscal', '001234567000089', ''],
            'payment_terms' => ['Conditions de paiement', false, '', '30 jours', ''],
            'credit_limit' => ['Plafond de credit', false, 'Nombre (0 si vide)', '50000', ''],
            'status' => ['Statut', false, 'actif ou inactif (actif si vide)', 'actif', 'actif'],
        ]],
    ];

    public function __construct(private StockService $stock)
    {
    }

    // ---- Template ---------------------------------------------------------------------------------------

    public function template(string $type): Spreadsheet
    {
        [$label, $columns] = self::TYPES[$type];
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->setTitle(mb_substr($label, 0, 31));
        $titles = array_map(fn ($c) => $c[0] . ($c[1] ? ' *' : ''), array_values($columns));
        $sheet->fromArray($titles, null, 'A1');
        $sheet->fromArray(array_map(fn ($c) => $c[3], array_values($columns)), null, 'A2');
        $sheet->fromArray(array_map(fn ($c) => $c[4], array_values($columns)), null, 'A3');
        $last = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$last}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ltrim(\App\Support\Branding::colorPrimary(), '#')]],
        ]);
        foreach (range(1, count($columns)) as $i) {
            $sheet->getColumnDimensionByColumn($i)->setWidth(22);
        }
        $sheet->freezePane('A2');

        $help = $spreadsheet->createSheet()->setTitle('Aide');
        $help->fromArray(['Colonne', 'Obligatoire', 'Explication'], null, 'A1');
        $row = 1;
        foreach ($columns as [$title, $required, $explanation]) {
            $help->fromArray([$title, $required ? 'oui' : 'non', $explanation], null, 'A' . (++$row));
        }
        $help->setCellValue('A' . ($row + 2), 'Remplacez les 2 lignes d\'exemple par vos donnees. Gardez la 1re ligne (titres). Les colonnes marquees * sont obligatoires.');
        $help->getStyle('A1:C1')->getFont()->setBold(true);
        $help->getColumnDimension('A')->setWidth(26);
        $help->getColumnDimension('B')->setWidth(12);
        $help->getColumnDimension('C')->setWidth(90);
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    // ---- Reading ----------------------------------------------------------------------------------------

    /**
     * Rows of the first sheet as [column key => value], matched on the column titles (accents, case,
     * "*" and spaces ignored). Empty rows are skipped; the Excel row number is kept for the messages.
     *
     * @return array{rows: array<int, array>, unknown: array<int, string>, missing: array<int, string>}
     */
    public function read(string $path, array $columns): array
    {
        $sheet = IOFactory::load($path)->getSheet(0);
        $data = $sheet->toArray(null, true, false, false);
        $header = array_map(fn ($title) => $this->normalize((string) $title), $data[0] ?? []);

        $lookup = [];
        foreach ($columns as $key => $definition) {
            $lookup[$this->normalize($definition[0])] = $key;
            $lookup[$this->normalize($key)] = $key;
        }
        $map = [];
        $unknown = [];
        foreach ($header as $index => $title) {
            if ($title === '') {
                continue;
            }
            isset($lookup[$title]) ? $map[$index] = $lookup[$title] : $unknown[] = (string) ($data[0][$index] ?? '');
        }
        $missing = collect($columns)->filter(fn ($c, $key) => $c[1] && !in_array($key, $map, true))->map(fn ($c) => $c[0])->values()->all();

        $rows = [];
        foreach (array_slice($data, 1, self::MAX_ROWS, true) as $index => $cells) {
            $row = [];
            foreach ($map as $position => $key) {
                $value = $cells[$position] ?? null;
                $row[$key] = is_string($value) ? trim($value) : $value;
            }
            if (collect($row)->filter(fn ($v) => $v !== null && $v !== '')->isEmpty()) {
                continue;
            }
            $rows[$index + 1] = $row;
        }

        return ['rows' => $rows, 'unknown' => $unknown, 'missing' => $missing];
    }

    // ---- Analysis ---------------------------------------------------------------------------------------

    /**
     * What will happen to each row: ['line' => n, 'action' => create|update|error, 'label' => ..., 'errors' => [...], 'data' => [...]]
     */
    public function analyse(string $type, array $rows): array
    {
        $seen = [];
        $result = [];
        foreach ($rows as $line => $row) {
            [$data, $errors, $key] = match ($type) {
                'products' => $this->product($row),
                'services' => $this->service($row),
                'categories' => $this->category($row),
                'contacts' => $this->contact($row),
            };
            if ($key !== null && isset($seen[$key])) {
                $errors[] = 'Deja present a la ligne ' . $seen[$key] . ' du fichier';
            }
            if ($key !== null) {
                $seen[$key] ??= $line;
            }
            $result[] = [
                'line' => $line,
                'label' => $data['label'] ?? '-',
                'action' => $errors ? 'error' : ($this->existing($type, $data) ? 'update' : 'create'),
                'errors' => $errors,
                'data' => $data,
            ];
        }

        return $result;
    }

    // ---- Apply ------------------------------------------------------------------------------------------

    /** @return array{created: int, updated: int, skipped: int} */
    public function apply(string $type, array $analysed): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        DB::transaction(function () use ($type, $analysed, &$counts) {
            foreach ($analysed as $row) {
                if ($row['action'] === 'error') {
                    $counts['skipped']++;
                    continue;
                }
                $created = match ($type) {
                    'products' => $this->saveProduct($row['data']),
                    'services' => $this->saveSimple(Service::class, $row['data'], ['service_name' => $row['data']['service_name']]),
                    'categories' => $this->saveCategory($row['data']),
                    'contacts' => $this->saveSimple(Contact::class, $row['data'], ['type' => $row['data']['type'], 'company_name' => $row['data']['company_name']]),
                };
                $counts[$created ? 'created' : 'updated']++;
            }
        });

        return $counts;
    }

    // ---- Rows -------------------------------------------------------------------------------------------

    private function product(array $row): array
    {
        $errors = [];
        $name = $this->text($row['name'] ?? null);
        $name === '' && $errors[] = 'Nom obligatoire';
        $sale = $this->number($row['sale_price'] ?? null, 'Prix de vente HT', $errors, true);
        $cost = $this->number($row['cost_price'] ?? null, 'Prix d\'achat HT', $errors) ?? 0;
        $stock = $this->number($row['stock'] ?? null, 'Stock initial', $errors) ?? 0;
        $minStock = $this->number($row['min_stock'] ?? null, 'Stock minimum', $errors) ?? 0;
        $status = $this->status($row['status'] ?? null, $errors);
        $reference = $this->text($row['reference'] ?? null);

        return [[
            'label' => trim(($reference !== '' ? $reference . ' - ' : '') . $name),
            'reference' => $reference !== '' ? mb_substr($reference, 0, 100) : null,
            'product_name' => mb_substr($name, 0, 255),
            'designation' => $this->text($row['designation'] ?? null) ?: null,
            'category' => $this->text($row['category'] ?? null) ?: null,
            'sale_price_ht' => $sale,
            'cost_price_ht' => $cost,
            'unit_type' => mb_substr($this->text($row['unit'] ?? null) ?: 'piece', 0, 30),
            'initial_stock' => $stock,
            'min_stock_alert_level' => $minStock,
            'status' => $status,
        ], $errors, $reference !== '' ? 'ref:' . mb_strtolower($reference) : ($name !== '' ? 'name:' . mb_strtolower($name) : null)];
    }

    private function service(array $row): array
    {
        $errors = [];
        $name = $this->text($row['name'] ?? null);
        $name === '' && $errors[] = 'Nom obligatoire';
        $price = $this->number($row['price'] ?? null, 'Prix HT', $errors, true);
        $hours = $this->number($row['hours'] ?? null, 'Duree', $errors) ?: 1;

        return [[
            'label' => $name,
            'service_name' => mb_substr($name, 0, 255),
            'service_price' => $price,
            'hours_count' => max($hours, 0.01),
            'description' => $this->text($row['description'] ?? null) ?: null,
            'status' => $this->status($row['status'] ?? null, $errors),
        ], $errors, $name !== '' ? mb_strtolower($name) : null];
    }

    private function category(array $row): array
    {
        $errors = [];
        $name = $this->text($row['name'] ?? null);
        $name === '' && $errors[] = 'Nom obligatoire';
        $active = mb_strtolower($this->text($row['active'] ?? null));
        if (!in_array($active, ['', 'oui', 'non', '1', '0', 'yes', 'no', 'actif', 'inactif'], true)) {
            $errors[] = 'Active : oui ou non';
        }

        return [[
            'label' => $name,
            'name' => mb_substr($name, 0, 255),
            'parent' => $this->text($row['parent'] ?? null) ?: null,
            'description' => $this->text($row['description'] ?? null) ?: null,
            'is_active' => !in_array($active, ['non', '0', 'no', 'inactif'], true),
        ], $errors, $name !== '' ? mb_strtolower($name) : null];
    }

    private function contact(array $row): array
    {
        $errors = [];
        $type = match (mb_strtolower(Str::ascii($this->text($row['type'] ?? null)))) {
            'client', 'clients' => 'client',
            'fournisseur', 'fournisseurs', 'supplier' => 'supplier',
            default => null,
        };
        $type === null && $errors[] = 'Type : client ou fournisseur';
        $company = $this->text($row['company'] ?? null);
        $company === '' && $errors[] = 'Societe obligatoire';
        $email = $this->text($row['email'] ?? null);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail invalide';
        }

        return [[
            'label' => $company . ($type ? ' (' . ($type === 'client' ? 'client' : 'fournisseur') . ')' : ''),
            'type' => $type,
            'company_name' => mb_substr($company, 0, 255),
            'contact_person_name' => $this->text($row['person'] ?? null) ?: null,
            'email' => $email ?: null,
            'phone' => $this->text($row['phone'] ?? null) ?: null,
            'mobile' => $this->text($row['mobile'] ?? null) ?: null,
            'address' => $this->text($row['address'] ?? null) ?: null,
            'city' => $this->text($row['city'] ?? null) ?: null,
            'postal_code' => mb_substr($this->text($row['postal_code'] ?? null), 0, 30) ?: null,
            'country' => $this->text($row['country'] ?? null) ?: null,
            'tax_id' => $this->text($row['tax_id'] ?? null) ?: null,
            'payment_terms' => $this->text($row['payment_terms'] ?? null) ?: null,
            'credit_limit' => $this->number($row['credit_limit'] ?? null, 'Plafond de credit', $errors) ?? 0,
            'status' => $this->status($row['status'] ?? null, $errors),
        ], $errors, $type && $company !== '' ? $type . ':' . mb_strtolower($company) : null];
    }

    // ---- Persistence ------------------------------------------------------------------------------------

    private function existing(string $type, array $data): ?object
    {
        return match ($type) {
            'products' => $this->findProduct($data),
            'services' => Service::whereRaw('LOWER(service_name) = ?', [mb_strtolower($data['service_name'])])->first(),
            'categories' => Category::whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])->first(),
            'contacts' => $data['type'] ? Contact::where('type', $data['type'])->whereRaw('LOWER(company_name) = ?', [mb_strtolower($data['company_name'])])->first() : null,
        };
    }

    private function findProduct(array $data): ?Products
    {
        if ($data['reference']) {
            return Products::whereRaw('LOWER(reference) = ?', [mb_strtolower($data['reference'])])->first();
        }

        return Products::whereRaw('LOWER(product_name) = ?', [mb_strtolower($data['product_name'])])->first();
    }

    private function saveProduct(array $data): bool
    {
        $categoryId = null;
        if ($data['category']) {
            $categoryId = (Category::whereRaw('LOWER(name) = ?', [mb_strtolower($data['category'])])->first()
                ?? Category::create(['name' => $data['category'], 'is_active' => true, 'created_by' => Auth::id(), 'updated_by' => Auth::id()]))->id;
        }
        $attributes = [
            'reference' => $data['reference'],
            'product_name' => $data['product_name'],
            'designation' => $data['designation'],
            'category_id' => $categoryId,
            'sale_price_ht' => $data['sale_price_ht'],
            'cost_price_ht' => $data['cost_price_ht'],
            'unit_type' => $data['unit_type'],
            'min_stock_alert_level' => $data['min_stock_alert_level'],
            'status' => $data['status'],
            'updated_by' => Auth::id(),
        ];

        if ($product = $this->findProduct($data)) {
            $product->update(array_filter($attributes, fn ($v, $k) => $v !== null || !in_array($k, ['reference', 'designation', 'category_id'], true), ARRAY_FILTER_USE_BOTH));

            return false;
        }

        // Stock is never written directly: the initial quantity is a stock movement.
        $product = Products::create($attributes + ['type' => 'product', 'stock_quantity' => 0, 'created_by' => Auth::id()]);
        if ($data['initial_stock'] > 0) {
            $this->stock->adjustTo($product, (float) $data['initial_stock'], 'IMPORT', 'Stock initial (import Excel)');
        }

        return true;
    }

    private function saveCategory(array $data): bool
    {
        $parentId = $data['parent']
            ? Category::whereRaw('LOWER(name) = ?', [mb_strtolower($data['parent'])])->value('id')
            : null;
        $attributes = ['name' => $data['name'], 'description' => $data['description'], 'is_active' => $data['is_active'], 'parent_category_id' => $parentId, 'updated_by' => Auth::id()];

        if ($category = Category::whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])->first()) {
            $category->update($attributes);

            return false;
        }
        Category::create($attributes + ['created_by' => Auth::id()]);

        return true;
    }

    /** @param class-string $model */
    private function saveSimple(string $model, array $data, array $match): bool
    {
        unset($data['label']);
        $query = $model::query();
        foreach ($match as $column => $value) {
            $column === 'type' ? $query->where($column, $value) : $query->whereRaw("LOWER({$column}) = ?", [mb_strtolower($value)]);
        }
        if ($existing = $query->first()) {
            $existing->update($data + ['updated_by' => Auth::id()]);

            return false;
        }
        $model::create($data + ['created_by' => Auth::id(), 'updated_by' => Auth::id()]);

        return true;
    }

    // ---- Document lines (fill a quote / order / invoice / delivery from Excel) -------------------------

    public const LINE_COLUMNS = [
        'article' => ['Article', false, "Reference ou nom d'un produit / service du catalogue (vide = article libre)", 'POMPE-100', ''],
        'designation' => ['Designation', false, "Texte de la ligne si l'article n'est pas dans le catalogue", '', 'Installation sur site'],
        'quantity' => ['Quantite', true, 'Nombre superieur a 0', '2', '1'],
        'price' => ['Prix unitaire HT', false, 'Vide = prix du catalogue', '', '350'],
    ];

    /**
     * Lines ready for the document form: [mode, product_id, service_id, name, quantity, price] + warnings.
     *
     * @param string $side 'sales' | 'purchase' (catalogue price used when the file has none)
     */
    public function documentLines(string $path, string $side): array
    {
        $read = $this->read($path, self::LINE_COLUMNS);
        $lines = [];
        $warnings = $read['missing'] ? ['Colonne obligatoire absente : ' . implode(', ', $read['missing'])] : [];

        foreach ($read['rows'] as $excelLine => $row) {
            $errors = [];
            $article = $this->text($row['article'] ?? null);
            $designation = $this->text($row['designation'] ?? null);
            $quantity = $this->number($row['quantity'] ?? null, 'Quantite', $errors, true);
            $price = $this->number($row['price'] ?? null, 'Prix', $errors);
            if ($quantity !== null && $quantity <= 0) {
                $errors[] = 'Quantite superieure a 0 attendue';
            }
            if ($article === '' && $designation === '') {
                $errors[] = 'Article ou designation obligatoire';
            }
            if ($errors) {
                $warnings[] = 'Ligne ' . $excelLine . ' ignoree : ' . implode(', ', $errors);
                continue;
            }

            $product = $article !== '' ? (Products::active()->whereRaw('LOWER(reference) = ?', [mb_strtolower($article)])->first()
                ?? Products::active()->whereRaw('LOWER(product_name) = ?', [mb_strtolower($article)])->first()) : null;
            $service = !$product && $article !== '' ? Service::active()->whereRaw('LOWER(service_name) = ?', [mb_strtolower($article)])->first() : null;

            if ($product) {
                $lines[] = ['mode' => 'product', 'product_id' => $product->id, 'service_id' => null, 'name' => null, 'quantity' => $quantity,
                    'price' => $price ?? (float) ($side === 'purchase' ? $product->cost_price_ht : $product->sale_price_ht), 'label' => $product->product_name];
            } elseif ($service) {
                $lines[] = ['mode' => 'service', 'product_id' => null, 'service_id' => $service->id, 'name' => null, 'quantity' => $quantity,
                    'price' => $price ?? (float) $service->service_price, 'label' => $service->service_name];
            } else {
                $name = $designation !== '' ? $designation : $article;
                if ($article !== '') {
                    $warnings[] = 'Ligne ' . $excelLine . ' : "' . $article . '" introuvable dans le catalogue, ajoute comme article manuel';
                }
                $lines[] = ['mode' => 'manual', 'product_id' => null, 'service_id' => null, 'name' => mb_substr($name, 0, 255), 'quantity' => $quantity,
                    'price' => $price ?? 0.0, 'label' => $name];
            }
        }

        return ['lines' => $lines, 'warnings' => $warnings];
    }

    public function linesTemplate(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Lignes');
        $columns = array_values(self::LINE_COLUMNS);
        $sheet->fromArray(array_map(fn ($c) => $c[0] . ($c[1] ? ' *' : ''), $columns), null, 'A1');
        $sheet->fromArray(array_map(fn ($c) => $c[3], $columns), null, 'A2');
        $sheet->fromArray(array_map(fn ($c) => $c[4], $columns), null, 'A3');
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ltrim(\App\Support\Branding::colorPrimary(), '#')]],
        ]);
        foreach (['A' => 24, 'B' => 40, 'C' => 12, 'D' => 18] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->setCellValue('A5', 'Article : reference ou nom du catalogue. Sinon laissez vide et ecrivez la designation. Prix vide = prix du catalogue.');

        return $spreadsheet;
    }

    // ---- Values -----------------------------------------------------------------------------------------

    public function normalize(string $title): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower(Str::ascii(str_replace('*', '', $title))));
    }

    private function text(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    /** "1 250,50", "1250.5", 1250.5 -> 1250.5; null when empty. */
    public function number(mixed $value, string $label, array &$errors, bool $required = false): ?float
    {
        if ($value === null || $value === '') {
            if ($required) {
                $errors[] = $label . ' obligatoire';
            }

            return $required ? 0.0 : null;
        }
        if (is_int($value) || is_float($value)) {
            $number = (float) $value;
        } else {
            $clean = str_replace([' ', "\u{00A0}", "\u{202F}"], '', (string) $value);
            $clean = str_contains($clean, ',') && str_contains($clean, '.') ? str_replace('.', '', $clean) : $clean;
            $clean = str_replace(',', '.', $clean);
            if (!is_numeric($clean)) {
                $errors[] = $label . ' : "' . $value . '" n\'est pas un nombre';

                return 0.0;
            }
            $number = (float) $clean;
        }
        if ($number < 0) {
            $errors[] = $label . ' ne peut pas etre negatif';
        }

        return round($number, 3);
    }

    private function status(mixed $value, array &$errors): string
    {
        $status = mb_strtolower(Str::ascii($this->text($value)));

        return match ($status) {
            '', 'actif', 'active', 'oui', '1' => 'active',
            'inactif', 'inactive', 'non', '0' => 'inactive',
            default => (function () use ($value, &$errors) {
                $errors[] = 'Statut : actif ou inactif (et non "' . $value . '")';

                return 'active';
            })(),
        };
    }
}

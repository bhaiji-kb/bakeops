<?php

namespace App\Services;

use App\Models\Product;
use App\Models\RecipeItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class RecipeWorkbookImportService
{
    /**
     * @return array{
     *     imported_products:int,
     *     imported_recipes:int,
     *     imported_recipe_items:int,
     *     skipped_sheets:int,
     *     warnings:array<int, string>
     * }
     */
    public function importFromPath(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('Recipe file not found.');
        }

        $sheets = $this->readWorkbookSheets($path);
        if (count($sheets) === 0) {
            throw new RuntimeException('No worksheet data found in the recipe file.');
        }

        $warnings = [];
        $importedProducts = 0;
        $importedRecipes = 0;
        $importedRecipeItems = 0;
        $skippedSheets = 0;

        DB::transaction(function () use (
            $sheets,
            &$warnings,
            &$importedProducts,
            &$importedRecipes,
            &$importedRecipeItems,
            &$skippedSheets
        ): void {
            $products = Product::query()->orderBy('id')->get();
            $byTypeAndName = [];
            $usedCodes = [];
            $usedLegacyCodes = [];
            foreach ($products as $product) {
                $nameKey = $this->nameKey($product->name);
                $byTypeAndName[$product->type . ':' . $nameKey] = $product;

                $code = strtoupper(trim((string) $product->code));
                if ($code !== '') {
                    $usedCodes[$code] = true;
                }

                $legacyCode = trim((string) ($product->legacy_code ?? ''));
                if ($legacyCode !== '') {
                    $usedLegacyCodes[$legacyCode] = true;
                }
            }

            $nextFgCode = $this->nextCodeNumber($usedCodes, 'FG');
            $nextRmCode = $this->nextCodeNumber($usedCodes, 'RM');
            $nextLegacyCode = $this->nextLegacyNumber($usedLegacyCodes);

            foreach ($sheets as $sheet) {
                $sheetName = trim((string) ($sheet['name'] ?? ''));
                if ($sheetName === '') {
                    $skippedSheets++;
                    continue;
                }

                if ($this->shouldSkipSheet($sheetName)) {
                    $skippedSheets++;
                    continue;
                }

                $finishedProductName = $this->normalizeFinishedProductName($sheetName);
                $finishedProduct = $this->upsertProduct(
                    $byTypeAndName,
                    $usedCodes,
                    $usedLegacyCodes,
                    $nextFgCode,
                    $nextLegacyCode,
                    'finished_good',
                    $finishedProductName,
                    'pcs',
                    null
                );
                if ($finishedProduct->wasRecentlyCreated) {
                    $importedProducts++;
                }

                $sheetRows = (array) ($sheet['rows'] ?? []);
                $recipeByIngredient = [];
                $ingredientProductsById = [];

                foreach ($sheetRows as $index => $row) {
                    $ingredientRaw = trim((string) ($row[1] ?? ''));
                    $quantityRaw = trim((string) ($row[2] ?? ''));
                    $priceRaw = trim((string) ($row[3] ?? ''));

                    if ($ingredientRaw === '' || $this->isHeaderOrSectionRow($ingredientRaw)) {
                        continue;
                    }

                    $quantity = $this->parseQuantity($quantityRaw);
                    if ($quantity === null) {
                        continue;
                    }

                    $ingredientName = $this->normalizeIngredientName($ingredientRaw);
                    if ($ingredientName === '') {
                        continue;
                    }

                    $ingredientPrice = $this->parseAmount($priceRaw);
                    $ingredientUnitCost = ($ingredientPrice > 0 && $quantity['value'] > 0)
                        ? round($ingredientPrice / $quantity['value'], 2)
                        : null;

                    $ingredientProduct = $this->upsertProduct(
                        $byTypeAndName,
                        $usedCodes,
                        $usedLegacyCodes,
                        $nextRmCode,
                        $nextLegacyCode,
                        'raw_material',
                        $ingredientName,
                        $quantity['unit'],
                        $ingredientUnitCost
                    );

                    if ($ingredientProduct->wasRecentlyCreated) {
                        $importedProducts++;
                    }

                    $ingredientId = (int) $ingredientProduct->id;
                    $existingQty = (float) ($recipeByIngredient[$ingredientId] ?? 0);
                    $recipeByIngredient[$ingredientId] = round($existingQty + $quantity['value'], 4);
                    $ingredientProductsById[$ingredientId] = $ingredientProduct;

                    if ($ingredientPrice <= 0 && $index > 0 && $quantityRaw !== '') {
                        $warnings[] = $sheetName . ': ingredient "' . $ingredientName . '" has no price, using existing product cost.';
                    }
                }

                if (count($recipeByIngredient) === 0) {
                    $warnings[] = $sheetName . ': no valid ingredient rows found.';
                    continue;
                }

                RecipeItem::query()
                    ->where('finished_product_id', $finishedProduct->id)
                    ->delete();

                foreach ($recipeByIngredient as $ingredientId => $requiredQty) {
                    RecipeItem::create([
                        'finished_product_id' => $finishedProduct->id,
                        'ingredient_product_id' => $ingredientId,
                        'quantity' => $requiredQty,
                    ]);
                    $importedRecipeItems++;
                }

                $importedRecipes++;
            }
        });

        return [
            'imported_products' => $importedProducts,
            'imported_recipes' => $importedRecipes,
            'imported_recipe_items' => $importedRecipeItems,
            'skipped_sheets' => $skippedSheets,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array<int, array{name:string, rows:array<int, array<int, string>>}>
     */
    private function readWorkbookSheets(string $path): array
    {
        $zip = new ZipArchive();
        $tempCopyPath = null;

        $opened = $zip->open($path) === true;
        if (!$opened) {
            $tempCopyPath = storage_path('app/recipe_import_' . uniqid('', true) . '.xlsx');
            if (@copy($path, $tempCopyPath)) {
                $opened = $zip->open($tempCopyPath) === true;
            }
        }

        if (!$opened) {
            if ($tempCopyPath && is_file($tempCopyPath)) {
                @unlink($tempCopyPath);
            }
            throw new RuntimeException('Unable to open the recipe workbook.');
        }

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $workbookXml = $zip->getFromName('xl/workbook.xml');
            $relationsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

            if ($workbookXml === false || $relationsXml === false) {
                throw new RuntimeException('Workbook metadata is missing.');
            }

            $workbook = $this->loadXml($workbookXml);
            $relations = $this->loadXml($relationsXml);

            $relationshipMap = [];
            $relations->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
            $relationNodes = $relations->xpath('//r:Relationship') ?: [];
            foreach ($relationNodes as $relationship) {
                $id = (string) ($relationship['Id'] ?? '');
                $target = (string) ($relationship['Target'] ?? '');
                if ($id !== '' && $target !== '') {
                    $relationshipMap[$id] = ltrim($target, '/');
                }
            }

            $workbook->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $sheetNodes = $workbook->xpath('//m:sheets/m:sheet') ?: [];

            $result = [];
            foreach ($sheetNodes as $sheetNode) {
                $sheetName = trim((string) ($sheetNode['name'] ?? ''));
                $relationshipId = trim((string) ($sheetNode->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? ''));
                if ($sheetName === '' || $relationshipId === '') {
                    continue;
                }

                $target = $relationshipMap[$relationshipId] ?? null;
                if ($target === null) {
                    continue;
                }

                $sheetPath = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                $rows = $this->readSheetRows($zip, $sheetPath, $sharedStrings);
                $result[] = [
                    'name' => $sheetName,
                    'rows' => $rows,
                ];
            }

            return $result;
        } finally {
            $zip->close();
            if ($tempCopyPath && is_file($tempCopyPath)) {
                @unlink($tempCopyPath);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $shared = $this->loadXml($xml);
        $shared->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $nodes = $shared->xpath('//m:si') ?: [];

        $result = [];
        foreach ($nodes as $index => $node) {
            $node->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $textChunks = [];
            $textNodes = $node->xpath('.//m:t') ?: [];
            foreach ($textNodes as $textNode) {
                $textChunks[] = (string) $textNode;
            }
            $result[$index] = trim(implode('', $textChunks));
        }

        return $result;
    }

    /**
     * @param array<int, string> $sharedStrings
     * @return array<int, array<int, string>>
     */
    private function readSheetRows(ZipArchive $zip, string $sheetPath, array $sharedStrings): array
    {
        $xml = $zip->getFromName($sheetPath);
        if ($xml === false) {
            return [];
        }

        $sheet = $this->loadXml($xml);
        $sheet->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rowNodes = $sheet->xpath('//m:sheetData/m:row') ?: [];

        $rows = [];
        foreach ($rowNodes as $rowNode) {
            $rowNode->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $cells = [];
            $cellNodes = $rowNode->xpath('./m:c') ?: [];
            foreach ($cellNodes as $cellNode) {
                $ref = (string) ($cellNode['r'] ?? '');
                $colIndex = $this->columnIndexFromReference($ref);
                if ($colIndex <= 0) {
                    continue;
                }
                $cells[$colIndex] = $this->readCellValue($cellNode, $sharedStrings);
            }
            if (count($cells) > 0) {
                $rows[] = $cells;
            }
        }

        return $rows;
    }

    /**
     * @param array<int, string> $sharedStrings
     */
    private function readCellValue(SimpleXMLElement $cellNode, array $sharedStrings): string
    {
        $cellNode->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $type = (string) ($cellNode['t'] ?? '');
        if ($type === 's') {
            $index = (int) ($cellNode->v ?? -1);
            return trim((string) ($sharedStrings[$index] ?? ''));
        }

        if ($type === 'inlineStr') {
            $textNodes = $cellNode->xpath('.//m:t');
            if ($textNodes && count($textNodes) > 0) {
                return trim(implode('', array_map(static fn ($node) => (string) $node, $textNodes)));
            }
            return trim((string) ($cellNode->is->t ?? ''));
        }

        if (isset($cellNode->v)) {
            return trim((string) $cellNode->v);
        }

        return trim((string) $cellNode);
    }

    private function columnIndexFromReference(string $reference): int
    {
        if (!preg_match('/^([A-Z]+)/i', strtoupper($reference), $matches)) {
            return 0;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;
        $length = strlen($letters);
        for ($i = 0; $i < $length; $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return $index;
    }

    private function shouldSkipSheet(string $sheetName): bool
    {
        $name = strtolower(trim($sheetName));
        return str_contains($name, 'rate list') || str_contains($name, 'expense');
    }

    private function isHeaderOrSectionRow(string $ingredientName): bool
    {
        $name = strtolower(trim($ingredientName));
        if ($name === '') {
            return true;
        }

        if (str_contains($name, 'ingredient')) {
            return true;
        }

        if (str_starts_with($name, 'for ') || str_contains($name, 'square tin')) {
            return true;
        }

        return false;
    }

    /**
     * @return array{value:float, unit:string}|null
     */
    private function parseQuantity(string $raw): ?array
    {
        $text = trim($raw);
        if ($text === '') {
            return null;
        }

        $lower = strtolower(preg_replace('/\s+/', ' ', $text) ?? $text);
        $value = null;

        if (preg_match('/(\d+)\s*\/\s*(\d+)/', $lower, $fraction)) {
            $denominator = (float) $fraction[2];
            if ($denominator > 0) {
                $value = (float) $fraction[1] / $denominator;
            }
        }

        if ($value === null && preg_match('/(\d+(?:\.\d+)?)/', $lower, $number)) {
            $value = (float) $number[1];
        }

        if ($value === null || $value <= 0) {
            return null;
        }

        $unit = 'g';
        if ($this->matchesAny($lower, ['/tsp/', '/tbsp/', '/whole/', '/egg/', '/drop/', '/\bpcs?\b/', '/piece/'])) {
            $unit = 'pcs';
        } elseif ($this->matchesAny($lower, ['/kg\b/', '/kilogram/'])) {
            $unit = 'g';
            $value *= 1000;
        } elseif ($this->matchesAny($lower, ['/ml\b/'])) {
            $unit = 'ml';
        } elseif ($this->matchesAny($lower, ['/ltr\b/', '/litre/', '/liter/'])) {
            $unit = 'ml';
            $value *= 1000;
        } elseif ($this->matchesAny($lower, ['/gram/', '/\bg\b/'])) {
            $unit = 'g';
        }

        return [
            'value' => round($value, 4),
            'unit' => $unit,
        ];
    }

    private function parseAmount(string $raw): float
    {
        $clean = str_replace(',', '', trim($raw));
        if ($clean === '') {
            return 0.0;
        }

        if (!preg_match('/-?\d+(?:\.\d+)?/', $clean, $matches)) {
            return 0.0;
        }

        return round((float) $matches[0], 2);
    }

    private function normalizeFinishedProductName(string $sheetName): string
    {
        $name = preg_replace('/\s+/', ' ', trim($sheetName)) ?? trim($sheetName);
        if ($name === '') {
            return 'Unnamed Product';
        }

        $name = preg_replace('/\s*-\s*(\d+(?:\.\d+)?)\s*(grams?|g)\b/i', ' $1g', $name) ?? $name;
        $name = preg_replace('/\s*-\s*/', ' ', $name) ?? $name;
        $name = preg_replace('/\s+/', ' ', trim($name)) ?? trim($name);

        return $name;
    }

    private function normalizeIngredientName(string $value): string
    {
        $name = preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
        $name = trim($name, ": \t\n\r\0\x0B");
        return $name;
    }

    private function nameKey(string $value): string
    {
        $lower = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9]+/', '', $lower);
        return $normalized ?: $lower;
    }

    /**
     * @param array<string, Product> $byTypeAndName
     * @param array<string, bool> $usedCodes
     * @param array<string, bool> $usedLegacyCodes
     */
    private function upsertProduct(
        array &$byTypeAndName,
        array &$usedCodes,
        array &$usedLegacyCodes,
        int &$nextCodeNumber,
        int &$nextLegacyCode,
        string $type,
        string $name,
        string $unit,
        ?float $price
    ): Product {
        $nameKey = $this->nameKey($name);
        $mapKey = $type . ':' . $nameKey;
        $existing = $byTypeAndName[$mapKey] ?? null;
        if ($existing) {
            $updates = [];
            if ($type === 'raw_material') {
                if (trim((string) $existing->unit) === '') {
                    $updates['unit'] = $unit;
                }
                if ($price !== null && (float) $existing->price <= 0) {
                    $updates['price'] = $price;
                }
            }
            if (!$existing->is_active) {
                $updates['is_active'] = true;
            }
            if (count($updates) > 0) {
                $existing->update($updates);
                $existing->refresh();
            }

            return $existing;
        }

        $prefix = $type === 'finished_good' ? 'FG' : 'RM';
        $code = $this->nextAvailableCode($usedCodes, $prefix, $nextCodeNumber);
        $legacyCode = $this->nextAvailableLegacyCode($usedLegacyCodes, $nextLegacyCode);

        $product = Product::create([
            'code' => $code,
            'legacy_code' => $legacyCode,
            'name' => $name,
            'type' => $type,
            'unit' => $unit,
            'reorder_level' => 0,
            'price' => $price ?? 0,
            'unit_cost' => 0,
            'is_active' => true,
        ]);

        $byTypeAndName[$mapKey] = $product;
        return $product;
    }

    /**
     * @param array<string, bool> $usedCodes
     */
    private function nextCodeNumber(array $usedCodes, string $prefix): int
    {
        $max = 0;
        foreach (array_keys($usedCodes) as $code) {
            if (!str_starts_with($code, $prefix)) {
                continue;
            }
            $numeric = (int) preg_replace('/\D+/', '', $code);
            if ($numeric > $max) {
                $max = $numeric;
            }
        }

        return $max + 1;
    }

    /**
     * @param array<string, bool> $usedLegacyCodes
     */
    private function nextLegacyNumber(array $usedLegacyCodes): int
    {
        $max = 0;
        foreach (array_keys($usedLegacyCodes) as $code) {
            if (!preg_match('/^\d+$/', $code)) {
                continue;
            }
            $numeric = (int) $code;
            if ($numeric > $max) {
                $max = $numeric;
            }
        }

        return $max + 1;
    }

    /**
     * @param array<string, bool> $usedCodes
     */
    private function nextAvailableCode(array &$usedCodes, string $prefix, int &$nextCodeNumber): string
    {
        while (true) {
            $candidate = sprintf('%s%03d', $prefix, $nextCodeNumber);
            $nextCodeNumber++;
            if (!isset($usedCodes[$candidate])) {
                $usedCodes[$candidate] = true;
                return $candidate;
            }
        }
    }

    /**
     * @param array<string, bool> $usedLegacyCodes
     */
    private function nextAvailableLegacyCode(array &$usedLegacyCodes, int &$nextLegacyCode): string
    {
        while (true) {
            $candidate = $nextLegacyCode < 100
                ? str_pad((string) $nextLegacyCode, 2, '0', STR_PAD_LEFT)
                : (string) $nextLegacyCode;
            $nextLegacyCode++;
            if (!isset($usedLegacyCodes[$candidate])) {
                $usedLegacyCodes[$candidate] = true;
                return $candidate;
            }
        }
    }

    /**
     * @param array<int, string> $patterns
     */
    private function matchesAny(string $value, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    private function loadXml(string $xml): SimpleXMLElement
    {
        $loaded = simplexml_load_string($xml);
        if ($loaded === false) {
            throw new RuntimeException('Unable to read workbook XML.');
        }

        return $loaded;
    }
}

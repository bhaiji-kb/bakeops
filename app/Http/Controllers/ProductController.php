<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ActivityLogService;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    private const TYPES = ['raw_material', 'finished_good'];
    private const UNITS = ['kg', 'g', 'ltr', 'ml', 'pcs'];
    private const CODE_PATTERN = '/^(FG|RM|PK)\d{3}$/i';

    public function index()
    {
        $products = Product::query()
            ->orderBy('type')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $types = self::TYPES;
        $units = self::UNITS;
        $stats = [
            'total' => $products->count(),
            'finished_goods' => $products->where('type', 'finished_good')->count(),
            'raw_materials' => $products->where('type', 'raw_material')->count(),
            'active' => $products->where('is_active', true)->count(),
            'low_stock' => $products->filter(function (Product $product): bool {
                return (float) $product->currentStock() <= (float) $product->reorder_level;
            })->count(),
        ];

        return view('products.index', compact('products', 'types', 'units', 'stats'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:products,name',
            'type' => ['required', Rule::in(self::TYPES)],
            'unit' => ['required', Rule::in(self::UNITS)],
            'reorder_level' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'opening_stock' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $normalizedCode = $this->generateNextCode((string) $validated['type']);
        $legacyCode = $this->generateNextLegacyCode();
        $openingStock = round((float) ($validated['opening_stock'] ?? 0), 2);

        $product = Product::create([
            'code' => $normalizedCode,
            'legacy_code' => $legacyCode,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'unit' => $validated['unit'],
            'reorder_level' => $validated['reorder_level'],
            'price' => $validated['price'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        if ($openingStock > 0) {
            (new InventoryService())->addStock(
                $product,
                $openingStock,
                'Opening stock at product creation'
            );
        }

        app(ActivityLogService::class)->log(
            module: 'products',
            action: 'create',
            entityType: Product::class,
            entityId: (int) $product->id,
            description: 'Product created.',
            newValues: [
                'code' => $product->code,
                'legacy_code' => $product->legacy_code,
                'name' => $product->name,
                'type' => $product->type,
                'unit' => $product->unit,
                'reorder_level' => (float) $product->reorder_level,
                'price' => (float) $product->price,
                'opening_stock' => $openingStock,
                'is_active' => (bool) $product->is_active,
            ]
        );

        return redirect()->route('products.index')->with('success', 'Product created successfully.');
    }

    public function edit(Product $product)
    {
        $types = self::TYPES;
        $units = self::UNITS;

        return view('products.edit', compact('product', 'types', 'units'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:5', 'regex:' . self::CODE_PATTERN, Rule::unique('products', 'code')->ignore($product->id)],
            'name' => ['required', 'string', 'max:255', Rule::unique('products', 'name')->ignore($product->id)],
            'type' => ['required', Rule::in(self::TYPES)],
            'unit' => ['required', Rule::in(self::UNITS)],
            'reorder_level' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $normalizedCode = strtoupper(trim((string) $validated['code']));
        $this->validateCodeTypeMatch($normalizedCode, (string) $validated['type']);

        $oldValues = [
            'code' => $product->code,
            'name' => $product->name,
            'type' => $product->type,
            'unit' => $product->unit,
            'reorder_level' => (float) $product->reorder_level,
            'price' => (float) $product->price,
            'is_active' => (bool) $product->is_active,
        ];

        $product->update([
            'code' => $normalizedCode,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'unit' => $validated['unit'],
            'reorder_level' => $validated['reorder_level'],
            'price' => $validated['price'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        app(ActivityLogService::class)->log(
            module: 'products',
            action: 'update',
            entityType: Product::class,
            entityId: (int) $product->id,
            description: 'Product updated.',
            oldValues: $oldValues,
            newValues: [
                'code' => $product->code,
                'name' => $product->name,
                'type' => $product->type,
                'unit' => $product->unit,
                'reorder_level' => (float) $product->reorder_level,
                'price' => (float) $product->price,
                'is_active' => (bool) $product->is_active,
            ]
        );

        return redirect()->route('products.index')->with('success', 'Product updated successfully.');
    }

    private function validateCodeTypeMatch(string $code, string $type): void
    {
        if ($type === 'finished_good' && !str_starts_with($code, 'FG')) {
            throw ValidationException::withMessages([
                'code' => 'Finished goods must use FG prefix (e.g. FG001).',
            ]);
        }

        if ($type === 'raw_material' && !str_starts_with($code, 'RM')) {
            throw ValidationException::withMessages([
                'code' => 'Raw materials must use RM prefix (e.g. RM001).',
            ]);
        }
    }

    private function generateNextCode(string $type): string
    {
        $prefix = $type === 'finished_good' ? 'FG' : 'RM';
        $existingCodes = Product::query()
            ->where('code', 'like', $prefix . '%')
            ->pluck('code')
            ->map(static fn ($code) => strtoupper(trim((string) $code)))
            ->values()
            ->all();

        $max = 0;
        foreach ($existingCodes as $code) {
            if (preg_match('/^' . $prefix . '(\d{3})$/', $code, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        for ($next = $max + 1; $next <= 999; $next++) {
            $candidate = sprintf('%s%03d', $prefix, $next);
            if (!in_array($candidate, $existingCodes, true)) {
                return $candidate;
            }
        }

        throw ValidationException::withMessages([
            'type' => 'Unable to generate product code. Maximum limit reached for ' . $prefix . ' series.',
        ]);
    }

    private function generateNextLegacyCode(): string
    {
        $existingLegacyCodes = Product::query()
            ->whereNotNull('legacy_code')
            ->pluck('legacy_code')
            ->map(static fn ($code) => trim((string) $code))
            ->filter(static fn ($code) => $code !== '')
            ->values()
            ->all();

        for ($next = 1; $next <= 9999; $next++) {
            $candidate = $next < 100 ? str_pad((string) $next, 2, '0', STR_PAD_LEFT) : (string) $next;
            if (!in_array($candidate, $existingLegacyCodes, true)) {
                return $candidate;
            }
        }

        throw ValidationException::withMessages([
            'name' => 'Unable to generate short product code. Legacy code limit reached.',
        ]);
    }
}

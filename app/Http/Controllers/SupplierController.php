<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::orderBy('name')->orderBy('id')->get();

        return view('suppliers.index', compact('suppliers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $supplier = Supplier::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        app(ActivityLogService::class)->log(
            module: 'suppliers',
            action: 'create',
            entityType: Supplier::class,
            entityId: (int) $supplier->id,
            description: 'Supplier created.',
            newValues: [
                'name' => $supplier->name,
                'phone' => $supplier->phone,
                'address' => $supplier->address,
                'is_active' => (bool) $supplier->is_active,
            ]
        );

        return redirect()->route('suppliers.index')->with('success', 'Supplier added successfully.');
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $oldValues = [
            'name' => $supplier->name,
            'phone' => $supplier->phone,
            'address' => $supplier->address,
            'is_active' => (bool) $supplier->is_active,
        ];

        $supplier->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        app(ActivityLogService::class)->log(
            module: 'suppliers',
            action: 'update',
            entityType: Supplier::class,
            entityId: (int) $supplier->id,
            description: 'Supplier updated.',
            oldValues: $oldValues,
            newValues: [
                'name' => $supplier->name,
                'phone' => $supplier->phone,
                'address' => $supplier->address,
                'is_active' => (bool) $supplier->is_active,
            ]
        );

        return redirect()->route('suppliers.index')->with('success', 'Supplier updated successfully.');
    }
}

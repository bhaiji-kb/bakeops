<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\CustomerMasterService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $segment = strtolower(trim((string) $request->get('segment', 'all')));
        $highValueThreshold = 5000.00;
        if (!in_array($segment, ['all', 'repeat', 'high_value', 'new'], true)) {
            $segment = 'all';
        }
        $highValueCustomerIds = Customer::query()
            ->withSum('sales', 'total_amount')
            ->get(['id'])
            ->filter(fn (Customer $customer) => (float) ($customer->sales_sum_total_amount ?? 0) >= $highValueThreshold)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        $customers = Customer::query()
            ->when($segment === 'repeat', function ($query) {
                $query->has('sales', '>=', 2);
            })
            ->when($segment === 'new', function ($query) {
                $query->doesntHave('sales');
            })
            ->when($segment === 'high_value', function ($query) use ($highValueCustomerIds) {
                if (empty($highValueCustomerIds)) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->whereIn('id', $highValueCustomerIds);
            })
            ->when($q !== '', function ($query) use ($q) {
                $qLower = mb_strtolower($q);
                $query->where(function ($inner) use ($qLower) {
                    $inner->whereRaw('LOWER(name) like ?', ['%' . $qLower . '%'])
                        ->orWhereRaw('LOWER(COALESCE(mobile, \'\')) like ?', ['%' . $qLower . '%'])
                        ->orWhereRaw('LOWER(COALESCE(identifier, \'\')) like ?', ['%' . $qLower . '%'])
                        ->orWhereRaw('LOWER(COALESCE(address, \'\')) like ?', ['%' . $qLower . '%'])
                        ->orWhereRaw('LOWER(COALESCE(city, \'\')) like ?', ['%' . $qLower . '%'])
                        ->orWhereRaw('LOWER(COALESCE(preference, \'\')) like ?', ['%' . $qLower . '%'])
                        ->orWhereRaw('LOWER(COALESCE(pincode, \'\')) like ?', ['%' . $qLower . '%']);
                });
            })
            ->withCount('sales')
            ->withSum('sales', 'total_amount')
            ->withMax('sales', 'created_at')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(100)
            ->withQueryString();

        $segmentCounts = [
            'repeat' => Customer::query()->has('sales', '>=', 2)->count(),
            'new' => Customer::query()->doesntHave('sales')->count(),
            'high_value' => count($highValueCustomerIds),
        ];

        return view('customers.index', compact('customers', 'q', 'segment', 'segmentCounts', 'highValueThreshold'));
    }

    public function store(Request $request)
    {
        $validated = $this->validatedPayload($request);

        Customer::create($validated);

        return redirect()->route('customers.index')->with('success', 'Customer is saved now.');
    }

    public function edit(Customer $customer)
    {
        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $this->validatedPayload($request, $customer);

        $customer->update($validated);

        return redirect()->route('customers.index')->with('success', 'Customer updated successfully.');
    }

    private function validatedPayload(Request $request, ?Customer $customer = null): array
    {
        $customerMaster = app(CustomerMasterService::class);

        $mobile = $customerMaster->normalizeMobile((string) $request->input('mobile', ''));
        $identifier = $this->normalizeIdentifier((string) $request->input('identifier', ''));

        $rawProfile = [
            'address_line1' => (string) $request->input('address_line1', ''),
            'road' => (string) $request->input('road', ''),
            'sector' => (string) $request->input('sector', ''),
            'city' => (string) $request->input('city', ''),
            'pincode' => (string) $request->input('pincode', ''),
            'preference' => (string) $request->input('preference', $request->input('notes', '')),
            'notes' => (string) $request->input('notes', ''),
            'address' => (string) $request->input('address', ''),
        ];

        $profile = $customerMaster->normalizeProfile($rawProfile);
        $composedAddress = $customerMaster->composeAddress($profile);

        $request->merge([
            'mobile' => $mobile ?: null,
            'identifier' => $identifier ?: null,
            'address_line1' => $profile['address_line1'] ?: null,
            'road' => $profile['road'] ?: null,
            'sector' => $profile['sector'] ?: null,
            'city' => $profile['city'] ?: null,
            'pincode' => $profile['pincode'] ?: null,
            'preference' => $profile['preference'] ?: null,
            'notes' => $profile['preference'] ?: null,
            'address' => $composedAddress !== '' ? $composedAddress : null,
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile' => [
                'nullable',
                'string',
                'regex:/^\d{10}$/',
                Rule::unique('customers', 'mobile')->ignore($customer?->id),
            ],
            'identifier' => [
                'nullable',
                'string',
                'max:40',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('customers', 'identifier')->ignore($customer?->id),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'road' => ['nullable', 'string', 'max:255'],
            'sector' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'pincode' => ['nullable', 'string', 'regex:/^\d{6}$/'],
            'preference' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (empty($validated['mobile']) && empty($validated['identifier'])) {
            throw ValidationException::withMessages([
                'mobile' => 'Enter 10-digit mobile or alternate identifier.',
                'identifier' => 'Enter 10-digit mobile or alternate identifier.',
            ]);
        }

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        foreach (['address_line1', 'road', 'sector', 'city', 'pincode', 'preference'] as $key) {
            $value = trim((string) ($validated[$key] ?? ''));
            $validated[$key] = $value !== '' ? $value : null;
        }
        $validated['notes'] = $validated['preference'];

        $validated['address'] = trim((string) ($validated['address'] ?? ''));
        if ($validated['address'] === '') {
            $validated['address'] = null;
        }

        return $validated;
    }

    private function normalizeIdentifier(string $value): string
    {
        return strtoupper(trim($value));
    }
}


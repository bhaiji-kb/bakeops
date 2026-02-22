<?php

namespace App\Http\Controllers;

use App\Models\IntegrationConnector;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IntegrationConnectorController extends Controller
{
    public function index()
    {
        $connectors = IntegrationConnector::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get();
        $drivers = IntegrationConnector::DRIVERS;

        return view('integrations.connectors.index', compact('connectors', 'drivers'));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $settings = $this->decodeSettingsJson((string) ($validated['settings_json'] ?? ''));
        if ($settings === null) {
            return back()->withInput()->withErrors([
                'settings_json' => 'Settings JSON is invalid.',
            ]);
        }

        $connector = IntegrationConnector::create([
            'code' => strtolower((string) $validated['code']),
            'name' => $validated['name'],
            'driver' => strtolower((string) $validated['driver']),
            'api_base_url' => $validated['api_base_url'] ?? null,
            'api_key' => $validated['api_key'] ?? null,
            'api_secret' => $validated['api_secret'] ?? null,
            'webhook_secret' => $validated['webhook_secret'] ?? null,
            'settings' => $settings,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        app(ActivityLogService::class)->log(
            module: 'integrations',
            action: 'create_connector',
            entityType: IntegrationConnector::class,
            entityId: (int) $connector->id,
            description: 'Integration connector created.',
            newValues: [
                'code' => $connector->code,
                'name' => $connector->name,
                'driver' => $connector->driver,
                'is_active' => (bool) $connector->is_active,
            ]
        );

        return redirect()->route('integrations.connectors.index')->with('success', 'Connector created successfully.');
    }

    public function edit(IntegrationConnector $connector)
    {
        $drivers = IntegrationConnector::DRIVERS;

        return view('integrations.connectors.edit', compact('connector', 'drivers'));
    }

    public function update(Request $request, IntegrationConnector $connector)
    {
        $validated = $this->validatePayload($request, $connector);
        $settings = $this->decodeSettingsJson((string) ($validated['settings_json'] ?? ''));
        if ($settings === null) {
            return back()->withInput()->withErrors([
                'settings_json' => 'Settings JSON is invalid.',
            ]);
        }

        $oldValues = [
            'code' => $connector->code,
            'name' => $connector->name,
            'driver' => $connector->driver,
            'api_base_url' => $connector->api_base_url,
            'is_active' => (bool) $connector->is_active,
        ];

        $updateData = [
            'code' => strtolower((string) $validated['code']),
            'name' => $validated['name'],
            'driver' => strtolower((string) $validated['driver']),
            'api_base_url' => $validated['api_base_url'] ?? null,
            'settings' => $settings,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];

        if (($validated['api_key'] ?? '') !== '') {
            $updateData['api_key'] = $validated['api_key'];
        } elseif ($request->boolean('clear_api_key')) {
            $updateData['api_key'] = null;
        }

        if (($validated['api_secret'] ?? '') !== '') {
            $updateData['api_secret'] = $validated['api_secret'];
        } elseif ($request->boolean('clear_api_secret')) {
            $updateData['api_secret'] = null;
        }

        if (($validated['webhook_secret'] ?? '') !== '') {
            $updateData['webhook_secret'] = $validated['webhook_secret'];
        } elseif ($request->boolean('clear_webhook_secret')) {
            $updateData['webhook_secret'] = null;
        }

        $connector->update($updateData);

        app(ActivityLogService::class)->log(
            module: 'integrations',
            action: 'update_connector',
            entityType: IntegrationConnector::class,
            entityId: (int) $connector->id,
            description: 'Integration connector updated.',
            oldValues: $oldValues,
            newValues: [
                'code' => $connector->code,
                'name' => $connector->name,
                'driver' => $connector->driver,
                'api_base_url' => $connector->api_base_url,
                'is_active' => (bool) $connector->is_active,
            ]
        );

        return redirect()->route('integrations.connectors.index')->with('success', 'Connector updated successfully.');
    }

    private function validatePayload(Request $request, ?IntegrationConnector $connector = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[a-z0-9][a-z0-9_-]*$/i',
                Rule::unique('integration_connectors', 'code')->ignore($connector?->id),
            ],
            'name' => 'required|string|max:100',
            'driver' => 'required|string|max:40|regex:/^[a-z0-9][a-z0-9_-]*$/i',
            'api_base_url' => 'nullable|url|max:255',
            'api_key' => 'nullable|string|max:2000',
            'api_secret' => 'nullable|string|max:4000',
            'webhook_secret' => 'nullable|string|max:4000',
            'settings_json' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeSettingsJson(string $json): ?array
    {
        $trimmed = trim($json);
        if ($trimmed === '') {
            return [];
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}

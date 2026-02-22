<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogService;
use App\Services\BusinessSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

class AdminSettingsController extends Controller
{
    public function index()
    {
        $service = app(BusinessSettingsService::class);
        $settings = $service->get();
        $settingsReady = $service->isReady();

        return view('admin.settings', compact('settings', 'settingsReady'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'business_name' => 'nullable|string|max:120',
            'business_address' => 'nullable|string|max:500',
            'business_phone' => 'nullable|string|max:30',
            'business_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'remove_business_logo' => 'nullable|boolean',
            'gst_enabled' => 'nullable|boolean',
            'gstin' => 'nullable|string|max:20',
            'kot_mode' => ['nullable', Rule::in(['off', 'conditional', 'always'])],
        ]);

        $newLogoPath = null;
        if ($request->hasFile('business_logo')) {
            $newLogoPath = $request->file('business_logo')->store('business-logos', 'public');
            $validated['business_logo_path'] = $newLogoPath;
        } elseif ((bool) ($validated['remove_business_logo'] ?? false)) {
            $validated['business_logo_path'] = '';
        }
        unset($validated['business_logo'], $validated['remove_business_logo']);

        $validated['gst_enabled'] = (bool) ($validated['gst_enabled'] ?? false);
        $validated['kot_mode'] = strtolower(trim((string) ($validated['kot_mode'] ?? 'always')));
        if ($validated['gst_enabled'] && trim((string) ($validated['gstin'] ?? '')) === '') {
            return back()->withInput()->withErrors([
                'gstin' => 'GSTIN is required when GST is enabled.',
            ]);
        }

        $service = app(BusinessSettingsService::class);
        $oldValues = $service->get();
        $oldLogoPath = (string) ($oldValues['business_logo_path'] ?? '');
        try {
            $updated = $service->update($validated);
        } catch (RuntimeException $e) {
            if ($newLogoPath !== null) {
                Storage::disk('public')->delete($newLogoPath);
            }
            return back()->withInput()->withErrors([
                'settings' => $e->getMessage(),
            ]);
        }
        $newValues = $service->get();
        $newLogoPathAfterUpdate = (string) ($newValues['business_logo_path'] ?? '');
        if ($oldLogoPath !== '' && $oldLogoPath !== $newLogoPathAfterUpdate) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        app(ActivityLogService::class)->log(
            module: 'admin',
            action: 'update_business_settings',
            entityType: \App\Models\BusinessSetting::class,
            entityId: (int) $updated->id,
            description: 'Business settings updated by owner.',
            oldValues: $oldValues,
            newValues: $newValues
        );

        return redirect()->route('admin.settings.index')->with('success', 'Business settings updated successfully.');
    }
}

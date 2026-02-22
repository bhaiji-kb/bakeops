<?php

namespace App\Services;

use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BusinessSettingsService
{
    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        if (!$this->isReady()) {
            return $this->defaults();
        }

        $setting = BusinessSetting::query()->orderBy('id')->first();
        if (!$setting) {
            return $this->defaults();
        }

        return $this->toArray($setting);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(array $payload): BusinessSetting
    {
        if (!$this->isReady()) {
            throw new RuntimeException('Business settings storage is not ready. Run: php artisan migrate');
        }

        $setting = BusinessSetting::query()->orderBy('id')->first();
        if (!$setting) {
            $setting = new BusinessSetting();
        }

        $setting->fill([
            'business_name' => $this->normalizeString((string) ($payload['business_name'] ?? ''), 120),
            'business_address' => $this->normalizeString((string) ($payload['business_address'] ?? ''), 500),
            'business_phone' => $this->normalizeString((string) ($payload['business_phone'] ?? ''), 30),
            'business_logo_path' => array_key_exists('business_logo_path', $payload)
                ? $this->normalizeString((string) ($payload['business_logo_path'] ?? ''), 255)
                : (string) ($setting->business_logo_path ?? ''),
            'gst_enabled' => (bool) ($payload['gst_enabled'] ?? false),
            'gstin' => $this->normalizeGstin((string) ($payload['gstin'] ?? '')),
            'kot_mode' => $this->normalizeKotMode((string) ($payload['kot_mode'] ?? 'always')),
        ]);

        $setting->save();

        return $setting->refresh();
    }

    public function isGstEnabled(): bool
    {
        return (bool) ($this->get()['gst_enabled'] ?? false);
    }

    public function kotMode(): string
    {
        return $this->normalizeKotMode((string) ($this->get()['kot_mode'] ?? 'always'));
    }

    public function isReady(): bool
    {
        try {
            return Schema::hasTable('business_settings');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'business_name' => 'BakeOps Bakery',
            'business_address' => '',
            'business_phone' => '',
            'business_logo_path' => '',
            'business_logo_url' => '',
            'gst_enabled' => false,
            'gstin' => '',
            'kot_mode' => 'always',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(BusinessSetting $setting): array
    {
        $logoPath = (string) ($setting->business_logo_path ?: '');
        $logoUrl = '';
        if ($logoPath !== '') {
            try {
                $logoUrl = Storage::disk('public')->url($logoPath);
            } catch (\Throwable) {
                $logoUrl = '';
            }
        }

        return [
            'business_name' => (string) ($setting->business_name ?: 'BakeOps Bakery'),
            'business_address' => (string) ($setting->business_address ?: ''),
            'business_phone' => (string) ($setting->business_phone ?: ''),
            'business_logo_path' => $logoPath,
            'business_logo_url' => $logoUrl,
            'gst_enabled' => (bool) $setting->gst_enabled,
            'gstin' => (string) ($setting->gstin ?: ''),
            'kot_mode' => $this->normalizeKotMode((string) ($setting->kot_mode ?: 'always')),
        ];
    }

    private function normalizeString(string $value, int $maxLength): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($value));
        if ($normalized === '') {
            return '';
        }

        if (mb_strlen($normalized) > $maxLength) {
            return mb_substr($normalized, 0, $maxLength);
        }

        return $normalized;
    }

    private function normalizeGstin(string $value): string
    {
        $normalized = strtoupper($this->normalizeString($value, 20));
        if ($normalized === '') {
            return '';
        }

        return preg_replace('/\s+/', '', $normalized);
    }

    private function normalizeKotMode(string $value): string
    {
        $mode = strtolower(trim($value));
        if (!in_array($mode, ['off', 'conditional', 'always'], true)) {
            return 'always';
        }

        return $mode;
    }
}

<?php

namespace App\Services;

use App\Models\Customer;

class CustomerMasterService
{
    /**
     * @param  array<string, mixed>|string|null  $profile
     */
    public function upsertByIdentifier(
        string $identifier,
        ?string $name = null,
        array|string|null $profile = null
    ): ?Customer {
        $normalizedIdentifier = $this->normalizeIdentifier($identifier);
        if ($normalizedIdentifier === '') {
            return null;
        }

        $mobile = $this->mobileFromIdentifier($normalizedIdentifier);
        $externalIdentifier = $mobile === null ? strtoupper($normalizedIdentifier) : null;

        $customer = Customer::query()
            ->where(function ($query) use ($mobile, $externalIdentifier) {
                if ($mobile !== null) {
                    $query->orWhere('mobile', $mobile);
                }
                if ($externalIdentifier !== null) {
                    $query->orWhereRaw('UPPER(COALESCE(identifier, \'\')) = ?', [$externalIdentifier]);
                }
            })
            ->first();

        $normalizedProfile = $this->normalizeProfile($profile);
        $normalizedAddress = $this->composeAddress($normalizedProfile);

        if (!$customer) {
            $customer = Customer::create([
                'name' => $this->normalizeName($name ?? 'Customer'),
                'mobile' => $mobile,
                'identifier' => $externalIdentifier,
                'address' => $normalizedAddress,
                'address_line1' => $normalizedProfile['address_line1'] ?: null,
                'road' => $normalizedProfile['road'] ?: null,
                'sector' => $normalizedProfile['sector'] ?: null,
                'city' => $normalizedProfile['city'] ?: null,
                'pincode' => $normalizedProfile['pincode'] ?: null,
                'preference' => $normalizedProfile['preference'] ?: null,
                'is_active' => true,
            ]);

            return $customer;
        }

        return $this->syncProfile($customer, $name, $normalizedProfile);
    }

    /**
     * @param  array<string, mixed>|string|null  $profile
     */
    public function syncProfile(Customer $customer, ?string $name = null, array|string|null $profile = null): Customer
    {
        $normalizedName = $this->normalizeName($name);
        $normalizedProfile = $this->normalizeProfile($profile);
        $normalizedAddress = $this->composeAddress($normalizedProfile);

        $updates = [];
        if ($normalizedName !== '' && $normalizedName !== (string) $customer->name) {
            $updates['name'] = $normalizedName;
        }

        foreach (['address_line1', 'road', 'sector', 'city', 'pincode', 'preference'] as $key) {
            $value = (string) ($normalizedProfile[$key] ?? '');
            if ($value !== '' && $value !== (string) ($customer->{$key} ?? '')) {
                $updates[$key] = $value;
            }
        }

        if ($normalizedAddress !== '' && $normalizedAddress !== (string) ($customer->address ?? '')) {
            $updates['address'] = $normalizedAddress;
        }

        if (!$customer->is_active) {
            $updates['is_active'] = true;
        }

        if (!empty($updates)) {
            $customer->update($updates);
            $customer->refresh();
        }

        return $customer;
    }

    public function normalizeIdentifier(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }

        $mobile = $this->normalizeMobile($raw);
        if ($mobile !== null) {
            return $mobile;
        }

        $normalized = strtoupper(preg_replace('/\s+/', '', $raw) ?: '');

        return trim($normalized);
    }

    public function normalizeName(?string $value): string
    {
        $name = preg_replace('/\s+/', ' ', trim((string) $value));
        if ($name === '') {
            return '';
        }

        return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
    }

    public function normalizeAddress(?string $value): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim((string) $value));

        return mb_substr((string) $normalized, 0, 500);
    }

    public function normalizeMobile(string $value): ?string
    {
        $digitsOnly = preg_replace('/\D+/', '', trim($value)) ?: '';
        if ($digitsOnly === '') {
            return null;
        }

        if (preg_match('/^\d{10}$/', $digitsOnly)) {
            return $digitsOnly;
        }
        if (preg_match('/^0\d{10}$/', $digitsOnly)) {
            return substr($digitsOnly, 1);
        }
        if (preg_match('/^91\d{10}$/', $digitsOnly)) {
            return substr($digitsOnly, 2);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|string|null  $profile
     * @return array<string, string>
     */
    public function normalizeProfile(array|string|null $profile): array
    {
        if (is_string($profile)) {
            return [
                'address_line1' => $this->normalizeAddressText($profile, 255),
                'road' => '',
                'sector' => '',
                'city' => '',
                'pincode' => '',
                'preference' => '',
            ];
        }

        $profile = is_array($profile) ? $profile : [];

        $addressLine1 = $this->normalizeAddressText($this->pickFirst($profile, [
            'address_line1',
            'apartment_house',
            'house',
            'line1',
            'customer_address_line1',
        ]), 255);

        $fullAddress = $this->normalizeAddressPart($this->pickFirst($profile, [
            'address',
            'customer_address',
            'delivery_address',
        ]), 500);

        if ($addressLine1 === '' && $fullAddress !== '') {
            $addressLine1 = mb_substr($fullAddress, 0, 255);
        }

        return [
            'address_line1' => $addressLine1,
            'road' => $this->normalizeAddressText($this->pickFirst($profile, ['road', 'street', 'customer_road']), 255),
            'sector' => $this->normalizeAddressText($this->pickFirst($profile, ['sector', 'area', 'customer_sector']), 120),
            'city' => $this->normalizeAddressText($this->pickFirst($profile, ['city', 'town', 'customer_city']), 120),
            'pincode' => $this->normalizePincode((string) $this->pickFirst($profile, ['pincode', 'postal_code', 'zipcode', 'zip', 'customer_pincode'])),
            'preference' => $this->normalizeAddressPart($this->pickFirst($profile, ['preference', 'customer_preference', 'notes', 'customer_notes']), 255),
        ];
    }

    /**
     * @param  array<string, string>  $profile
     */
    public function composeAddress(array $profile): string
    {
        $parts = [
            (string) ($profile['address_line1'] ?? ''),
            (string) ($profile['road'] ?? ''),
            (string) ($profile['sector'] ?? ''),
            (string) ($profile['city'] ?? ''),
        ];

        $parts = array_values(array_filter(array_map(fn (string $value) => trim($value), $parts), fn (string $value) => $value !== ''));
        $address = implode(', ', $parts);

        $pincode = trim((string) ($profile['pincode'] ?? ''));
        if ($pincode !== '') {
            $address = trim($address . ($address !== '' ? ' - ' : '') . $pincode);
        }

        return mb_substr($address, 0, 500);
    }

    public function buildAddressFromCustomer(?Customer $customer): string
    {
        if (!$customer) {
            return '';
        }

        $profile = [
            'address_line1' => (string) ($customer->address_line1 ?? ''),
            'road' => (string) ($customer->road ?? ''),
            'sector' => (string) ($customer->sector ?? ''),
            'city' => (string) ($customer->city ?? ''),
            'pincode' => (string) ($customer->pincode ?? ''),
            'preference' => (string) ($customer->preference ?? ''),
        ];

        $address = $this->composeAddress($profile);
        if ($address !== '') {
            return $address;
        }

        return $this->normalizeAddress((string) ($customer->address ?? ''));
    }

    private function mobileFromIdentifier(string $identifier): ?string
    {
        return $this->normalizeMobile($identifier);
    }

    private function normalizeAddressPart(mixed $value, int $maxLength): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim((string) $value));

        return mb_substr((string) $normalized, 0, $maxLength);
    }

    private function normalizeAddressText(mixed $value, int $maxLength): string
    {
        $normalized = $this->normalizeAddressPart($value, $maxLength);
        if ($normalized === '') {
            return '';
        }

        return mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8');
    }

    private function normalizePincode(string $value): string
    {
        $digits = preg_replace('/\D+/', '', trim($value)) ?: '';
        if ($digits === '') {
            return '';
        }

        if (strlen($digits) < 6) {
            return '';
        }

        return substr($digits, 0, 6);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $keys
     */
    private function pickFirst(array $payload, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];
            if (is_scalar($value)) {
                $text = trim((string) $value);
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return '';
    }
}

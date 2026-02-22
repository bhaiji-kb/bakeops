<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_and_update_business_settings(): void
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $this->actingAs($owner)->get(route('admin.settings.index'))->assertOk();

        $response = $this->actingAs($owner)->put(route('admin.settings.update'), [
            'business_name' => 'BakeOps Prime',
            'business_address' => 'Main Road, City',
            'business_phone' => '9876543210',
            'gst_enabled' => '1',
            'gstin' => '22AAAAA0000A1Z5',
            'kot_mode' => 'conditional',
        ]);

        $response->assertRedirect(route('admin.settings.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('business_settings', [
            'business_name' => 'BakeOps Prime',
            'business_address' => 'Main Road, City',
            'business_phone' => '9876543210',
            'gst_enabled' => 1,
            'gstin' => '22AAAAA0000A1Z5',
            'kot_mode' => 'conditional',
        ]);
    }

    public function test_non_owner_cannot_access_business_settings_page(): void
    {
        BusinessSetting::create([
            'business_name' => 'BakeOps Bakery',
            'gst_enabled' => false,
        ]);

        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);
        $purchase = User::factory()->create([
            'role' => User::ROLE_PURCHASE,
            'is_active' => true,
        ]);

        $this->actingAs($manager)->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($manager)->put(route('admin.settings.update'), [
            'business_name' => 'No Access',
        ])->assertForbidden();

        $this->actingAs($purchase)->get(route('admin.settings.index'))->assertForbidden();
    }

    public function test_gstin_is_required_when_gst_enabled(): void
    {
        $owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $response = $this->actingAs($owner)
            ->from(route('admin.settings.index'))
            ->put(route('admin.settings.update'), [
                'business_name' => 'BakeOps',
                'gst_enabled' => '1',
                'gstin' => '',
            ]);

        $response->assertRedirect(route('admin.settings.index'));
        $response->assertSessionHasErrors([
            'gstin' => 'GSTIN is required when GST is enabled.',
        ]);
    }
}

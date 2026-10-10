<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BecomePharmacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_switch_to_pharmacy_onboarding(): void
    {
        $user = User::create([
            'full_name' => 'Test Patient',
            'email' => 'patient@example.com',
            'password_hash' => bcrypt('password'),
            'cura_role' => 'patient',
        ]);

        $this->actingAs($user);

        $response = $this->post(route('user.become.pharmacy'));

        $response->assertRedirect(route('vendor.onboarding'));
        $this->assertSame('vendor', $user->fresh()->role);
    }

    public function test_vendor_can_switch_back_to_patient_dashboard(): void
    {
        $user = User::create([
            'full_name' => 'Test Vendor',
            'email' => 'vendor@example.com',
            'password_hash' => bcrypt('password'),
            'cura_role' => 'vendor',
        ]);

        $this->actingAs($user);

        $response = $this->post(route('vendor.become.patient'));

        $response->assertRedirect(route('user.dashboard'));
        $this->assertSame('patient', $user->fresh()->role);
    }

    public function test_vendor_dashboard_renders_when_no_pharmacy_exists(): void
    {
        $user = User::create([
            'full_name' => 'No Pharmacy Vendor',
            'email' => 'nopharmacy@example.com',
            'password_hash' => bcrypt('password'),
            'cura_role' => 'vendor',
        ]);

        if (!Schema::hasTable('wallets')) {
            Schema::create('wallets', function ($table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->decimal('balance', 12, 2)->default(0);
                $table->timestamps();
            });
        }

        $this->actingAs($user);

        $response = $this->get(route('vendor.dashboard'));

        $response->assertOk();
    }
}

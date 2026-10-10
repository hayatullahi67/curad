<?php

namespace Tests\Feature;

use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesStaffAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_can_create_sales_staff_login_details(): void
    {
        $vendor = User::create([
            'full_name' => 'Vendor Owner',
            'email' => 'owner@example.com',
            'password_hash' => bcrypt('password'),
            'cura_role' => 'vendor',
        ]);

        Pharmacy::create([
            'user_id' => $vendor->id,
            'name' => 'Owner Pharmacy',
            'address' => '123 Main Road',
            'phone' => '08012345678',
            'email' => 'ownerpharmacy@example.com',
        ]);

        $this->actingAs($vendor);

        $response = $this->post(route('vendor.sales-staff.store'), [
            'full_name' => 'Ada Sales',
            'email' => 'sales@example.com',
            'password' => 'secure123',
            'phone' => '09000000000',
            'staff_role' => 'cashier',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'email' => 'sales@example.com',
            'cura_role' => 'sales_person',
            'staff_role' => 'cashier',
            'created_by_user_id' => $vendor->id,
        ]);
    }
}

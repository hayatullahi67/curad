<?php

namespace Tests\Feature;

use App\Models\Medication;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class InventoryImportTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsVendor(): Pharmacy
    {
        $user = User::create([
            'full_name' => 'Inventory Vendor',
            'email' => 'inventory' . uniqid() . '@example.com',
            'password_hash' => bcrypt('password'),
            'cura_role' => 'vendor',
        ]);

        $pharmacy = Pharmacy::create([
            'user_id' => $user->id,
            'name' => 'Test Pharmacy',
            'address' => '123 Test Road',
            'phone' => '08000000000',
            'email' => 'pharmacy' . uniqid() . '@example.com',
        ]);

        $this->actingAs($user);

        return $pharmacy;
    }

    public function test_preview_detects_standard_headers_exactly(): void
    {
        $this->actingAsVendor();

        $csvContent = "name,category,dosage,price,stock_level,low_stock_threshold,expiry_date,description\n"
            . "Paracetamol,General,500mg,150.00,25,10,2026-12-31,For fever\n";

        $response = $this->post(route('vendor.inventory.import.preview'), [
            'csv_file' => UploadedFile::fake()->createWithContent('inventory.csv', $csvContent, 'text/csv'),
        ]);

        $response->assertOk();
        $response->assertJsonPath('mapping.name.source', 'exact_header');
        $this->assertEquals(1.0, $response->json('mapping.name.confidence'));
        $response->assertJsonPath('missing_required', []);
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_preview_maps_scrambled_and_renamed_columns_via_fuzzy_and_content_sniffing(): void
    {
        $this->actingAsVendor();

        // Columns are reordered vs. the "standard" layout, and use synonyms/typos
        // instead of the exact expected header names.
        $csvContent = "Qty on Hand,Product Title,Amount (NGN),Item Class,Exp Date\n"
            . "40,Amoxicillin,850.00,Antibiotics,2027-03-15\n"
            . "12,Ibuprofen,320.50,Painkillers,2027-06-01\n"
            . "5,Vitamin C,199.99,Supplements,2027-01-20\n";

        $response = $this->post(route('vendor.inventory.import.preview'), [
            'csv_file' => UploadedFile::fake()->createWithContent('inventory.csv', $csvContent, 'text/csv'),
        ]);

        $response->assertOk();
        $mapping = $response->json('mapping');

        // "Product Title" -> name, "Amount (NGN)" -> price, "Qty on Hand" -> stock_level,
        // "Item Class" -> category, "Exp Date" -> expiry_date — regardless of column order.
        $this->assertSame(1, $mapping['name']['column']);
        $this->assertSame(2, $mapping['price']['column']);
        $this->assertSame(0, $mapping['stock_level']['column']);
        $this->assertSame(3, $mapping['category']['column']);
        $this->assertSame(4, $mapping['expiry_date']['column']);
        $this->assertSame([], $response->json('missing_required'));
    }

    public function test_confirm_imports_using_the_supplied_mapping(): void
    {
        $pharmacy = $this->actingAsVendor();

        $csvContent = "Qty,Item,Cost\n20,Paracetamol,150.00\n";

        $preview = $this->post(route('vendor.inventory.import.preview'), [
            'csv_file' => UploadedFile::fake()->createWithContent('inventory.csv', $csvContent, 'text/csv'),
        ]);
        $preview->assertOk();
        $token = $preview->json('token');

        $response = $this->postJson(route('vendor.inventory.import.confirm'), [
            'token' => $token,
            'mapping' => [
                'name' => 1,
                'category' => null,
                'dosage' => null,
                'price' => 2,
                'stock_level' => 0,
                'low_stock_threshold' => null,
                'expiry_date' => null,
                'description' => null,
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('imported', 1);
        $this->assertDatabaseHas('medications', [
            'pharmacy_id' => $pharmacy->id,
            'name' => 'Paracetamol',
            'category' => 'General',
            'stock_level' => 20,
        ]);
        $this->assertEquals(1, Medication::count());
    }

    public function test_confirm_rejects_a_mapping_missing_required_fields(): void
    {
        $this->actingAsVendor();

        $csvContent = "Qty,Item,Cost\n20,Paracetamol,150.00\n";

        $preview = $this->post(route('vendor.inventory.import.preview'), [
            'csv_file' => UploadedFile::fake()->createWithContent('inventory.csv', $csvContent, 'text/csv'),
        ]);
        $token = $preview->json('token');

        $response = $this->postJson(route('vendor.inventory.import.confirm'), [
            'token' => $token,
            'mapping' => [
                'name' => 1,
                'price' => null,
                'stock_level' => 0,
            ],
        ]);

        $response->assertStatus(422);
        $this->assertEquals(0, Medication::count());
    }
}

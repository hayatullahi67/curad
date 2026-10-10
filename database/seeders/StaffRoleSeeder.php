<?php

namespace Database\Seeders;

use App\Models\Pharmacy;
use App\Models\User;
use App\Support\StaffRoles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates one vendor + one staff login per role for both facility types, so
 * role-based access (sidebar links, route guards, POS/reservations/dispatch
 * permissions) can be clicked through manually without hand-building users.
 * Safe to re-run: vendors/staff are upserted by email.
 */
class StaffRoleSeeder extends Seeder
{
    const PASSWORD = 'password123';

    public function run(): void
    {
        // Onboarding normally adds these columns on first visit; guarantee
        // they exist here too so this seeder works on a fresh database.
        if (! Schema::hasColumn('pharmacies', 'type')) {
            DB::statement("ALTER TABLE pharmacies ADD COLUMN type VARCHAR(50) DEFAULT 'pharmacy' AFTER name");
        }
        if (! Schema::hasColumn('pharmacies', 'license_number')) {
            DB::statement('ALTER TABLE pharmacies ADD COLUMN license_number VARCHAR(255) NULL AFTER address');
        }

        $facilities = [
            'pharmacy' => [
                'vendor_email' => 'roletest-pharmacy-owner@example.com',
                'vendor_name' => 'Role Test Pharmacy Owner',
                'facility_name' => 'Role Test Pharmacy',
            ],
            'hospital' => [
                'vendor_email' => 'roletest-hospital-owner@example.com',
                'vendor_name' => 'Role Test Hospital Owner',
                'facility_name' => 'Role Test Hospital',
            ],
        ];

        foreach ($facilities as $type => $config) {
            $vendor = $this->upsertUser($config['vendor_email'], $config['vendor_name'], [
                'cura_role' => 'vendor',
            ]);

            $pharmacy = Pharmacy::updateOrCreate(
                ['user_id' => $vendor->id],
                [
                    'name' => $config['facility_name'],
                    'type' => $type,
                    'address' => '1 Test Street, Abuja',
                    'license_number' => 'ROLETEST-' . strtoupper($type),
                    'phone' => '08000000000',
                    'email' => $config['vendor_email'],
                    'is_verified' => true,
                ]
            );

            $this->command?->info("[{$type}] vendor: {$config['vendor_email']} / " . self::PASSWORD);

            foreach (StaffRoles::forFacilityType($type) as $roleKey => $role) {
                $email = "roletest-{$type}-{$roleKey}@example.com";

                $staff = $this->upsertUser($email, $role['label'] . ' (' . ucfirst($type) . ' Test)', [
                    'cura_role' => 'sales_person',
                    'staff_role' => $roleKey,
                    'created_by_user_id' => $vendor->id,
                    'assigned_pharmacy_id' => $pharmacy->id,
                    'is_active' => 1,
                ]);

                $this->command?->info("  - {$role['label']}: {$email} / " . self::PASSWORD);
            }
        }
    }

    protected function upsertUser(string $email, string $fullName, array $attributes): User
    {
        $nameParts = preg_split('/\s+/', trim($fullName), 2);

        $existing = User::where('email', $email)->first();

        $data = array_merge([
            'full_name' => $fullName,
            'first_name' => $nameParts[0],
            'last_name' => $nameParts[1] ?? $nameParts[0],
            'phone' => '',
            'address' => '',
            'password_hash' => bcrypt(self::PASSWORD),
            'password' => '',
        ], $attributes);

        if ($existing) {
            DB::table('users')->where('id', $existing->id)->update($data + ['updated_at' => now()]);

            return $existing->fresh();
        }

        $id = DB::table('users')->insertGetId($data + [
            'email' => $email,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::find($id);
    }
}

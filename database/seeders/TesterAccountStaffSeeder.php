<?php

namespace Database\Seeders;

use App\Models\Pharmacy;
use App\Models\User;
use App\Support\StaffRoles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Creates one staff login per role available to tester@gmail.com's own
 * facility, so role-based access can be clicked through under the account
 * actually being used for manual testing. Safe to re-run: upserts by email.
 */
class TesterAccountStaffSeeder extends Seeder
{
    const OWNER_EMAIL = 'tester@gmail.com';
    const PASSWORD = 'password123';

    public function run(): void
    {
        $vendor = User::where('email', self::OWNER_EMAIL)->first();

        if (! $vendor) {
            $this->command?->error(self::OWNER_EMAIL . ' not found — nothing to seed.');
            return;
        }

        $pharmacy = Pharmacy::where('user_id', $vendor->id)->first();

        if (! $pharmacy) {
            $this->command?->error(self::OWNER_EMAIL . ' has no facility record yet — finish onboarding first.');
            return;
        }

        $type = strtolower($pharmacy->type ?? 'pharmacy') === 'hospital' ? 'hospital' : 'pharmacy';

        $this->command?->info("Seeding {$type} staff for {$pharmacy->name} (owner: " . self::OWNER_EMAIL . ')');

        foreach (StaffRoles::forFacilityType($type) as $roleKey => $role) {
            $email = "tester-staff-{$roleKey}@example.com";

            $this->upsertStaff($email, $role['label'] . ' (Tester Account)', $vendor, $pharmacy, $roleKey);

            $this->command?->info("  - {$role['label']}: {$email} / " . self::PASSWORD);
        }
    }

    protected function upsertStaff(string $email, string $fullName, User $vendor, Pharmacy $pharmacy, string $roleKey): void
    {
        $nameParts = preg_split('/\s+/', trim($fullName), 2);

        $data = [
            'full_name' => $fullName,
            'first_name' => $nameParts[0],
            'last_name' => $nameParts[1] ?? $nameParts[0],
            'phone' => '',
            'address' => '',
            'password_hash' => bcrypt(self::PASSWORD),
            'password' => '',
            'cura_role' => 'sales_person',
            'staff_role' => $roleKey,
            'created_by_user_id' => $vendor->id,
            'assigned_pharmacy_id' => $pharmacy->id,
            'is_active' => 1,
        ];

        $existing = User::where('email', $email)->first();

        if ($existing) {
            DB::table('users')->where('id', $existing->id)->update($data + ['updated_at' => now()]);
            return;
        }

        DB::table('users')->insertGetId($data + [
            'email' => $email,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

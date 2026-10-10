<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Pharmacy;
use App\Models\Medication;
use App\Models\Reservation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create a Vendor User
        $vendor = User::create([
            'name' => 'MedPlus Pharmacist',
            'email' => 'vendor@medplus.com',
            'phone' => '08012345678',
            'role' => 'vendor',
            'password' => Hash::make('password'),
        ]);

        // 2. Create a Pharmacy for the Vendor
        $pharmacy = Pharmacy::create([
            'user_id' => $vendor->id,
            'name' => 'MedPlus Pharmacy, Victoria Island',
            'address' => '123 Admiralty Way, VI',
            'city' => 'Lagos',
            'state' => 'Lagos',
            'phone' => '01-2345678',
            'email' => 'vi@medplus.com',
            'latitude' => 6.4253,
            'longitude' => 3.4419,
            'is_verified' => true,
        ]);

        // 3. Create Medications for the Pharmacy
        $meds = [
            [
                'name' => 'Amoxicillin 250mg',
                'description' => 'Antibiotic for bacterial infections.',
                'price' => 1500.00,
                'stock_level' => 50,
                'dosage' => '250mg',
                'category' => 'Antibiotics',
            ],
            [
                'name' => 'Paracetamol 500mg',
                'description' => 'Pain reliever and fever reducer.',
                'price' => 500.00,
                'stock_level' => 100,
                'dosage' => '500mg',
                'category' => 'Painkillers',
            ],
            [
                'name' => 'Vitamin C 1000mg',
                'description' => 'Immune system booster.',
                'price' => 2000.00,
                'stock_level' => 80,
                'dosage' => '1000mg',
                'category' => 'Supplements',
            ],
            [
                'name' => 'Insulin Glargine',
                'description' => 'Long-acting insulin for diabetes management.',
                'price' => 8500.00,
                'stock_level' => 12,
                'dosage' => '100Units/mL',
                'category' => 'Diabetes',
            ],
        ];

        foreach ($meds as $med) {
            Medication::create(array_merge($med, ['pharmacy_id' => $pharmacy->id]));
        }

        // 4. Create a Patient User
        $patient = User::create([
            'name' => 'Adebayo Johnson',
            'email' => 'user@cura.com',
            'phone' => '08098765432',
            'role' => 'patient',
            'password' => Hash::make('password'),
        ]);

        // 5. Create a Demo Reservation
        Reservation::create([
            'user_id' => $patient->id,
            'medication_id' => Medication::where('name', 'Amoxicillin 250mg')->first()->id,
            'quantity' => 2,
            'status' => 'ready',
            'expires_at' => Carbon::now()->addHours(18),
            'pickup_code' => strtoupper(Str::random(8)),
        ]);
    }
}

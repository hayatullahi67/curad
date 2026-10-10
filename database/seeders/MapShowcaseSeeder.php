<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Pharmacy;
use App\Models\Medication;

class MapShowcaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Ensure we have a default admin user to own pharmacies
        $admin = User::firstOrCreate(
            ['email' => 'admin@cura.com'],
            [
                'name' => 'Cura Admin',
                'password' => bcrypt('password'),
                'role' => 'admin',
                'wallet_balance' => 50000
            ]
        );

        // 2. Create Pharmacies in Key Nigeria Locations
        $pharmacies = [
            // Lagos
            [
                'name' => 'HealthPlus Pharmacy (Ikeja)',
                'address' => 'Isaac John Street, Ikeja GRA, Lagos',
                'phone' => '08012345671',
                'user_id' => $admin->id,
                'latitude' => 6.5765,
                'longitude' => 3.3557,
                'is_verified' => true,
            ],
            [
                'name' => 'Medplus Pharmacy (Lekki)',
                'address' => 'Admiralty Way, Lekki Phase 1, Lagos',
                'phone' => '08012345672',
                'user_id' => $admin->id,
                'latitude' => 6.4500,
                'longitude' => 3.4680,
                'is_verified' => true,
            ],
            // Abuja
            [
                'name' => 'Maitama Specialist Hub',
                'address' => 'Ganges Street, Maitama, Abuja',
                'phone' => '09012341111',
                'user_id' => $admin->id,
                'latitude' => 9.0883,
                'longitude' => 7.5029,
                'is_verified' => true,
            ],
            [
                'name' => 'Wuse II Medical Center',
                'address' => 'Adetokunbo Ademola Crescent, Wuse II, Abuja',
                'phone' => '09012342222',
                'user_id' => $admin->id,
                'latitude' => 9.0765,
                'longitude' => 7.4764,
                'is_verified' => true,
            ],
            [
                'name' => 'Garki District Pharmacy',
                'address' => 'Area 11, Garki, Abuja',
                'phone' => '09012343333',
                'user_id' => $admin->id,
                'latitude' => 9.0223,
                'longitude' => 7.4820,
                'is_verified' => true,
            ],
            // Jos
            [
                'name' => 'Terminus Healthcare Jos',
                'address' => 'Ahmadu Bello Way, Jos Terminus, Plateau',
                'phone' => '07012345555',
                'user_id' => $admin->id,
                'latitude' => 9.9167,
                'longitude' => 8.8833,
                'is_verified' => true,
            ],
            [
                'name' => 'Rayfield Medical Clinic',
                'address' => 'Old Airport Road, Rayfield, Jos',
                'phone' => '07012346666',
                'user_id' => $admin->id,
                'latitude' => 9.8517,
                'longitude' => 8.9189,
                'is_verified' => true,
            ],
            [
                'name' => 'Bukuru Distribution Hub',
                'address' => 'Vom Road, Bukuru, Jos',
                'phone' => '07012347777',
                'user_id' => $admin->id,
                'latitude' => 9.7917,
                'longitude' => 8.8667,
                'is_verified' => true,
            ],
        ];

        foreach ($pharmacies as $pData) {
            $pharmacy = Pharmacy::updateOrCreate(
                [
                    'name' => $pData['name'],
                    'user_id' => $pData['user_id']
                ],
                $pData
            );

            // 4. Seed Medications for EACH Pharmacy
            $medications = [
                ['name' => 'Paracetamol 500mg', 'category' => 'Pain Relief', 'dosage' => '2 tablets daily', 'price' => 500, 'stock_level' => 50],
                ['name' => 'Amoxicillin 500mg', 'category' => 'Antibiotics', 'dosage' => '1 tablet every 8 hours', 'price' => 2500, 'stock_level' => 20],
                ['name' => 'Ciprofloxacin 500mg', 'category' => 'Antibiotics', 'dosage' => '1 tablet twice daily', 'price' => 3000, 'stock_level' => 15],
                ['name' => 'Vitamin C 1000mg', 'category' => 'Supplements', 'dosage' => '1 tablet daily', 'price' => 1500, 'stock_level' => 100],
                ['name' => 'Ibuprofen 400mg', 'category' => 'Pain Relief', 'dosage' => '1 tablet every 6 hours', 'price' => 800, 'stock_level' => 45],
                ['name' => 'Artemether Lumefantrine', 'category' => 'Malaria', 'dosage' => 'Complete course', 'price' => 4500, 'stock_level' => 30],
                ['name' => 'Loratadine 10mg', 'category' => 'Allergy', 'dosage' => '1 tablet daily', 'price' => 1200, 'stock_level' => 25],
            ];

            foreach ($medications as $medData) {
                // Add slight price variation per pharmacy for realism
                $priceVariation = rand(-200, 300);
                
                Medication::updateOrCreate(
                    [
                        'pharmacy_id' => $pharmacy->id,
                        'name' => $medData['name'],
                    ],
                    [
                        'category' => $medData['category'],
                        'description' => "High quality {$medData['name']} from {$pharmacy->name}.",
                        'dosage' => $medData['dosage'],
                        'price' => max(100, $medData['price'] + $priceVariation),
                        'stock_level' => $medData['stock_level'],
                        'photo' => null 
                    ]
                );
            }
        }
    }
}

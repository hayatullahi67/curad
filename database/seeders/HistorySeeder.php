<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = \App\Models\User::all();
        $medications = \App\Models\Medication::all();

        if ($medications->isEmpty()) return;

        foreach ($users as $user) {
            // Seed Wallet Transactions
            for ($i = 0; $i < 5; $i++) {
                \App\Models\WalletTransaction::create([
                    'user_id' => $user->id,
                    'amount' => rand(500, 5000),
                    'type' => rand(0, 1) ? 'credit' : 'debit',
                    'description' => $i % 2 == 0 ? 'Pharmacy Reservation Fee' : 'Wallet Top-up via Paystack',
                    'reference' => 'TXN-' . strtoupper(bin2hex(random_bytes(4))),
                    'created_at' => now()->subDays(rand(1, 10)),
                ]);
            }

            // Seed Reservation History
            for ($i = 0; $i < 3; $i++) {
                $med = $medications->random();
                \App\Models\Reservation::create([
                    'user_id' => $user->id,
                    'medication_id' => $med->id,
                    'quantity' => rand(1, 3),
                    'status' => ['picked_up', 'cancelled', 'expired'][rand(0, 2)],
                    'pickup_code' => 'CURA-' . strtoupper(bin2hex(random_bytes(3))),
                    'expires_at' => now()->subDays(rand(1, 5)),
                    'is_paid' => true,
                    'created_at' => now()->subDays(rand(6, 15)),
                ]);
            }
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (\App\Models\User::all() as $user) {
            \App\Models\Notification::create([
                'user_id' => $user->id,
                'type' => 'SECURITY',
                'title' => 'System Initialized',
                'message' => 'Your CURA account is now active and protected by our security protocols.',
                'created_at' => now()->subHours(5),
            ]);
            \App\Models\Notification::create([
                'user_id' => $user->id,
                'type' => 'STOCK',
                'title' => 'Inventory Sync',
                'message' => 'Real-time stock monitoring is now active for your region. Stock levels are updated every 15 minutes.',
                'created_at' => now()->subHours(2),
            ]);
            \App\Models\Notification::create([
                'user_id' => $user->id,
                'type' => 'FINANCE',
                'title' => 'Wallet Connected',
                'message' => 'Your Valu Wallet is linked and ready for secure pharmacy transactions.',
                'created_at' => now()->subMinutes(30),
            ]);
        }
    }
}

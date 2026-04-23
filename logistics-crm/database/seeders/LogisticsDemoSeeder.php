<?php

namespace Database\Seeders;

use App\Models\Captain;
use App\Models\Order;
use Illuminate\Database\Seeder;

/**
 * بيانات تجريبية للتطوير (تشغيل: php artisan db:seed --class=LogisticsDemoSeeder).
 */
class LogisticsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $captain = Captain::query()->firstOrCreate(
            ['code' => 'CAP-001'],
            [
                'full_name' => 'كابتن تجريبي',
                'phone' => '218941234567',
                'vehicle_type' => 'van',
                'is_active' => true,
            ]
        );

        Order::query()->firstOrCreate(
            ['reference' => 'ORD-DEMO-001'],
            [
                'captain_id' => $captain->id,
                'status' => Order::STATUS_IN_TRANSIT,
                'promised_delivery_at' => now()->subDays(7),
                'customer_phone' => '218911223344',
                'notes' => 'Seeded delayed order for CRM tests',
            ]
        );

        Order::query()->firstOrCreate(
            ['reference' => 'ORD-DEMO-002'],
            [
                'captain_id' => null,
                'status' => Order::STATUS_PENDING,
                'promised_delivery_at' => now()->addDay(),
            ]
        );
    }
}

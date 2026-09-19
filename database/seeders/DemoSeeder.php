<?php

namespace Database\Seeders;

use App\Models\ArtworkVersion;
use App\Models\Customer;
use App\Models\Order;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo content for local development / training.
 * Run explicitly:  php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Users (assumes roles + admin from ProductionSeeder)
        $admin = User::where('email', 'like', '%@%')->where('is_staff', true)->first()
            ?? User::factory()->create(['name' => 'Admin', 'is_staff' => true]);

        $artworker = User::firstOrCreate(
            ['email' => 'artworker@example.test'],
            ['name' => 'Art Worker', 'password' => Hash::make('password'), 'is_staff' => true]
        );
        $artworker->assignRole('artworker');

        $storekeeper = User::firstOrCreate(
            ['email' => 'store@example.test'],
            ['name' => 'Store Keeper', 'password' => Hash::make('password'), 'is_staff' => true]
        );
        $storekeeper->assignRole('storekeeper');

        // Customer + portal user
        $customer = Customer::firstOrCreate(
            ['name' => 'Acme Corp'],
            ['contact_name' => 'Wile E.', 'email' => 'acme@example.test']
        );

        $portalUser = User::firstOrCreate(
            ['email' => 'portal@acme.test'],
            ['name' => 'Acme Buyer', 'password' => Hash::make('password'), 'is_staff' => false, 'customer_id' => $customer->id]
        );
        $portalUser->assignRole('customer');

        // Suppliers & stock
        $supplier = Supplier::firstOrCreate(['name' => 'Blank Apparel Ltd'], ['email' => 'sales@blankapparel.test']);

        $tshirtBlack = StockItem::firstOrCreate(
            ['sku' => 'GAR-TSHI-BLK-M'],
            ['name' => 'T-shirt black M', 'type' => 'garment', 'category' => 'T-shirts', 'unit' => 'each',
             'qty_on_hand' => 250, 'reorder_level' => 50, 'reorder_qty' => 100, 'supplier_id' => $supplier->id, 'cost' => 250]
        );

        StockItem::firstOrCreate(
            ['sku' => 'CON-INK-WHT-1L'],
            ['name' => 'White plastisol ink 1L', 'type' => 'consumable', 'category' => 'Inks', 'unit' => 'each',
             'qty_on_hand' => 4, 'reorder_level' => 5, 'reorder_qty' => 12, 'supplier_id' => $supplier->id, 'cost' => 1800]
        );

        // Orders
        $order1 = Order::firstOrCreate(
            ['number' => 'JOB-2026-00001'],
            [
                'uuid' => (string) Str::uuid(),
                'customer_id' => $customer->id,
                'type' => 'print',
                'reference' => 'ACME-SUMMER',
                'due_date' => now()->addDays(10),
                'quantity' => 100,
                'price' => 45000,
                'customer_notes' => 'Front print only, Pantone 485C red',
            ]
        );

        Order::firstOrCreate(
            ['number' => 'JOB-2026-00002'],
            [
                'uuid' => (string) Str::uuid(),
                'customer_id' => $customer->id,
                'type' => 'embroidery',
                'due_date' => now()->addDays(4),
                'quantity' => 300,
                'price' => 120000,
                'stage' => 'artwork',
            ]
        );

        // Artwork
        ArtworkVersion::firstOrCreate(
            ['order_id' => $order1->id, 'version_number' => 1],
            ['status' => 'awaiting_customer', 'is_final' => false, 'created_by' => $artworker->id, 'notes' => 'Initial proof']
        );

        // Allocation with deliberate shortfall for demo purposes
        app(\App\Services\StockService::class)->reserveForOrder($order1, $tshirtBlack, 60, $admin);

        $this->command?->info('Seeded demo: artworker@example.test, store@example.test, portal@acme.test (all "password"), JOB-2026-00001/00002, Acme Corp, stock items.');
    }
}

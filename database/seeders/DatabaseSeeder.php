<?php

namespace Database\Seeders;

use App\Models\ArtworkVersion;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductionStage;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ----- Roles & permissions -----
        $roles = ['super_admin', 'manager', 'artworker', 'print_operator', 'embroidery_operator', 'storekeeper', 'customer'];
        foreach ($roles as $role) {
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role]);
        }

        // ----- Pipeline stages -----
        $stages = [
            ['key' => 'new_order', 'name' => 'New order', 'position' => 1, 'is_default_start' => true],
            ['key' => 'artwork', 'name' => 'Artwork', 'position' => 2, 'gate_requires_final_artwork' => false],
            ['key' => 'pre_production', 'name' => 'Pre-production', 'position' => 3, 'gate_requires_final_artwork' => true, 'gate_requires_stock' => true, 'gate_requires_digitised_file' => true],
            ['key' => 'printing', 'name' => 'Printing', 'position' => 4, 'gate_requires_final_artwork' => true],
            ['key' => 'embroidery', 'name' => 'Embroidery', 'position' => 5, 'gate_requires_final_artwork' => true],
            ['key' => 'finishing', 'name' => 'Finishing', 'position' => 6],
            ['key' => 'qc', 'name' => 'QC', 'position' => 7],
            ['key' => 'packing', 'name' => 'Packing', 'position' => 8],
            ['key' => 'complete', 'name' => 'Complete', 'position' => 9, 'is_terminal' => true],
        ];

        foreach ($stages as $stage) {
            ProductionStage::updateOrCreate(['key' => $stage['key']], $stage);
        }

        // ----- Users -----
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.test'],
            ['name' => 'Admin User', 'password' => Hash::make('password'), 'is_staff' => true]
        );
        $admin->assignRole('super_admin');

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

        // ----- Customer + portal user -----
        $customer = Customer::firstOrCreate(
            ['name' => 'Acme Corp'],
            ['contact_name' => 'Wile E.', 'email' => 'acme@example.test']
        );

        $portalUser = User::firstOrCreate(
            ['email' => 'portal@acme.test'],
            ['name' => 'Acme Buyer', 'password' => Hash::make('password'), 'is_staff' => false, 'customer_id' => $customer->id]
        );
        $portalUser->assignRole('customer');

        // ----- Suppliers & stock -----
        $supplier = Supplier::firstOrCreate(['name' => 'Blank Apparel Ltd'], ['email' => 'sales@blankapparel.test']);

        $tshirtBlack = StockItem::firstOrCreate(
            ['sku' => 'GAR-TSHI-BLK-M'],
            ['name' => 'T-shirt black M', 'type' => 'garment', 'category' => 'T-shirts', 'unit' => 'each',
             'qty_on_hand' => 250, 'reorder_level' => 50, 'reorder_qty' => 100, 'supplier_id' => $supplier->id, 'cost' => 250]
        );

        $inkWhite = StockItem::firstOrCreate(
            ['sku' => 'CON-INK-WHT-1L'],
            ['name' => 'White plastisol ink 1L', 'type' => 'consumable', 'category' => 'Inks', 'unit' => 'each',
             'qty_on_hand' => 4, 'reorder_level' => 5, 'reorder_qty' => 12, 'supplier_id' => $supplier->id, 'cost' => 1800]
        );

        // ----- Orders -----
        $order1 = Order::firstOrCreate(
            ['number' => 'JOB-2026-00001'],
            [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
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
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'customer_id' => $customer->id,
                'type' => 'embroidery',
                'due_date' => now()->addDays(4),
                'quantity' => 300,
                'price' => 120000,
                'stage' => 'artwork',
            ]
        );

        // ----- Artwork -----
        $v1 = ArtworkVersion::firstOrCreate(
            ['order_id' => $order1->id, 'version_number' => 1],
            ['status' => 'awaiting_customer', 'is_final' => false, 'created_by' => $artworker->id, 'notes' => 'Initial proof']
        );

        // ----- Allocations -----
        app(\App\Services\StockService::class)->reserveForOrder($order1, $tshirtBlack, 60, $admin);
        // Deliberately leaves a 40-unit shortfall on JOB-2026-00001 for demo purposes

        $this->command?->info('Seeded: admin@example.test / password (super admin), artworker@example.test, store@example.test, portal@acme.test (portal user)');
    }
}

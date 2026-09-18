<?php

use App\Models\ArtworkVersion;
use App\Models\Customer;
use App\Models\ProductionStage;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;

beforeEach(function () {
    foreach (['super_admin', 'manager', 'artworker', 'print_operator', 'embroidery_operator', 'storekeeper', 'customer'] as $role) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    ProductionStage::create(['key' => 'new_order', 'name' => 'New order', 'position' => 1, 'is_default_start' => true]);
    ProductionStage::create(['key' => 'pre_production', 'name' => 'Pre-production', 'position' => 2, 'gate_requires_final_artwork' => true]);
    ProductionStage::create(['key' => 'complete', 'name' => 'Complete', 'position' => 3, 'is_terminal' => true]);

    $this->staff = User::create(['name' => 'Staff', 'email' => 'staff@test.test', 'password' => 'x', 'is_staff' => true]);
    $this->staff->assignRole('super_admin');

    $this->customer = Customer::create(['name' => 'Test Co']);
    $this->portalUser = User::create(['name' => 'Buyer', 'email' => 'buyer@test.test', 'password' => 'x', 'is_staff' => false, 'customer_id' => $this->customer->id]);
    $this->portalUser->assignRole('customer');

    $this->order = \App\Models\Order::create([
        'customer_id' => $this->customer->id,
        'type' => 'print',
        'due_date' => now()->addDays(7),
        'quantity' => 50,
        'customer_notes' => 'Front print',
    ]);

    $supplier = Supplier::create(['name' => 'Sup']);
    $item = StockItem::create(['sku' => 'GAR-S-001', 'name' => 'Test tee', 'type' => 'garment', 'unit' => 'each', 'qty_on_hand' => 100, 'reorder_level' => 10, 'supplier_id' => $supplier->id]);

    app(\App\Services\StockService::class)->reserveForOrder($this->order, $item, 50, $this->staff);

    ArtworkVersion::create([
        'order_id' => $this->order->id,
        'version_number' => 1,
        'status' => App\Enums\ArtworkStatus::AwaitingCustomer,
        'created_by' => $this->staff->id,
    ]);
});

it('renders all staff pages', function () {
    $pages = [
        '/admin',
        '/admin/orders',
        '/admin/orders/create',
        "/admin/orders/{$this->order->id}",
        "/admin/orders/{$this->order->id}/edit",
        '/admin/production-board',
        '/admin/stock-items',
        '/admin/stock-items/create',
        '/admin/stock-items/1',
        '/admin/purchase-orders',
        '/admin/purchase-orders/create',
        '/admin/customers',
        '/admin/customers/create',
        '/admin/customers/1',
        '/admin/production-stages',
        '/admin/scan-page' /* not real, ignore */,
    ];

    foreach (array_unique($pages) as $page) {
        if (str_contains($page, 'scan-page')) {
            continue;
        }
        $response = $this->actingAs($this->staff)->get($page);
        if ($response->status() !== 200) {
            fwrite(STDERR, "[smoke] failing page: {$page} => ".$response->status().' -> '.$response->headers->get('Location')."\n");
        }
        $response->assertStatus(200);
    }
});

it('renders staff-only management pages', function () {
    $this->actingAs($this->staff)->get('/admin/users')->assertStatus(200);
    $this->actingAs($this->staff)->get('/admin/users/create')->assertStatus(200);
});

it('renders the scan page for staff', function () {
    $this->actingAs($this->staff)->get('/scan')->assertStatus(200);
});

it('renders job ticket pdf', function () {
    $response = $this->actingAs($this->staff)->get("/orders/{$this->order->id}/job-ticket");

    $response->assertStatus(200);
    expect($response->headers->get('content-type'))->toContain('pdf');
});

it('renders stock label pdf', function () {
    $response = $this->actingAs($this->staff)->get('/stock-items/1/label');

    $response->assertStatus(200);
    expect($response->headers->get('content-type'))->toContain('pdf');
});

it('renders portal pages for customer users', function () {
    $this->actingAs($this->portalUser)->get('/portal')->assertStatus(200);
    $this->actingAs($this->portalUser)->get('/portal/orders')->assertStatus(200);
    $this->actingAs($this->portalUser)->get("/portal/orders/{$this->order->id}")->assertStatus(200);
});

it('prevents portal users from the staff panel', function () {
    $this->actingAs($this->portalUser)->get('/admin')->assertForbidden();
});

it('prevents staff from the portal panel', function () {
    $this->actingAs($this->staff)->get('/portal')->assertForbidden();
});

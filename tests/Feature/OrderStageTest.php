<?php

use App\Enums\ArtworkStatus;
use App\Enums\StageKey;
use App\Models\ArtworkVersion;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductionStage;
use App\Models\StockItem;
use App\Models\User;
use App\Services\ArtworkService;
use App\Services\OrderStageService;
use App\Services\StockService;

beforeEach(function () {
    foreach (
        [
            ['key' => 'new_order', 'name' => 'New order', 'position' => 1, 'is_default_start' => true],
            ['key' => 'pre_production', 'name' => 'Pre-production', 'position' => 2, 'gate_requires_final_artwork' => true, 'gate_requires_stock' => true, 'gate_requires_digitised_file' => true],
            ['key' => 'printing', 'name' => 'Printing', 'position' => 3, 'gate_requires_final_artwork' => true],
            ['key' => 'complete', 'name' => 'Complete', 'position' => 4, 'is_terminal' => true],
        ] as $stage
    ) {
        ProductionStage::create($stage);
    }

    $this->customer = Customer::create(['name' => 'Test Co']);
    $this->user = User::create(['name' => 'Staff', 'email' => 'staff@test.test', 'password' => 'x', 'is_staff' => true]);

    $this->order = Order::create([
        'uuid' => (string) Illuminate\Support\Str::uuid(),
        'customer_id' => $this->customer->id,
        'type' => 'print',
        'due_date' => now()->addDays(7),
        'quantity' => 50,
    ]);
});

it('starts in new_order and can move freely when no gates apply', function () {
    $service = app(OrderStageService::class);
    $service->moveTo($this->order, StageKey::Complete, $this->user, 'done');

    expect($this->order->refresh()->stage)->toBe(StageKey::Complete)
        ->and($this->order->stageHistory()->count())->toBe(1)
        ->and($this->order->stageHistory()->first()->note)->toBe('done');
});

it('blocks moving to a gated stage without final artwork', function () {
    $service = app(OrderStageService::class);

    $service->moveTo($this->order, StageKey::PreProduction, $this->user);
})->throws(InvalidArgumentException::class);

it('blocks moving to a gated stage without full stock allocation', function () {
    // Approve artwork
    $version = ArtworkVersion::create([
        'order_id' => $this->order->id,
        'version_number' => 1,
        'status' => ArtworkStatus::Approved,
        'is_final' => true,
        'created_by' => $this->user->id,
        'approved_by' => $this->user->id,
    ]);

    app(OrderStageService::class)->moveTo($this->order, StageKey::PreProduction, $this->user);
})->throws(InvalidArgumentException::class);

it('allows moving to a gated stage when artwork, stock and digitised file are in place', function () {
    ArtworkVersion::create([
        'order_id' => $this->order->id,
        'version_number' => 1,
        'status' => ArtworkStatus::Approved,
        'is_final' => true,
        'created_by' => $this->user->id,
        'approved_by' => $this->user->id,
    ]);

    $item = StockItem::create([
        'sku' => 'GAR-TEST-001', 'name' => 'Test tee', 'type' => 'garment', 'unit' => 'each',
        'qty_on_hand' => 100, 'reorder_level' => 10,
    ]);

    app(StockService::class)->reserveForOrder($this->order, $item, 50, $this->user);

    $doc = $this->order->documents()->create(['category' => 'digitised', 'name' => 'dst', 'uploaded_by' => $this->user->id]);

    app(OrderStageService::class)->moveTo($this->order, StageKey::PreProduction, $this->user);

    expect($this->order->refresh()->stage)->toBe(StageKey::PreProduction)
        ->and($item->refresh()->qty_reserved)->toBe(50)
        ->and($item->qty_available)->toBe(50);
});

it('reservations respect available stock and leave backorder allocations', function () {
    $item = StockItem::create([
        'sku' => 'GAR-TEST-002', 'name' => 'Test tee 2', 'type' => 'garment', 'unit' => 'each',
        'qty_on_hand' => 10, 'reorder_level' => 5,
    ]);

    $service = app(StockService::class);
    $service->reserveForOrder($this->order, $item, 30, $this->user);

    expect($item->refresh()->qty_reserved)->toBe(10)
        ->and($item->qty_available)->toBe(0)
        ->and($this->order->refresh()->total_allocated)->toBe(30)
        ->and($this->order->shortfall_qty)->toBe(20);
});

it('releases reservations when released', function () {
    $item = StockItem::create([
        'sku' => 'GAR-TEST-003', 'name' => 'Test tee 3', 'type' => 'garment', 'unit' => 'each',
        'qty_on_hand' => 100, 'reorder_level' => 10,
    ]);

    $service = app(StockService::class);
    $allocation = $service->reserveForOrder($this->order, $item, 50, $this->user);
    $service->release($allocation, $this->user);

    expect($item->refresh()->qty_reserved)->toBe(0)
        ->and($allocation->refresh()->status)->toBe('released');
});

it('only one artwork version can be final', function () {
    $v1 = ArtworkVersion::create([
        'order_id' => $this->order->id, 'version_number' => 1,
        'status' => ArtworkStatus::Approved, 'is_final' => true, 'created_by' => $this->user->id,
    ]);

    $v2 = ArtworkVersion::create([
        'order_id' => $this->order->id, 'version_number' => 2,
        'status' => ArtworkStatus::Draft, 'created_by' => $this->user->id,
    ]);

    app(ArtworkService::class)->approve($v2, $this->user);

    expect($v1->refresh()->is_final)->toBeFalse()
        ->and($v1->refresh()->status)->toBe(ArtworkStatus::Superseded)
        ->and($v2->refresh()->is_final)->toBeTrue();
});

it('generates sequential job numbers', function () {
    $o2 = Order::create([
        'uuid' => (string) Illuminate\Support\Str::uuid(),
        'customer_id' => $this->customer->id,
        'type' => 'print',
        'due_date' => now()->addDays(7),
        'quantity' => 10,
    ]);

    expect($o2->number)->not->toBe($this->order->number)
        ->and($o2->number)->toMatch('/^JOB-\d{4}-\d{5}$/');
});

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // suppliers + stock_items are created in 180001 so that
        // order_lines.stock_item_id (created here) can reference them.

        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->string('type', 20)->default('garment');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('price')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('order_stock_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_line_id')->nullable()->constrained('order_lines')->nullOnDelete();
            $table->unsignedInteger('qty_allocated')->default(0);
            $table->unsignedInteger('qty_issued')->default(0);
            $table->string('status', 30)->default('reserved');
            // purchase_orders doesn't exist yet — FK added in 180003
            $table->unsignedBigInteger('purchase_order_id')->nullable()->index();
            $table->timestamps();
            $table->index(['order_id', 'status']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->integer('qty');
            $table->unsignedInteger('qty_on_hand_after');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['stock_item_id', 'created_at']);
        });

        Schema::create('number_sequences', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->unsignedBigInteger('value')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('order_stock_allocations');
        Schema::dropIfExists('order_lines');
    }
};

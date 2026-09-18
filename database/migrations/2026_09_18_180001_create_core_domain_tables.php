<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('remember_token')->constrained()->nullOnDelete();
            $table->boolean('is_staff')->default(true)->after('customer_id');
            $table->string('phone')->nullable()->after('is_staff');
        });

        Schema::create('production_stages', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->unsignedInteger('position');
            $table->boolean('gate_requires_final_artwork')->default(false);
            $table->boolean('gate_requires_stock')->default(false);
            $table->boolean('gate_requires_digitised_file')->default(false);
            $table->boolean('is_default_start')->default(false);
            $table->boolean('is_terminal')->default(false);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('number')->unique();
            $table->foreignId('customer_id')->constrained();
            $table->string('type', 20)->default('print');
            $table->string('stage', 40)->default('new_order');
            $table->string('reference')->nullable();
            $table->date('due_date');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('price')->default(0);
            $table->unsignedInteger('paid')->default(0);
            $table->text('notes')->nullable();
            $table->text('customer_notes')->nullable();
            $table->timestamps();
            $table->index(['stage', 'due_date']);
        });

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

        Schema::create('order_stage_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_stage', 40)->nullable();
            $table->string('to_stage', 40);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('artwork_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 30)->default('draft');
            $table->boolean('is_final')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'version_number']);
        });

        Schema::create('order_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 40)->default('general');
            $table->string('name');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_documents');
        Schema::dropIfExists('artwork_versions');
        Schema::dropIfExists('order_stage_history');
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('production_stages');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['customer_id', 'is_staff', 'phone']);
        });
        Schema::dropIfExists('customers');
    }
};

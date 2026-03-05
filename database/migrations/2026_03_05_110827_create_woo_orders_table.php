<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('woo_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('woo_shop_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('external_order_id');
            $table->string('order_number')->nullable();
            $table->string('status', 50);
            $table->string('currency', 10)->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->timestamp('order_created_at')->nullable();
            $table->timestamp('order_paid_at')->nullable();
            $table->timestamps();

            $table->unique(['woo_shop_id', 'external_order_id']);
            $table->index(['woo_shop_id', 'order_created_at']);
            $table->index(['woo_shop_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('woo_orders');
    }
};

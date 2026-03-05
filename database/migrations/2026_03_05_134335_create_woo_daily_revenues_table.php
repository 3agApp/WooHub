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
        Schema::create('woo_daily_revenues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('woo_shop_id')->constrained()->cascadeOnDelete();
            $table->date('revenue_date');
            $table->string('currency', 10)->default('CHF');
            $table->decimal('revenue_total', 14, 2)->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->timestamps();

            $table->unique(['woo_shop_id', 'revenue_date', 'currency']);
            $table->index(['woo_shop_id', 'revenue_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('woo_daily_revenues');
    }
};

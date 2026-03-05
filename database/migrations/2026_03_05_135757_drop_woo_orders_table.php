<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('woo_orders')) {
            Schema::drop('woo_orders');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};

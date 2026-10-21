<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `total_amount` always stores the amount actually charged after the
     * discount, i.e. MAX(0, amount - discount_amount).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('discount_amount', 12, 2)->default(0)->after('amount');
            $table->foreignId('promo_voucher_id')->nullable()->after('voucher_id')->constrained('promo_vouchers')->nullOnDelete();
            $table->string('promo_code', 50)->nullable()->after('promo_voucher_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promo_voucher_id');
            $table->dropColumn(['promo_code', 'discount_amount']);
        });
    }
};

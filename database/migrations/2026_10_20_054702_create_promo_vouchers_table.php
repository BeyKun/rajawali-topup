<?php

use App\Enums\DiscountType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Promo vouchers are marketing discounts (applied to the order total),
     * completely separate from the physical Telkomsel `vouchers` inventory.
     */
    public function up(): void
    {
        Schema::create('promo_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->enum('discount_type', DiscountType::cases())->default(DiscountType::Fixed->value);
            $table->decimal('discount_value', 12, 2);
            $table->decimal('max_discount_amount', 12, 2)->nullable();
            $table->decimal('min_spend', 12, 2)->default(0);
            $table->integer('usage_limit')->nullable();
            $table->integer('used_count')->default(0);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_vouchers');
    }
};

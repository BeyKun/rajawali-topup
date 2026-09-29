<?php

use App\Enums\PaymentStatus;
use App\Enums\RedeemStatus;
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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 50)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->string('msisdn', 20);
            $table->decimal('amount', 12, 2);
            $table->decimal('admin_fee', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->string('payment_channel', 30)->default('QRIS');
            $table->enum('payment_status', PaymentStatus::cases())->default(PaymentStatus::Unpaid->value);
            $table->enum('redeem_status', RedeemStatus::cases())->default(RedeemStatus::Pending->value);
            $table->text('qris_string')->nullable();
            $table->text('qris_url')->nullable();
            $table->timestamp('qris_expired_at')->nullable();
            $table->string('payment_ref_id', 100)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('redeem_response_code', 50)->nullable();
            $table->json('redeem_response_raw')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();

            $table->index(['payment_status', 'redeem_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

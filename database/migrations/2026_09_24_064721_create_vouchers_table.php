<?php

use App\Enums\VoucherStatus;
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
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('serial_number', 50)->unique();
            $table->text('hrn');
            $table->enum('status', VoucherStatus::cases())->default(VoucherStatus::Available->value);
            $table->string('validity', 50)->nullable();
            $table->string('expired_date', 50)->nullable();
            $table->string('region', 100)->nullable();
            $table->json('telkomsel_check_response')->nullable();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->string('redeemed_msisdn', 20)->nullable();
            $table->string('telkomsel_trace_id', 100)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};

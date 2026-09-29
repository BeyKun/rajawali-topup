<?php

use App\Enums\PaymentStatus;
use App\Enums\RedeemStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_payment_status_check');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_status_check CHECK (payment_status IN ('UNPAID', 'PAID', 'EXPIRED', 'FAILED', 'CANCELED'))");

            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_redeem_status_check');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_redeem_status_check CHECK (redeem_status IN ('PENDING', 'PROCESSING', 'SUCCESS', 'FAILED', 'CANCELED'))");
        }

        // Migrate previously abandoned/cancelled orders where payment was expired and redeem remained pending
        DB::table('orders')
            ->where('payment_status', 'EXPIRED')
            ->where('redeem_status', 'PENDING')
            ->update([
                'payment_status' => PaymentStatus::Canceled->value,
                'redeem_status' => RedeemStatus::Canceled->value,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_payment_status_check');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_status_check CHECK (payment_status IN ('UNPAID', 'PAID', 'EXPIRED', 'FAILED'))");

            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_redeem_status_check');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_redeem_status_check CHECK (redeem_status IN ('PENDING', 'PROCESSING', 'SUCCESS', 'FAILED'))");
        }
    }
};

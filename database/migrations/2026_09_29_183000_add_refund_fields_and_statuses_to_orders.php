<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('refund_amount', 12, 2)->nullable()->after('total_amount');
            $table->string('refund_ref_id', 100)->nullable()->after('payment_ref_id');
            $table->text('refund_reason')->nullable()->after('refund_ref_id');
            $table->timestamp('refunded_at')->nullable()->after('paid_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_payment_status_check');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_status_check CHECK (payment_status IN ('UNPAID', 'PAID', 'EXPIRED', 'FAILED', 'CANCELED', 'REFUNDED', 'REFUND_PENDING'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_payment_status_check');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_status_check CHECK (payment_status IN ('UNPAID', 'PAID', 'EXPIRED', 'FAILED', 'CANCELED'))");
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['refund_amount', 'refund_ref_id', 'refund_reason', 'refunded_at']);
        });
    }
};

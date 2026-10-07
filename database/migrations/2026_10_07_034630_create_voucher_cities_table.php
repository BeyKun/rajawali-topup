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
        Schema::create('voucher_cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('vouchers')->cascadeOnDelete();
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['voucher_id', 'city_id']);
        });

        // Backfill existing vouchers that already have city_id assigned
        if (Schema::hasColumn('vouchers', 'city_id')) {
            \Illuminate\Support\Facades\DB::table('vouchers')
                ->whereNotNull('city_id')
                ->select(['id', 'city_id', 'created_at', 'updated_at'])
                ->orderBy('id')
                ->chunk(200, function ($vouchers) {
                    $records = $vouchers->map(fn ($v) => [
                        'voucher_id' => $v->id,
                        'city_id' => $v->city_id,
                        'created_at' => $v->created_at ?? now(),
                        'updated_at' => $v->updated_at ?? now(),
                    ])->all();

                    \Illuminate\Support\Facades\DB::table('voucher_cities')->insertOrIgnore($records);
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_cities');
    }
};

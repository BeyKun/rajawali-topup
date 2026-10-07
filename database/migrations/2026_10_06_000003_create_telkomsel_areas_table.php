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
        Schema::create('telkomsel_areas', function (Blueprint $table) {
            $table->id();
            $table->string('region', 100)->unique();
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('province_id')->nullable()->after('role')->constrained('provinces')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->after('province_id')->constrained('cities')->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('region')->constrained('cities')->nullOnDelete();
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('region')->constrained('cities')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
            $table->dropConstrainedForeignId('province_id');
        });

        Schema::dropIfExists('telkomsel_areas');
    }
};

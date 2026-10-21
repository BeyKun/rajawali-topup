<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Outlet users are provisioned by an admin with only a name and phone
     * number, so `email` becomes optional and the (normalized, `628xxx`)
     * phone number takes over as the unique login identifier.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->string('email')->nullable()->change();
            $table->unique('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->string('email')->nullable(false)->change();
            $table->unique('email');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أرقام للتواصل عبر واتساب: الزبون مباشرة على الطلب أو عبر حساب المستخدم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->after('email');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_phone', 32)->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('customer_phone');
        });
    }
};

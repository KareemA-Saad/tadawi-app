<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('email'); // حقل جديد لـ provider (google, facebook, etc.)
            $table->string('password')->nullable()->change(); // nullable لو مش موجود
            $table->string('phone_number')->nullable()->change(); // nullable لو مش موجود
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('provider');
            $table->string('password')->nullable(false)->change();
            $table->string('phone_number')->nullable(false)->change();
        });
    }
};
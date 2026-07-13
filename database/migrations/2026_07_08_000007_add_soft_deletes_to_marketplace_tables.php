<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores',   fn (Blueprint $t) => $t->softDeletes());
        Schema::table('brands',   fn (Blueprint $t) => $t->softDeletes());
        Schema::table('products', fn (Blueprint $t) => $t->softDeletes());
    }

    public function down(): void
    {
        Schema::table('stores',   fn (Blueprint $t) => $t->dropSoftDeletes());
        Schema::table('brands',   fn (Blueprint $t) => $t->dropSoftDeletes());
        Schema::table('products', fn (Blueprint $t) => $t->dropSoftDeletes());
    }
};

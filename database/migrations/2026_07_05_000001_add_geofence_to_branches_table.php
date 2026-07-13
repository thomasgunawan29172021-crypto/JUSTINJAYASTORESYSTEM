<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            // Koordinat cabang untuk geofence absensi. Radius default 100 m.
            $table->decimal('latitude', 10, 7)->nullable()->after('phone');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedSmallInteger('geofence_radius_m')->default(100)->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'geofence_radius_m']);
        });
    }
};
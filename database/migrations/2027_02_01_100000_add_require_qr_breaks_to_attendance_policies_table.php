<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance: the QR is asked by type of mark (criterios 4.2.1). `require_qr_onsite` keeps meaning the start and the end of
 * the day; this one is for the break marks, and by default the QR is optional there (the geofence proves where).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table) {
            $table->boolean('require_qr_breaks')->default(false)->after('require_qr_onsite');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table) {
            $table->dropColumn('require_qr_breaks');
        });
    }
};

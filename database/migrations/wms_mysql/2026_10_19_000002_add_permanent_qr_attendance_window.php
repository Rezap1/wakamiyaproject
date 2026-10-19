<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('MASTER_PERMANENT_QR')) {
            return;
        }

        Schema::table('MASTER_PERMANENT_QR', function (Blueprint $table): void {
            if (! Schema::hasColumn('MASTER_PERMANENT_QR', 'ATTENDANCE_START_TIME')) {
                $table->time('ATTENDANCE_START_TIME')->nullable();
            }
            if (! Schema::hasColumn('MASTER_PERMANENT_QR', 'ATTENDANCE_END_TIME')) {
                $table->time('ATTENDANCE_END_TIME')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('MASTER_PERMANENT_QR')) {
            return;
        }

        Schema::table('MASTER_PERMANENT_QR', function (Blueprint $table): void {
            if (Schema::hasColumn('MASTER_PERMANENT_QR', 'ATTENDANCE_END_TIME')) {
                $table->dropColumn('ATTENDANCE_END_TIME');
            }
            if (Schema::hasColumn('MASTER_PERMANENT_QR', 'ATTENDANCE_START_TIME')) {
                $table->dropColumn('ATTENDANCE_START_TIME');
            }
        });
    }
};

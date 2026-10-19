<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('MASTER_ATTENDANCE_REQUEST', function (Blueprint $table): void {
            if (! Schema::hasColumn('MASTER_ATTENDANCE_REQUEST', 'Class_ID')) {
                $table->string('Class_ID', 191)->nullable();
            }
            if (! Schema::hasColumn('MASTER_ATTENDANCE_REQUEST', 'Attendance_Type')) {
                $table->string('Attendance_Type', 32)->nullable();
            }
        });

        if (! Schema::hasIndex('MASTER_ATTENDANCE_REQUEST', 'attendance_request_class_status_idx')) {
            Schema::table('MASTER_ATTENDANCE_REQUEST', function (Blueprint $table): void {
                $table->index(['Class_ID', 'Status'], 'attendance_request_class_status_idx');
            });
        }
        if (! Schema::hasIndex('MASTER_ATTENDANCE_REQUEST', 'attendance_request_schedule_status_idx')) {
            Schema::table('MASTER_ATTENDANCE_REQUEST', function (Blueprint $table): void {
                $table->index(['Schedule_ID', 'Status'], 'attendance_request_schedule_status_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('MASTER_ATTENDANCE_REQUEST', 'attendance_request_class_status_idx')) {
            Schema::table('MASTER_ATTENDANCE_REQUEST', function (Blueprint $table): void {
                $table->dropIndex('attendance_request_class_status_idx');
            });
        }
        if (Schema::hasIndex('MASTER_ATTENDANCE_REQUEST', 'attendance_request_schedule_status_idx')) {
            Schema::table('MASTER_ATTENDANCE_REQUEST', function (Blueprint $table): void {
                $table->dropIndex('attendance_request_schedule_status_idx');
            });
        }

        Schema::table('MASTER_ATTENDANCE_REQUEST', function (Blueprint $table): void {
            $columns = array_values(array_filter(
                ['Class_ID', 'Attendance_Type'],
                fn (string $column): bool => Schema::hasColumn('MASTER_ATTENDANCE_REQUEST', $column)
            ));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};

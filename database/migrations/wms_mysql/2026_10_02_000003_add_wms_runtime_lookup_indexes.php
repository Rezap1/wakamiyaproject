<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array<string, list<string>>> */
    private array $indexes = [
        'MASTER_USER' => [
            'wms_user_username_lookup' => ['Username'],
            'wms_user_email_lookup' => ['Email'],
        ],
        'FINANCE_INVOICE' => [
            'wms_invoice_student_status' => ['Student_ID', 'Status'],
        ],
        'FINANCE_PAYMENT' => [
            'wms_payment_invoice_status' => ['Invoice_ID', 'Status'],
            'wms_payment_student_status' => ['Student_ID', 'Status'],
        ],
        'MASTER_ATTENDANCE' => [
            'wms_attendance_student_day_class' => ['Student_ID', 'Attendance_Date', 'Class_ID'],
            'wms_attendance_employee_session' => ['Employee_ID', 'Session_ID'],
        ],
        'QUIZ_RESULT' => [
            'wms_quiz_result_class_completed' => ['Class_ID', 'Completed_At'],
        ],
        'MASTER_NOTIFICATION' => [
            'wms_notification_user_created_read' => ['User_ID', 'Created_At', 'Is_Read'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $existing = collect(Schema::getIndexes($table))->pluck('name');
            Schema::table($table, function (Blueprint $blueprint) use ($indexes, $existing): void {
                foreach ($indexes as $name => $columns) {
                    if (! $existing->contains($name)) {
                        $blueprint->index($columns, $name);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $existing = collect(Schema::getIndexes($table))->pluck('name');
            Schema::table($table, function (Blueprint $blueprint) use ($indexes, $existing): void {
                foreach (array_keys($indexes) as $name) {
                    if ($existing->contains($name)) {
                        $blueprint->dropIndex($name);
                    }
                }
            });
        }
    }
};

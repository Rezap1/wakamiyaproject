<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('WMS_ID_SEQUENCE')) {
            Schema::create('WMS_ID_SEQUENCE', function (Blueprint $table): void {
                $table->string('Sequence_Key', 191)->primary();
                $table->unsignedBigInteger('Last_Value')->default(0);
                $table->dateTime('Updated_At', 6)->nullable();
            });
        }

        if (Schema::hasTable('MASTER_ATTENDANCE')) {
            Schema::table('MASTER_ATTENDANCE', function (Blueprint $table): void {
                foreach (['Employee_ID', 'User_ID', 'Batch_ID', 'Session_ID'] as $column) {
                    if (! Schema::hasColumn('MASTER_ATTENDANCE', $column)) {
                        $table->string($column, 191)->nullable()->index();
                    }
                }
                if (! Schema::hasColumn('MASTER_ATTENDANCE', 'Late_Minutes')) {
                    $table->bigInteger('Late_Minutes')->nullable();
                }
                foreach (['Verification_Method', 'Device_Info'] as $column) {
                    if (! Schema::hasColumn('MASTER_ATTENDANCE', $column)) {
                        $table->longText($column)->nullable();
                    }
                }
            });
        }

        $this->addUniqueIfMissing('FINANCE_PAYMENT', ['Idempotency_Key'], 'wms_payment_idempotency_unique');
        $this->addUniqueIfMissing('QUIZ_ATTEMPT', ['Quiz_ID', 'Student_ID'], 'wms_quiz_attempt_student_unique');
        $this->addUniqueIfMissing('QUIZ_RESULT', ['Attempt_ID'], 'wms_quiz_result_attempt_unique');

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->addGeneratedLedgerGuard();
            $this->addGeneratedAttendanceGuard();
        }
    }

    public function down(): void
    {
        foreach ([
            ['FINANCE_PAYMENT', 'wms_payment_idempotency_unique'],
            ['QUIZ_ATTEMPT', 'wms_quiz_attempt_student_unique'],
            ['QUIZ_RESULT', 'wms_quiz_result_attempt_unique'],
            ['FINANCE_TRANSACTION', 'wms_transaction_reference_unique'],
            ['MASTER_ATTENDANCE', 'wms_attendance_business_unique'],
        ] as [$table, $index]) {
            if (Schema::hasTable($table) && $this->indexExists($table, $index)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropUnique($index));
            }
        }

        foreach ([
            'FINANCE_TRANSACTION' => '_runtime_ledger_key',
            'MASTER_ATTENDANCE' => '_runtime_attendance_key',
        ] as $table => $column) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($column));
            }
        }

        if (Schema::hasTable('MASTER_ATTENDANCE')) {
            $columns = array_values(array_filter(
                ['Employee_ID', 'User_ID', 'Batch_ID', 'Session_ID', 'Late_Minutes', 'Verification_Method', 'Device_Info'],
                fn (string $column): bool => Schema::hasColumn('MASTER_ATTENDANCE', $column)
            ));
            if ($columns !== []) {
                Schema::table('MASTER_ATTENDANCE', fn (Blueprint $table) => $table->dropColumn($columns));
            }
        }

        Schema::dropIfExists('WMS_ID_SEQUENCE');
    }

    /** @param list<string> $columns */
    private function addUniqueIfMissing(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique($columns, $name));
    }

    private function addGeneratedLedgerGuard(): void
    {
        if (! Schema::hasColumn('FINANCE_TRANSACTION', '_runtime_ledger_key')) {
            DB::statement(<<<'SQL'
ALTER TABLE `FINANCE_TRANSACTION`
ADD COLUMN `_runtime_ledger_key` VARCHAR(512)
GENERATED ALWAYS AS (
    CASE
        WHEN LOWER(COALESCE(`Reference_Type`, '')) IN ('payment', 'paymentreversal')
             AND COALESCE(`Reference_ID`, '') <> ''
        THEN CONCAT(LOWER(`Reference_Type`), ':', LOWER(`Reference_ID`))
        ELSE NULL
    END
) PERSISTENT
SQL);
        }
        if (! $this->indexExists('FINANCE_TRANSACTION', 'wms_transaction_reference_unique')) {
            Schema::table('FINANCE_TRANSACTION', fn (Blueprint $table) => $table->unique(
                '_runtime_ledger_key',
                'wms_transaction_reference_unique'
            ));
        }
    }

    private function addGeneratedAttendanceGuard(): void
    {
        if (! Schema::hasColumn('MASTER_ATTENDANCE', '_runtime_attendance_key')) {
            DB::statement(<<<'SQL'
ALTER TABLE `MASTER_ATTENDANCE`
ADD COLUMN `_runtime_attendance_key` CHAR(64)
GENERATED ALWAYS AS (
    CASE
        WHEN COALESCE(`Student_ID`, '') <> ''
             AND COALESCE(`Attendance_Date`, '') <> ''
             AND COALESCE(`Class_ID`, '') <> ''
             AND COALESCE(`Attendance_Type`, '') <> ''
        THEN SHA2(CONCAT('student|', LOWER(`Student_ID`), '|', `Attendance_Date`, '|', LOWER(`Class_ID`), '|', LOWER(`Attendance_Type`)), 256)
        WHEN COALESCE(`Employee_ID`, '') <> ''
             AND COALESCE(`Session_ID`, '') <> ''
        THEN SHA2(CONCAT('employee|', LOWER(`Employee_ID`), '|', LOWER(`Session_ID`)), 256)
        ELSE NULL
    END
) PERSISTENT
SQL);
        }
        if (! $this->indexExists('MASTER_ATTENDANCE', 'wms_attendance_business_unique')) {
            Schema::table('MASTER_ATTENDANCE', fn (Blueprint $table) => $table->unique(
                '_runtime_attendance_key',
                'wms_attendance_business_unique'
            ));
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))->contains(
            fn (array $definition): bool => ($definition['name'] ?? null) === $index
        );
    }
};

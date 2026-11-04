<?php

namespace Tests\Support;

use App\Support\Migration\WmsMigrationManifest;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait PdfDatabaseFixture
{
    protected function createPdfFixture(): void
    {
        // No migrations, seeders, production connections, or destructive resets.
        $this->assertTrue(app()->environment('testing'));
        \Illuminate\Support\Facades\Cache::forever('system_company_profile_payload', ['company' => ['name' => 'Perusahaan Fixture Audit'], 'bank' => [], 'document' => []]);
        $mysql = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
        if ($mysql) {
            $this->assertSame('wms_mysql_test', DB::connection()->getDatabaseName());
            $this->assertSame('wms_mysql_test', DB::selectOne('SELECT DATABASE() AS db')->db);
            $this->assertSame(config('database.default'), config('wms_runtime.connection'));
        } else {
            $this->assertSame('sqlite', config('wms_runtime.connection'));
            $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        }
        foreach (WmsMigrationManifest::contracts() as $contract) {
            Schema::create($contract['table'], function (Blueprint $table) use ($contract, $mysql) {
                // The existing MySQL test schema is incomplete. Use connection-local
                // manifest tables without altering any persistent table or row.
                if ($mysql) { $table->temporary(); }
                $columns = array_unique([...$contract['headers'], ...config('wms_runtime.runtime_columns.'.$contract['table'], [])]);
                foreach ($columns as $column) {
                    $definition = $mysql ? match (WmsMigrationManifest::columnType($column)) {
                        'identifier' => $table->string($column, 191),
                        'decimal' => $table->decimal($column, 20, 4),
                        'integer' => $table->bigInteger($column),
                        'boolean' => $table->boolean($column),
                        'date' => $table->date($column),
                        'datetime' => $table->dateTime($column, 6),
                        'time' => $table->time($column, 6),
                        default => $table->text($column),
                    } : $table->text($column);
                    $definition->nullable();
                }
                if ($contract['preserve_duplicate_ids']) { $table->integer('_source_row_number')->nullable(); }
            });
        }
        Schema::create('WMS_ID_SEQUENCE', function (Blueprint $table) use ($mysql) {
            if ($mysql) { $table->temporary(); }
            $table->string('Sequence_Key', 191)->primary();
            $table->unsignedBigInteger('Last_Value');
            $table->dateTime('Updated_At')->nullable();
        });
        $this->app->forgetInstance(\App\Interfaces\GoogleSheets\NotificationRepositoryInterface::class);
        $this->app->bind(\App\Interfaces\GoogleSheets\NotificationRepositoryInterface::class, \App\Repositories\GoogleSheets\NotificationRepository::class);
        foreach (['ADMINISTRATOR', 'MASTER', 'FINANCE', 'HR', 'ACADEMIC', 'MARKETING', 'TEACHER', 'STUDENT', 'DIRECTOR'] as $role) {
            $this->insertPdfRow('MASTER_ROLE', ['Role_ID' => 'ROLE-'.$role, 'Role_Name' => $role]);
        }
        $this->insertPdfRow('MASTER_USER', ['User_ID' => 'USR-AUDIT', 'Role_ID' => 'ROLE-ADMINISTRATOR', 'Username' => 'audit.user', 'Full_Name' => 'Audit Operator', 'Email' => 'audit@example.test']);
        $this->insertPdfRow('MASTER_USER', ['User_ID' => 'USR-TEACHER', 'Role_ID' => 'ROLE-TEACHER', 'Username' => 'audit.teacher', 'Full_Name' => 'Guru Audit', 'Employee_ID' => 'EMP-AUDIT']);
        $this->insertPdfRow('MASTER_USER', ['User_ID' => 'USR-STUDENT', 'Role_ID' => 'ROLE-STUDENT', 'Username' => 'audit.student', 'Full_Name' => 'Siswa Audit']);
        $this->insertPdfRow('MASTER_DEPARTMENT', ['Department_ID' => 'DEP-AUDIT', 'Department_Name' => 'Departemen Audit', 'Department_Code' => 'AUD']);
        $this->insertPdfRow('MASTER_POSITION', ['Position_ID' => 'POS-AUDIT', 'Position_Name' => 'Posisi Audit', 'Department_ID' => 'DEP-AUDIT']);
        $this->insertPdfRow('MASTER_EMPLOYEE', ['Employee_ID' => 'EMP-AUDIT', 'Employee_Number' => 'EMP-0001', 'Full_Name' => 'Pegawai Audit', 'User_ID' => 'USR-TEACHER', 'Department_ID' => 'DEP-AUDIT', 'Position_ID' => 'POS-AUDIT', 'Employment_Status' => 'Permanent']);
        $this->insertPdfRow('MASTER_TEACHER', ['Teacher_ID' => 'TCH-AUDIT', 'Teacher_Code' => 'GURU-001', 'Full_Name' => 'Guru Audit', 'User_ID' => 'USR-TEACHER', 'Employee_ID' => 'EMP-AUDIT', 'Teaching_Status' => 'Active']);
        $this->insertPdfRow('MASTER_PROGRAM', ['Program_ID' => 'PRG-AUDIT', 'Program_Name' => 'Program Audit', 'Program_Code' => 'PRG-001']);
        $this->insertPdfRow('MASTER_BATCH', ['Batch_ID' => 'BAT-AUDIT', 'Batch_Name' => 'Angkatan Audit', 'Batch_Code' => 'BAT-001', 'Program_ID' => 'PRG-AUDIT', 'Batch_Status' => 'Active']);
        $this->insertPdfRow('MASTER_CLASS', ['Class_ID' => 'CLS-AUDIT', 'Class_Code' => 'CLS-001', 'Class_Name' => 'Kelas Audit', 'Batch_ID' => 'BAT-AUDIT', 'Program_ID' => 'PRG-AUDIT', 'Homeroom_Teacher_ID' => 'TCH-AUDIT', 'Class_Status' => 'Active', 'Capacity' => 20]);
        $this->insertPdfRow('MASTER_STUDENT', ['Student_ID' => 'STU-0001', 'Student_Number' => 'NIS-0001', 'Full_Name' => 'Siswa Audit', 'User_ID' => 'USR-STUDENT', 'Class_ID' => 'CLS-AUDIT', 'Batch_ID' => 'BAT-AUDIT', 'Program_ID' => 'PRG-AUDIT', 'Enrollment_Status' => 'Active', 'Graduation_Status' => 'Not Graduated']);
        $this->insertPdfRow('MASTER_COMPANY', ['Company_ID' => 'COM-AUDIT', 'Company_Code' => 'COM-001', 'Company_Name' => 'Perusahaan Audit']);
        $this->insertPdfRow('MASTER_MODULE', ['Module_ID' => 'MOD-AUDIT', 'Module_Name' => 'Modul Audit', 'Module_Code' => 'MOD-001']);
        $this->insertPdfRow('MASTER_SUBJECT', ['Subject_ID' => 'SUB-AUDIT', 'Subject_Name' => 'Bahasa Audit', 'Subject_Code' => 'SUB-001', 'Program_ID' => 'PRG-AUDIT']);
        $this->insertPdfRow('MASTER_ACADEMIC_YEAR', ['Academic_Year_ID' => 'YEAR-AUDIT', 'Name' => 'Tahun Audit', 'Semester' => '1']);
        $this->insertPdfRow('MASTER_CLASS_ENROLLMENT', ['Enrollment_ID' => 'ENR-AUDIT', 'Class_ID' => 'CLS-AUDIT', 'Student_ID' => 'STU-0001', 'Status' => 'Active', 'Academic_Year_ID' => 'YEAR-AUDIT']);
        $this->insertPdfRow('MASTER_ASSESSMENT_CONFIG', ['Category_ID' => 'UJIAN_BAB', 'Category_Name' => 'Ujian Bab', 'Aspects_JSON' => '[]']);
        // No schedule: explicitly exercise the valid homeroom-only workflow.
        $this->insertPdfRow('MASTER_SCORE', ['Score_ID' => 'SCR-0001', 'Student_ID' => 'STU-0001', 'Subject_ID' => 'SUB-AUDIT', 'Score' => '88.50', 'Grade' => 'A', 'Assessment_Category' => 'UJIAN_BAB', 'Created_By' => 'USR-TEACHER', 'Evaluation_Details' => '{"category":"ujian_bab","assessment_date":"2026-10-31","class_id":"CLS-AUDIT","notes":"Nilai historis audit"}']);
        $this->insertPdfRow('ASSESSMENTS', ['Assessment_ID' => 'ASM-AUDIT', 'Title' => 'Evaluasi Audit', 'Class_ID' => 'CLS-AUDIT', 'Subject_ID' => 'SUB-AUDIT', 'Date' => '2026-10-31', 'Type' => 'Exam', 'Status' => 'Published', 'Max_Score' => 100]);
        $this->insertPdfRow('MASTER_ASSIGNMENT', ['Assignment_ID' => 'ASG-AUDIT', 'Title' => 'Tugas Audit', 'Class_ID' => 'CLS-AUDIT', 'Subject_ID' => 'SUB-AUDIT', 'Teacher_ID' => 'TCH-AUDIT', 'Deadline' => '2026-11-04 23:59:00', 'Status' => 'PUBLISHED', 'Assignment_Type' => 'HOMEWORK']);
        $this->insertPdfRow('MASTER_ATTENDANCE', ['Attendance_ID' => 'ATT-AUDIT', 'Student_ID' => 'STU-0001', 'Class_ID' => 'CLS-AUDIT', 'Attendance_Type' => 'STUDENT', 'Attendance_Date' => '2026-11-04', 'Date' => '2026-11-04', 'Status' => 'PRESENT', 'Check_In_Time' => '08:00:00', 'Check_Out_Time' => '16:00:00']);
        $this->insertPdfRow('MASTER_ACCOUNT', ['Account_ID' => 'ACC-AUDIT', 'Account_Code' => '1101', 'Account_Name' => 'Bank Audit', 'Account_Category' => 'Asset', 'Normal_Balance' => 'Debit']);
        $this->insertPdfRow('MASTER_ACCOUNT', ['Account_ID' => 'ACC-OLD', 'Account_Code' => '1102', 'Account_Name' => 'Bank Historis', 'Is_Active' => 0]);
        foreach ([['TRX-OPEN', '2026-10-30', 'ACC-AUDIT', 'Income', 100000], ['TRX-500', '2026-11-04', '1101', 'Income', 500000], ['TRX-1250', '2026-11-04', 'ACC-OLD', 'Income', 1250000], ['TRX-1500', '2026-11-04', 'ACC-AUDIT', 'Expense', 1500000]] as [$id, $date, $account, $type, $amount]) {
            $this->insertPdfRow('FINANCE_TRANSACTION', ['Transaction_ID' => $id, 'Transaction_Date' => $date, 'Account_ID' => $account, 'Type' => $type, 'Category' => 'Operasional', 'Amount' => $amount, 'Reference_Type' => 'Other', 'Description' => 'Keterangan '.$id]);
        }
        $this->insertPdfRow('FINANCE_INVOICE', ['Invoice_ID' => 'INV-AUDIT', 'Student_ID' => 'STU-0001', 'Invoice_Type' => 'STUDENT', 'Category' => 'Buku', 'Period' => '2026-11', 'Amount' => 2000000, 'Description' => 'Buku Audit', 'Status' => 'Waiting Payment', 'Due_Date' => '2026-11-30']);
        $this->insertPdfRow('FINANCE_PAYMENT', ['Payment_ID' => 'PAY-AUDIT', 'Invoice_ID' => 'INV-AUDIT', 'Student_ID' => 'STU-0001', 'Amount_Paid' => 500000, 'Payment_Date' => '2026-11-04', 'Payment_Method' => 'Transfer', 'Receipt_Number' => 'REC-AUDIT', 'Status' => 'Verified', 'Verified_By' => 'USR-AUDIT', 'Verified_At' => '2026-11-04 10:00:00']);
        $this->insertPdfRow('MASTER_PAYROLL', ['Payroll_ID' => 'PRL-AUDIT', 'Employee_ID' => 'EMP-AUDIT', 'Period' => '2026-11', 'Payroll_Period' => '2026-11', 'Base_Salary' => 3000000, 'Total_Allowances' => 200000, 'Total_Deductions' => 100000, 'Net_Salary' => 3100000, 'Status' => 'Paid', 'Paid_Date' => '2026-11-04', 'Approved_Date' => '2026-11-03']);
        $this->insertPdfRow('MASTER_LEAVE', ['Leave_ID' => 'LEV-AUDIT', 'Document_Number' => 'CUTI-AUDIT', 'Employee_ID' => 'EMP-AUDIT', 'Employee_Name' => 'Pegawai Audit', 'Leave_Type' => 'Annual', 'Start_Date' => '2026-11-04', 'End_Date' => '2026-11-05', 'Duration_Days' => 2, 'Reason' => 'Cuti Audit', 'Status' => 'APPROVED', 'Approved_By' => 'USR-AUDIT', 'Approved_At' => '2026-11-03 10:00:00']);
        $this->insertPdfRow('MASTER_OVERTIME', ['Overtime_ID' => 'OVT-AUDIT', 'Document_Number' => 'LEMBUR-AUDIT', 'Employee_ID' => 'EMP-AUDIT', 'Employee_Name' => 'Pegawai Audit', 'Date' => '2026-11-04', 'Start_Time' => '17:00:00', 'End_Time' => '19:00:00', 'Duration_Hours' => 2, 'Hourly_Rate' => 25000, 'Overtime_Pay' => 50000, 'Reason' => 'Lembur Audit', 'Status' => 'APPROVED', 'Approved_By' => 'USR-AUDIT', 'Approved_At' => '2026-11-03 10:00:00']);
        $this->insertPdfRow('MASTER_NOTIFICATION', ['Notification_ID' => 'NOT-AUDIT', 'User_ID' => 'USR-AUDIT', 'Title' => 'Notifikasi Audit', 'Message' => 'Pesan Audit', 'Is_Read' => 0]);
        $this->insertPdfRow('MASTER_AUDIT_LOG', ['Audit_ID' => 'LOG-AUDIT', 'User_ID' => 'USR-AUDIT', 'Module' => 'AUDIT', 'Action' => 'READ AUDIT', 'New_Value' => 'Bukti Audit', 'Status' => 'SUCCESS']);
        $this->insertPdfRow('MASTER_ANNOUNCEMENT', ['Announcement_ID' => 'ANN-AUDIT', 'Title' => 'Pengumuman Audit', 'Content' => 'Isi Audit', 'Target_Role' => 'ALL', 'Priority' => 'NORMAL', 'Publish_Date' => '2026-11-01 08:00:00', 'Expired_Date' => '2026-12-01 08:00:00']);
        $this->insertPdfRow('MASTER_ALUMNI', ['Alumni_ID' => 'ALM-AUDIT', 'Source_Type' => 'MANUAL', 'Full_Name' => 'Alumni Audit', 'Program_ID' => 'PRG-AUDIT', 'Batch_ID' => 'BAT-AUDIT', 'Class_ID' => 'CLS-AUDIT', 'Status' => 'ACTIVE', 'Work_Field' => 'Hospitality']);
        $this->pdfActor('ADMINISTRATOR');
    }

    protected function insertPdfRow(string $table, array $row): void
    {
        $columns = WmsMigrationManifest::contracts()[$table]['headers'];
        $defaults = array_intersect_key(['Is_Active' => 1, 'Submitted_At' => '2026-11-03 09:00:00', 'Created_At' => '2026-11-04 09:00:00', 'Updated_At' => '2026-11-04 09:00:00', 'Created_By' => 'USR-AUDIT'], array_flip($columns));
        DB::table($table)->insert(array_merge($defaults, $row));
    }

    protected function pdfActor(string $role): void
    {
        \App\Helpers\UserResolverHelper::clearCache();
        $id = match ($role) { 'TEACHER' => 'USR-TEACHER', 'STUDENT' => 'USR-STUDENT', default => 'USR-AUDIT' };
        $this->actingAs(new GenericUser(['id' => $id, 'User_ID' => $id, 'Role_ID' => 'ROLE-'.$role, 'Role' => $role, 'Full_Name' => 'Audit Operator', 'Email' => 'audit@example.test']));
    }
}

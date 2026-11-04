<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithPdf;
use Tests\Support\PdfDatabaseFixture;
use Tests\TestCase;

class GlobalPdfIntegrityTest extends TestCase
{
    use InteractsWithPdf, PdfDatabaseFixture;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-04 10:00:00');
        $this->createPdfFixture();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::purge();
        parent::tearDown();
    }

    public static function lists(): array
    {
        return [
            'users' => ['users', 'Audit Operator', 3],
            'departments' => ['departments', 'Departemen Audit', 1],
            'positions' => ['positions', 'Posisi Audit', 1],
            'employees' => ['employees', 'Guru Audit', 1],
            'teachers' => ['teachers', 'Guru Audit', 1],
            'programs' => ['programs', 'Program Audit', 1],
            'students' => ['students', 'Siswa Audit', 1],
            'companies' => ['companies', 'Perusahaan Audit', 1],
            'modules' => ['modules', 'Modul Audit', 1],
            'accounts' => ['accounts', 'Bank Audit', 1],
            'transactions' => ['transactions', 'Bank Audit', 4],
            'invoices' => ['invoices', 'INV-AUDIT', 1],
            'payments' => ['payments', 'Siswa Audit', 1],
            'payrolls' => ['payrolls', 'Pegawai Audit', 1],
            'leaves' => ['hr.leaves', 'Pegawai Audit', 1],
            'overtimes' => ['hr.overtimes', 'Pegawai Audit', 1],
            'audit' => ['audit', 'READ AUDIT', 1],
            'batches' => ['batches', 'Angkatan Audit', 1],
            'classes' => ['classes', 'Kelas Audit', 1],
            'assessments' => ['assessments', 'Evaluasi Audit', 1],
            'scores' => ['scores', 'Siswa Audit', 1],
            'subjects' => ['subjects', 'Bahasa Audit', 1],
            'assignments' => ['assignments', 'Tugas Audit', 1],
            'notifications' => ['notifications', 'Notifikasi Audit', 1],
            'activity' => ['activity', 'READ AUDIT', 1],
            'announcements' => ['announcements', 'Pengumuman Audit', 1],
        ];
    }

    #[DataProvider('lists')]
    public function test_list_pdf_contains_source_identity_and_exact_record_count(string $prefix, string $expected, int $count): void
    {
        $text = $this->pdfText($this->get(route($prefix.'.export-pdf')), $prefix);
        $this->assertStringContainsString($expected, $text);
        $this->assertMatchesRegularExpression('/Total Data\s*:\s*'.$count.'\b/', $text);
        $this->assertStringNotContainsString('Akun tidak ditemukan', $text);
    }

    #[DataProvider('lists')]
    public function test_empty_date_range_is_a_zero_record_pdf(string $prefix, string $expected, int $count): void
    {
        $text = $this->pdfText($this->get(route($prefix.'.export-pdf', ['export_filter' => 'range', 'start_date' => '2001-01-01', 'end_date' => '2001-01-02'])), $prefix.'-empty');
        $this->assertMatchesRegularExpression('/Total Data\s*:\s*0\b/', $text);
        $this->assertStringContainsString('Tidak ada data', $text);
    }

    public function test_cash_flow_pdf_reconciles_independent_known_amounts_and_historical_accounts(): void
    {
        // Independent arithmetic: opening 100000; income 500000+1250000;
        // expense 1500000; net 250000; closing 350000.
        $text = $this->pdfText($this->get(route('reports.finance.export-pdf', ['start_date' => '2026-11-01', 'end_date' => '2026-11-30'])), 'cash-flow');
        foreach (['TRX-500', 'TRX-1250', 'TRX-1500', 'Bank Audit', 'Bank Historis', '100.000', '1.750.000', '1.500.000', '250.000', '350.000'] as $expected) {
            $this->assertStringContainsString($expected, $text);
        }
        $this->assertMatchesRegularExpression('/Total Data\s*:\s*3\b/', $text);
        $this->assertStringNotContainsString('TRX-OPEN', $text);
        $this->get(route('reports.finance.cash_flow'))->assertOk()->assertSee('Bank Historis')->assertSee('350.000');
    }

    public function test_cash_flow_account_code_and_today_filter_recalculate_balances(): void
    {
        $text = $this->pdfText($this->get(route('reports.finance.export-pdf', ['account_id' => '1101', 'export_filter' => 'today'])), 'cash-flow-account');
        foreach (['TRX-500', 'TRX-1500', '500.000', '-1.000.000', '-900.000'] as $expected) { $this->assertStringContainsString($expected, $text); }
        $this->assertStringNotContainsString('TRX-1250', $text);
        $this->assertMatchesRegularExpression('/Total Data\s*:\s*2\b/', $text);
    }

    public function test_outstanding_reconciles_only_verified_payments(): void
    {
        $this->insertPdfRow('FINANCE_PAYMENT', ['Payment_ID' => 'PAY-PENDING', 'Invoice_ID' => 'INV-AUDIT', 'Student_ID' => 'STU-0001', 'Amount_Paid' => 900000, 'Status' => 'Waiting Verification']);
        $text = $this->pdfText($this->get(route('reports.finance.export-pdf', ['report_type' => 'outstanding'])), 'outstanding');
        foreach (['Siswa Audit', 'INV-AUDIT', '2.000.000', '500.000', '1.500.000', 'Total Piutang'] as $expected) { $this->assertStringContainsString($expected, $text); }
        $this->assertStringNotContainsString('900.000', $text);
    }

    public function test_missing_account_and_bad_date_are_visible_integrity_findings(): void
    {
        $this->insertPdfRow('FINANCE_TRANSACTION', ['Transaction_ID' => 'TRX-ORPHAN', 'Transaction_Date' => '2026-11-04', 'Account_ID' => null, 'Type' => 'Income', 'Amount' => 12]);
        $this->insertPdfRow('FINANCE_TRANSACTION', ['Transaction_ID' => 'TRX-BAD-DATE', 'Transaction_Date' => null, 'Account_ID' => 'ACC-AUDIT', 'Type' => 'Income', 'Amount' => 99]);
        $text = $this->pdfText($this->get(route('reports.finance.export-pdf')), 'cash-flow-integrity');
        foreach (['Perlu rekonsiliasi', 'TRX-ORPHAN', 'TRX-BAD-DATE', 'Referensi akun tidak valid'] as $expected) { $this->assertStringContainsString($expected, $text); }
        $this->assertSame(1, app(\App\Services\Finance\FinanceReportService::class)->getCashFlow()['skipped_transaction_count']);
    }

    public function test_soft_deleted_ledger_is_excluded_without_changing_amounts(): void
    {
        $this->insertPdfRow('FINANCE_TRANSACTION', ['Transaction_ID' => 'TRX-VOID', 'Transaction_Date' => '2026-11-04', 'Account_ID' => 'ACC-AUDIT', 'Type' => 'Income', 'Amount' => 9999999, 'Is_Active' => 0]);
        $text = $this->pdfText($this->get(route('reports.finance.export-pdf')), 'cash-flow-void');
        $this->assertStringNotContainsString('TRX-VOID', $text);
        $this->assertStringContainsString('350.000', $text);
        $this->assertEquals(9999999, DB::table('FINANCE_TRANSACTION')->where('Transaction_ID', 'TRX-VOID')->value('Amount'));
    }

    public function test_teacher_without_schedule_exports_real_student_and_historical_score(): void
    {
        $this->pdfActor('TEACHER');
        $screen = $this->get(route('teacher.workspace.scores'))->assertOk()->assertSee('Siswa Audit')->assertSee('88,5');
        $screenDirectory = base_path('tmp/pdf-integrity/'.config('database.default'));
        if (!is_dir($screenDirectory)) { mkdir($screenDirectory, 0777, true); }
        file_put_contents($screenDirectory.'/teacher-scores-screen.html', $screen->getContent());
        foreach (['student' => ['student_id' => 'STU-0001'], 'class' => ['class_id' => 'CLS-AUDIT']] as $mode => $entity) {
            $text = $this->pdfText($this->get(route('teacher.workspace.reports.scores-pdf', $entity + ['mode' => $mode, 'all_periods' => 1])), 'teacher-'.$mode);
            foreach (['Siswa Audit', '88,5', 'Nilai historis audit', 'Semua Periode', 'Kelas Audit'] as $expected) { $this->assertStringContainsString(strtoupper($expected), strtoupper($text)); }
            $this->assertMatchesRegularExpression('/Total Data\s*:\s*1\b/', $text);
        }
    }

    public function test_teacher_date_filter_uses_assessment_not_import_date_and_rejects_foreign_ids(): void
    {
        $this->pdfActor('TEACHER');
        $query = ['mode' => 'student', 'student_id' => 'STU-0001', 'date_from' => '2026-10-31', 'date_to' => '2026-10-31'];
        $text = $this->pdfText($this->get(route('teacher.workspace.reports.scores-pdf', $query)), 'teacher-date');
        $this->assertStringContainsString('88,5', $text);
        $query['date_from'] = $query['date_to'] = '2026-11-04';
        $text = $this->pdfText($this->get(route('teacher.workspace.reports.scores-pdf', $query)), 'teacher-empty');
        $this->assertStringContainsString('Belum ada data nilai', $text);
        $query['student_id'] = 'STU-FOREIGN';
        $this->get(route('teacher.workspace.reports.scores-pdf', $query))->assertForbidden();
    }

    public function test_student_pdf_uses_own_identity_and_score(): void
    {
        $this->pdfActor('STUDENT');
        $text = $this->pdfText($this->get(route('student.export-scores-pdf')), 'student-scores');
        $this->assertStringContainsString('Siswa Audit', $text);
        $this->assertStringContainsString('88,5', $text);
    }

    public function test_assessment_and_transaction_range_filters_use_real_schema_columns(): void
    {
        foreach (['assessments' => ['2026-10-31', 'Evaluasi Audit'], 'transactions' => ['2026-11-04', 'Bank Historis'], 'activity' => ['2026-11-04', 'READ AUDIT']] as $prefix => [$date, $label]) {
            $text = $this->pdfText($this->get(route($prefix.'.export-pdf', ['export_filter' => 'range', 'start_date' => $date, 'end_date' => $date])), $prefix.'-range');
            $this->assertStringContainsString($label, $text);
        }
    }

    public function test_invalid_date_range_is_rejected_and_role_boundaries_hold(): void
    {
        $this->getJson(route('transactions.export-pdf', ['export_filter' => 'range', 'start_date' => '2026-11-05', 'end_date' => '2026-11-04']))->assertUnprocessable();
        foreach (['STUDENT', 'TEACHER', 'MARKETING', 'ACADEMIC', 'HR'] as $role) {
            $this->pdfActor($role);
            $this->get(route('reports.finance.export-pdf'))->assertForbidden();
        }
    }

    public static function documents(): array
    {
        return [
            'invoice' => ['invoices.pdf', ['id' => 'INV-AUDIT'], 'ADMINISTRATOR', ['Siswa Audit', 'INV-AUDIT', '2.000.000', '500.000', '1.500.000']],
            'receipt' => ['payments.receipt', ['id' => 'PAY-AUDIT'], 'FINANCE', ['Siswa Audit', '500.000', 'PAY-AUDIT']],
            'payslip' => ['payrolls.pdf', ['id' => 'PRL-AUDIT'], 'HR', ['Pegawai Audit', '3.000.000', '200.000', '100.000', '3.100.000']],
            'leave' => ['leaves.pdf', ['id' => 'LEV-AUDIT'], 'HR', ['Pegawai Audit', 'CUTI-AUDIT', 'Cuti Audit']],
            'overtime' => ['overtimes.pdf', ['id' => 'OVT-AUDIT'], 'HR', ['Pegawai Audit', 'LEMBUR-AUDIT', '50.000']],
            'student-invoice' => ['student.billing.invoice-pdf', ['id' => 'INV-AUDIT'], 'STUDENT', ['Siswa Audit', '2.000.000', '1.500.000']],
            'student-receipt' => ['student.billing.payment-receipt', ['id' => 'PAY-AUDIT'], 'STUDENT', ['Siswa Audit', '500.000']],
            'academic-attendance' => ['attendances.export-pdf', ['date' => '2026-11-04', 'class_id' => 'CLS-AUDIT'], 'ACADEMIC', ['Siswa Audit', 'Kelas Audit', 'Hadir', '08:00']],
            'teacher-attendance' => ['teacher.workspace.reports.attendances-pdf', [], 'TEACHER', ['Siswa Audit', 'Kelas Audit', 'Hadir']],
            'student-attendance' => ['student.export-attendances-pdf', [], 'STUDENT', ['Siswa Audit', 'Hadir', '4 November 2026']],
            'attendance-daily' => ['attendance.reports.pdf', ['class_id' => 'CLS-AUDIT', 'report_type' => 'harian', 'daily_date' => '2026-11-04'], 'ADMINISTRATOR', ['Siswa Audit', 'Kelas Audit', 'Hadir']],
            'attendance-weekly' => ['attendance.reports.pdf', ['class_id' => 'CLS-AUDIT', 'report_type' => 'mingguan', 'weekly_anchor' => '2026-11-04'], 'ADMINISTRATOR', ['Siswa Audit', 'Kelas Audit']],
            'attendance-monthly' => ['attendance.reports.pdf', ['class_id' => 'CLS-AUDIT', 'report_type' => 'bulanan', 'month' => 11, 'year' => 2026], 'ADMINISTRATOR', ['Siswa Audit', 'Kelas Audit']],
            'attendance-range' => ['attendance.reports.pdf', ['class_id' => 'CLS-AUDIT', 'student_id' => 'STU-0001', 'report_type' => 'rentang', 'date_start' => '2026-11-04', 'date_end' => '2026-11-05'], 'ADMINISTRATOR', ['Siswa Audit', 'Kelas Audit']],
        ];
    }

    #[DataProvider('documents')]
    public function test_specialized_documents_match_fixture(string $route, array $query, string $role, array $expected): void
    {
        $this->pdfActor($role);
        $text = $this->pdfText($this->get(route($route, $query)), str_replace('.', '-', $route).'-'.($query['report_type'] ?? 'detail'));
        foreach ($expected as $value) { $this->assertStringContainsString($value, $text); }
    }

    public function test_schedule_and_selected_alumni_pdf(): void
    {
        $this->insertPdfRow('MASTER_SCHEDULE', ['Schedule_ID' => 'SCH-AUDIT', 'Class_ID' => 'CLS-AUDIT', 'Subject_ID' => 'SUB-AUDIT', 'Teacher_ID' => 'TCH-AUDIT', 'Day_Of_Week' => 'Wednesday', 'Start_Time' => '08:00:00', 'End_Time' => '10:00:00']);
        $text = $this->pdfText($this->get(route('schedules.export-pdf', ['export_filter' => 'today'])), 'schedules');
        foreach (['Kelas Audit', 'Bahasa Audit', 'Guru Audit', '08:00'] as $value) { $this->assertStringContainsString($value, $text); }
        $this->insertPdfRow('MASTER_ALUMNI', ['Alumni_ID' => 'ALM000001', 'Source_Type' => 'MANUAL', 'Full_Name' => 'Alumni Terpilih', 'Status' => 'ACTIVE']);
        $text = $this->pdfText($this->post(route('alumni.export-selected-pdf'), ['alumni_ids' => ['ALM000001']]), 'alumni-selected');
        $this->assertStringContainsString('Alumni Terpilih', $text);
        $this->assertStringNotContainsString('Alumni Audit', $text);
    }

    public function test_smart_generator_manual_and_canonical_invoice_receipt_amounts(): void
    {
        foreach (['invoice', 'kwitansi'] as $type) {
            $text = $this->pdfText($this->post(route('finance.smart_generator.pdf'), [
                'doc_type' => $type, 'source_type' => 'manual_invoice', 'doc_number' => 'MANUAL-AUDIT', 'client_name' => 'Klien Audit',
                'items' => [['name' => 'Produk Audit', 'qty' => 2, 'price' => 250000]], 'kwitansi_amount' => 500000,
            ]), 'smart-manual-'.$type);
            foreach (['Klien Audit', 'MANUAL-AUDIT', '500.000'] as $value) { $this->assertStringContainsString($value, $text); }
            $text = $this->pdfText($this->post(route('finance.smart_generator.pdf'), [
                'doc_type' => $type, 'source_type' => 'student_invoice', 'source_id' => 'INV-AUDIT', 'client_name' => 'FORGED', 'kwitansi_amount' => 1,
            ]), 'smart-student-'.$type);
            foreach (['Siswa Audit', 'INV-AUDIT', $type === 'invoice' ? '2.000.000' : '500.000'] as $value) { $this->assertStringContainsString($value, $text); }
            $this->assertStringNotContainsString('FORGED', $text);
            if ($type === 'kwitansi') { $this->assertStringNotContainsString('2.000.000', $text); }
        }
    }

    public function test_print_preview_and_pagination_use_the_same_scoped_rows(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            $this->insertPdfRow('MASTER_ACCOUNT', ['Account_ID' => 'ACC-PAGE-'.$i, 'Account_Code' => 'P'.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'Account_Name' => 'PageAccount'.$i, 'Account_Category' => 'Asset']);
        }
        $query = ['search' => 'PageAccount', 'export_filter' => 'current_page', 'page' => 2];
        $text = $this->pdfText($this->get(route('accounts.export-pdf', $query)), 'accounts-page2');
        $this->assertStringContainsString('PageAccount16', $text);
        $this->assertStringNotContainsString('PageAccount15', $text);
        $this->assertMatchesRegularExpression('/Total Data\s*:\s*1\b/', $text);
        $this->get(route('accounts.print', $query))->assertOk()->assertSee('PageAccount16')->assertDontSee('PageAccount15');
        $preview = $this->pdfText($this->get(route('accounts.preview-pdf', $query)), 'accounts-preview');
        $this->assertStringContainsString('PageAccount16', $preview);
    }

    public function test_student_cannot_read_another_students_financial_documents(): void
    {
        $this->insertPdfRow('MASTER_STUDENT', ['Student_ID' => 'STU-OTHER', 'Full_Name' => 'Rahasia Siswa']);
        $this->insertPdfRow('FINANCE_INVOICE', ['Invoice_ID' => 'INV-OTHER', 'Student_ID' => 'STU-OTHER', 'Invoice_Type' => 'STUDENT', 'Amount' => 7777777, 'Status' => 'Waiting Payment']);
        $this->insertPdfRow('FINANCE_PAYMENT', ['Payment_ID' => 'PAY-OTHER', 'Student_ID' => 'STU-OTHER', 'Invoice_ID' => 'INV-OTHER', 'Amount_Paid' => 7777777, 'Status' => 'Verified']);
        $this->pdfActor('STUDENT');
        $this->get(route('student.billing.payment-receipt', ['id' => 'PAY-OTHER']))->assertForbidden();
        $response = $this->get(route('student.billing.invoice-pdf', ['id' => 'INV-OTHER']));
        $this->assertContains($response->status(), [302, 403, 404]);
        $response->assertDontSee('Rahasia Siswa')->assertDontSee('7.777.777');
    }

    public function test_permanent_qr_pdf_contains_the_authorized_class_identity(): void
    {
        $this->insertPdfRow('MASTER_PERMANENT_QR', ['QR_ID' => 'QR-AUDIT', 'QR_TYPE' => 'STUDENT', 'IDENTIFIER' => 'CLS-AUDIT', 'LABEL' => 'QR Kelas Audit', 'STATUS' => 'ACTIVE', 'ACTIVE_FROM' => '2026-11-01 00:00:00', 'ACTIVE_UNTIL' => '2026-12-01 23:59:00', 'CREATED_AT' => '2026-11-01 00:00:00']);
        $text = $this->pdfText($this->get(route('attendance.qr.pdf', ['id' => 'QR-AUDIT'])), 'permanent-qr');
        $this->assertStringContainsString('QR Kelas Audit', $text);
        $this->assertStringContainsString('CLS-AUDIT', $text);
    }

    public function test_graduation_pdf_payload_and_archived_metadata_survive_mysql_contract(): void
    {
        $archive = base_path('tmp/pdf-integrity/'.config('database.default').'/archive');
        config(['filesystems.disks.local.root' => $archive]);
        \Illuminate\Support\Facades\Storage::forgetDisk('local');
        $events = \Mockery::mock(\App\Services\Core\EnterpriseEventService::class);
        $events->shouldReceive('dispatch')->andReturnNull();
        $this->app->instance(\App\Services\Core\EnterpriseEventService::class, $events);
        app(\App\Services\Core\StudentService::class)->updateStudent('STU-0001', ['Graduation_Status' => 'Lulus']);
        $documents = app(\App\Repositories\GoogleSheets\DocumentRepository::class)->getAll();
        $this->assertCount(2, $documents);
        foreach ($documents as $document) {
            $this->assertSame('Student', $document['Reference_Type']);
            $this->assertSame('STU-0001', $document['Reference_ID']);
            $bytes = \Illuminate\Support\Facades\Storage::disk('local')->get($document['File_Path']);
            $this->assertSame(hash('sha256', $bytes), $document['Hash']);
            $text = $this->pdfText(\Illuminate\Testing\TestResponse::fromBaseResponse(response($bytes)), 'automatic-'.$document['Document_Type']);
            $this->assertStringContainsString('Siswa Audit', $text);
            $this->assertStringContainsString('Program Audit', $text);
            if ($document['Document_Type'] === 'AcademicReport') {
                $this->assertStringContainsString('88,5', $text);
                $this->assertStringNotContainsString('Penilaian tidak ditemukan', $text);
            }
        }
    }

    public function test_academic_name_search_and_print_attendance_match_the_screen(): void
    {
        $this->get(route('scores.index', ['search' => 'Siswa Audit']))->assertOk()->assertSee('Siswa Audit');
        $text = $this->pdfText($this->get(route('scores.export-pdf', ['search' => 'Siswa Audit'])), 'academic-name-search');
        $this->assertStringContainsString('Siswa Audit', $text);
        $this->assertMatchesRegularExpression('/Total Data\s*:\s*1\b/', $text);
        $query = ['class_id' => 'CLS-AUDIT', 'date' => '2026-11-04', 'search' => 'NO-SUCH-STUDENT'];
        $this->get(route('attendances.print', $query))->assertOk()->assertDontSee('Siswa Audit')->assertSee('Belum ada data presensi');
        $text = $this->pdfText($this->get(route('attendances.export-pdf', $query)), 'academic-attendance-empty');
        $this->assertStringNotContainsString('Siswa Audit', $text);
    }

    public function test_duplicate_transactions_fail_without_changing_the_ledger(): void
    {
        $this->insertPdfRow('FINANCE_TRANSACTION', ['Transaction_ID' => 'TRX-500', 'Transaction_Date' => '2026-11-04', 'Account_ID' => 'ACC-AUDIT', 'Type' => 'Income', 'Amount' => 500000]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('duplikat');
        app(\App\Services\Finance\FinanceReportService::class)->getCashFlow();
    }

    public function test_all_periods_keeps_unknown_legacy_dates_and_missing_student_reference_is_explicit(): void
    {
        $this->insertPdfRow('MASTER_SCORE', ['Score_ID' => 'SCR-ORPHAN', 'Student_ID' => 'STU-MISSING', 'Score' => 62, 'Assessment_Category' => 'UJIAN_BAB', 'Created_At' => null]);
        $text = $this->pdfText($this->get(route('scores.export-pdf')), 'academic-orphan');
        $this->assertStringContainsString('Data siswa tidak ditemukan', $text);
        $this->assertStringContainsString('62', $text);
        $this->assertMatchesRegularExpression('/Total Data\s*:\s*2\b/', $text);
    }

    public static function screenFilters(): array
    {
        return [
            'invoice-name' => ['invoices', ['search' => 'Siswa Audit'], 1],
            'invoice-type' => ['invoices', ['type' => 'COMPANY'], 0],
            'invoice-status' => ['invoices', ['status' => 'Paid'], 0],
            'payment-name' => ['payments', ['search' => 'Siswa Audit'], 1],
            'payroll-name' => ['payrolls', ['search' => 'Pegawai Audit'], 1],
            'payroll-miss' => ['payrolls', ['search' => 'NO-SUCH-PERSON'], 0],
            'student-id' => ['students', ['search' => 'STU-0001'], 1],
            'student-date' => ['students', ['date_from' => '2001-01-01', 'date_to' => '2001-01-02'], 0],
            'employee-date' => ['employees', ['date_from' => '2001-01-01', 'date_to' => '2001-01-02'], 0],
            'teacher-id' => ['teachers', ['search' => 'TCH-AUDIT'], 1],
            'teacher-teaching' => ['teachers', ['teaching' => 'Inactive'], 0],
            'assessment-class' => ['assessments', ['search' => 'CLS-AUDIT'], 1],
            'subject-status' => ['subjects', ['status' => 'FALSE'], 0],
        ];
    }

    #[DataProvider('screenFilters')]
    public function test_pdf_preserves_screen_filters(string $prefix, array $query, int $count): void
    {
        $response = $this->get(route($prefix.'.index', $query))->assertOk();
        $this->assertCount($count, $response->viewData($prefix));
        $text = $this->pdfText($this->get(route($prefix.'.export-pdf', $query)), 'filter-'.$prefix.'-'.array_key_first($query));
        $this->assertMatchesRegularExpression('/Total Data\s*:\s*'.$count.'\b/', $text);
    }

    public function test_unpaginated_reports_keep_all_rows_and_audit_export_exceeds_screen_limit(): void
    {
        for ($i = 1; $i <= 101; $i++) {
            $this->insertPdfRow('MASTER_AUDIT_LOG', ['Audit_ID' => 'AUD-EXTRA-'.$i, 'User_ID' => 'USR-AUDIT', 'Module' => 'AUDIT', 'Action' => 'AuditRow'.$i]);
        }
        $text = $this->pdfText($this->get(route('audit.export-pdf')), 'audit-full');
        $this->assertMatchesRegularExpression('/Total Data\s*:\s*102\b/', $text);
        $this->assertSame(101, preg_match_all('/AuditRow\d+\b/', $text));
        $this->assertStringContainsString('Audit Operator', $text);
        $text = $this->pdfText($this->get(route('audit.export-pdf', ['export_filter' => 'current_page'])), 'audit-screen');
        $this->assertMatchesRegularExpression('/Total Data\s*:\s*100\b/', $text);
        for ($i = 1; $i <= 12; $i++) {
            $this->insertPdfRow('MASTER_SUBJECT', ['Subject_ID' => 'SUB-PAGE-'.$i, 'Subject_Name' => 'MateriPage'.$i]);
        }
        $text = $this->pdfText($this->get(route('subjects.export-pdf', ['export_filter' => 'current_page'])), 'subjects-screen');
        $this->assertMatchesRegularExpression('/Total Data\s*:\s*13\b/', $text);
    }

    public function test_invalid_transaction_type_is_not_silently_counted_as_zero(): void
    {
        $this->insertPdfRow('FINANCE_TRANSACTION', ['Transaction_ID' => 'TRX-TYPE', 'Transaction_Date' => '2026-11-04', 'Type' => 'UNKNOWN', 'Amount' => 123]);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Tipe transaksi tidak valid');
        app(\App\Services\Finance\FinanceReportService::class)->getCashFlow();
    }

    public function test_cash_flow_empty_period_keeps_the_real_opening_balance(): void
    {
        $text = $this->pdfText($this->get(route('reports.finance.export-pdf', ['start_date' => '2026-12-01', 'end_date' => '2026-12-31'])), 'cash-flow-empty');
        $this->assertMatchesRegularExpression('/Total Data\s*:\s*0\b/', $text);
        $this->assertSame(2, substr_count($text, '350.000'));
    }

    public function test_archived_payroll_uses_the_same_paid_payload_as_download(): void
    {
        config(['filesystems.disks.local.root' => base_path('tmp/pdf-integrity/'.config('database.default').'/payroll-archive')]);
        \Illuminate\Support\Facades\Storage::forgetDisk('local');
        $events = \Mockery::mock(\App\Services\Core\EnterpriseEventService::class);
        $events->shouldReceive('dispatch')->andReturnNull();
        $this->app->instance(\App\Services\Core\EnterpriseEventService::class, $events);
        DB::table('MASTER_PAYROLL')->where('Payroll_ID', 'PRL-AUDIT')->update(['Status' => 'Approved']);
        $this->insertPdfRow('FINANCE_TRANSACTION', ['Transaction_ID' => 'TRX-PAYROLL', 'Transaction_Date' => '2026-11-04', 'Type' => 'Expense', 'Amount' => 3100000, 'Account_ID' => 'ACC-AUDIT', 'Reference_Type' => 'Payroll', 'Reference_ID' => 'PRL-AUDIT']);
        app(\App\Services\HR\PayrollService::class)->updateStatus('PRL-AUDIT', 'Paid', 'USR-AUDIT');
        $document = app(\App\Repositories\GoogleSheets\DocumentRepository::class)->getAll()->firstWhere('Document_Type', 'Payroll');
        $this->assertNotNull($document);
        $bytes = \Illuminate\Support\Facades\Storage::disk('local')->get($document['File_Path']);
        $this->assertSame(hash('sha256', $bytes), $document['Hash']);
        $text = $this->pdfText(\Illuminate\Testing\TestResponse::fromBaseResponse(response($bytes)), 'automatic-Payroll');
        foreach (['PERUSAHAAN FIXTURE AUDIT', 'PRL-AUDIT', 'Pegawai Audit', '3.100.000', 'LUNAS'] as $value) { $this->assertStringContainsString($value, $text); }
        $this->assertStringNotContainsString('Jl. Raya Wakamiya No. 88', $text);
    }

    public function test_email_attachments_are_real_pdf_without_sending_any_message(): void
    {
        foreach (['invoice', 'kwitansi'] as $type) {
            $captured = null;
            \Illuminate\Support\Facades\Mail::shouldReceive('raw')->once()->andReturnUsing(function ($body, $callback) use (&$captured) {
                $mime = new \Symfony\Component\Mime\Email();
                $callback(new \Illuminate\Mail\Message($mime));
                $captured = $mime->getAttachments()[0]->getBody();
            });
            $this->postJson(route('finance.smart_generator.send_email'), ['doc_type' => $type, 'source_type' => 'manual_invoice', 'doc_number' => 'EMAIL-AUDIT', 'client_name' => 'Penerima Audit', 'client_email' => 'audit@example.test', 'kwitansi_amount' => 500000, 'items' => [['name' => 'Produk Audit', 'qty' => 2, 'price' => 250000]]])->assertOk()->assertJson(['success' => true]);
            $this->assertIsString($captured);
            $text = $this->pdfText(\Illuminate\Testing\TestResponse::fromBaseResponse(response($captured)), 'email-'.$type);
            foreach (['EMAIL-AUDIT', 'Penerima Audit', '500.000'] as $value) { $this->assertStringContainsString($value, $text); }
        }
    }

    public function test_duplicate_source_events_are_reported_and_reversal_net_is_reconciled(): void
    {
        foreach ([['PAY-LEDGER-1','Income','Payment'], ['PAY-LEDGER-2','Income','Payment'], ['PAY-REVERSAL','Expense','PaymentReversal']] as [$id,$type,$ref]) {
            $this->insertPdfRow('FINANCE_TRANSACTION', ['Transaction_ID' => $id, 'Transaction_Date' => '2026-11-04', 'Account_ID' => 'ACC-AUDIT', 'Type' => $type, 'Amount' => 500000, 'Reference_Type' => $ref, 'Reference_ID' => 'PAY-AUDIT']);
        }
        $text = $this->pdfText($this->get(route('reports.finance.export-pdf')), 'cash-flow-duplicate-source');
        foreach (['Sumber tercatat berulang', 'PAY-LEDGER-1', 'PAY-LEDGER-2', 'PAY-REVERSAL', 'Penerimaan pembayaran', 'Pembatalan pembayaran', '850.000'] as $value) { $this->assertStringContainsString($value, $text); }
        $this->assertSame(7, DB::table('FINANCE_TRANSACTION')->count());
    }

    public function test_missing_company_configuration_never_invents_bank_or_signer_details(): void
    {
        \Illuminate\Support\Facades\Cache::forget('system_company_profile_payload');
        $profile = app(\App\Services\Core\SystemSettingService::class)->getCompanyProfile();
        $this->assertSame('', $profile['bank']['account_number']);
        $this->assertSame('', $profile['document']['signer_name']);
        $this->assertSame('', $profile['company']['npwp']);
        $text = $this->pdfText($this->post(route('finance.smart_generator.pdf'), ['doc_type' => 'invoice', 'source_type' => 'manual_invoice', 'client_name' => 'Klien Audit', 'items' => [['name' => 'Audit', 'price' => 500000, 'qty' => 1]]]), 'smart-no-config');
        foreach (['7343551023', '888-999-777', 'Helmi Maulana', 'Dr. Reza', '33.450.000', '01.234.567.8-901.000'] as $invented) { $this->assertStringNotContainsString($invented, $text); }
    }

    public static function roles(): array
    {
        return array_map(fn ($role) => [$role], ['ADMINISTRATOR', 'MASTER', 'FINANCE', 'HR', 'ACADEMIC', 'MARKETING', 'TEACHER', 'STUDENT', 'DIRECTOR']);
    }

    #[DataProvider('roles')]
    public function test_report_role_matrix(string $role): void
    {
        $this->pdfActor($role);
        foreach ([
            'users' => ['ADMINISTRATOR', 'MASTER'],
            'reports.finance' => ['ADMINISTRATOR', 'MASTER', 'FINANCE', 'DIRECTOR'],
            'payrolls' => ['ADMINISTRATOR', 'MASTER', 'HR', 'FINANCE'],
            'scores' => ['ACADEMIC', 'ADMINISTRATOR', 'MASTER'],
            'announcements' => ['ACADEMIC', 'ADMINISTRATOR', 'MASTER', 'TEACHER', 'DIRECTOR'],
        ] as $prefix => $allowed) {
            $response = $this->get(route($prefix.'.export-pdf'));
            if (in_array($role, $allowed, true)) {
                $response->assertOk();
                $this->assertStringStartsWith('%PDF-', $response->getContent());
            } else { $response->assertForbidden(); }
        }
    }
}

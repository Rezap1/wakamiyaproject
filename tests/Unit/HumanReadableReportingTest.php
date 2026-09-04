<?php

namespace Tests\Unit;

use App\Http\Controllers\Academic\ScheduleController;
use App\Http\Controllers\Finance\ReportController;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\SubjectRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Services\Academic\ScheduleService;
use App\Services\Finance\FinanceReportService;
use App\Support\Reporting\HumanReadableResolver;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class HumanReadableReportingTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_resolver_uses_master_snapshot_labels_and_human_fallbacks(): void
    {
        $students = collect([
            'STU-A' => ['Student_ID' => 'STU-A', 'Full_Name' => 'Aiko Tanaka', 'Student_Number' => 'NIS-001'],
        ]);
        $classes = collect([
            'CLS-A' => ['Class_ID' => 'CLS-A', 'Class_Name' => 'Kelas Sakura'],
        ]);
        $subjects = collect([
            'SUB-A' => ['Subject_ID' => 'SUB-A', 'Subject_Name' => 'Bahasa Jepang'],
        ]);
        $teachers = collect([
            'TCH-A' => ['Teacher_ID' => 'TCH-A', 'Full_Name' => 'Sensei A'],
        ]);
        $schedules = collect([
            'SCH-A' => [
                'Schedule_ID' => 'SCH-A',
                'Class_ID' => 'CLS-A',
                'Subject_ID' => 'SUB-A',
                'Teacher_ID' => 'TCH-A',
                'Day_Of_Week' => 'Friday',
                'Start_Time' => '08:00',
                'End_Time' => '09:00',
            ],
        ]);

        $label = HumanReadableResolver::scheduleLabel('SCH-A', $schedules, $classes, $subjects, $teachers);

        $this->assertSame('Aiko Tanaka', HumanReadableResolver::studentName('STU-A', $students));
        $this->assertSame('NIS-001', HumanReadableResolver::studentNumber('STU-A', $students));
        $this->assertStringContainsString('Bahasa Jepang', $label);
        $this->assertStringContainsString('Kelas Sakura', $label);
        $this->assertStringContainsString('Sensei A', $label);
        $this->assertSame('Data siswa tidak ditemukan', HumanReadableResolver::studentName('STU-MISSING', $students));
        $this->assertSame('Jadwal tidak ditemukan', HumanReadableResolver::scheduleLabel('SCH-MISSING', $schedules, $classes, $subjects, $teachers));
        $this->assertStringNotContainsString('SCH-A', $label);
        $this->assertStringNotContainsString('CLS-A', $label);
        $this->assertStringNotContainsString('SUB-A', $label);
    }

    public function test_schedule_export_outputs_human_labels_not_raw_ids(): void
    {
        $scheduleRow = [
            'Schedule_ID' => 'SCH-A',
            'Class_ID' => 'CLS-A',
            'Subject_ID' => 'SUB-A',
            'Teacher_ID' => 'TCH-A',
            'Day_Of_Week' => 'Friday',
            'Start_Time' => '08:00',
            'End_Time' => '09:00',
        ];
        $scheduleService = Mockery::mock(ScheduleService::class);
        $scheduleService->shouldReceive('getAll')->once()->andReturn(collect([$scheduleRow]));

        $classRepo = Mockery::mock(ClassRepositoryInterface::class);
        $classRepo->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Class_ID' => 'CLS-A', 'Class_Name' => 'Kelas Sakura'],
        ]));
        $subjectRepo = Mockery::mock(SubjectRepositoryInterface::class);
        $subjectRepo->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Subject_ID' => 'SUB-A', 'Subject_Name' => 'Bahasa Jepang'],
        ]));
        $teacherRepo = Mockery::mock(TeacherRepositoryInterface::class);
        $teacherRepo->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Teacher_ID' => 'TCH-A', 'Full_Name' => 'Sensei A'],
        ]));

        $this->app->instance(ClassRepositoryInterface::class, $classRepo);
        $this->app->instance(SubjectRepositoryInterface::class, $subjectRepo);
        $this->app->instance(TeacherRepositoryInterface::class, $teacherRepo);

        $config = (new H838ExposedScheduleController($scheduleService))->exposedExportConfig(Request::create('/exports', 'GET'));
        $mapped = $config['mapRow']($scheduleRow);

        $this->assertSame(['Kelas', 'Mata Pelajaran', 'Guru', 'Hari', 'Jam'], $config['headers']);
        $this->assertSame(['Kelas Sakura', 'Bahasa Jepang', 'Sensei A', 'Friday', '08:00 - 09:00'], $mapped);
        $this->assertRawIdsAbsent($mapped, ['SCH-A', 'CLS-A', 'SUB-A', 'TCH-A']);
    }

    public function test_finance_cash_flow_export_resolves_account_label_not_account_id(): void
    {
        $reportService = Mockery::mock(FinanceReportService::class);
        $reportService->shouldReceive('getCashFlow')->once()->andReturn([
            'transactions' => collect([
                [
                    'Transaction_Date' => '2026-09-04',
                    'Account_ID' => 'ACC-1',
                    'Type' => 'INCOME',
                    'Category' => 'Tuition',
                    'Description' => 'Pembayaran kursus',
                    'Amount' => 150000,
                ],
            ]),
            'accounts' => collect([
                ['Account_ID' => 'ACC-1', 'Account_Code' => '101', 'Account_Name' => 'Kas Utama'],
            ]),
            'opening_balance' => 0,
            'total_income' => 150000,
            'total_expense' => 0,
            'net_cash_flow' => 150000,
            'closing_balance' => 150000,
        ]);

        $config = (new ReportController($reportService))->getExportConfig(Request::create('/exports', 'GET'));
        $mapped = $config['mapRow']($config['data']->first());

        $this->assertSame('101 - Kas Utama', $mapped[1]);
        $this->assertRawIdsAbsent($mapped, ['ACC-1']);
        $this->assertNotContains('Account_ID', $config['headers']);
    }

    public function test_outstanding_invoice_export_keeps_business_number_without_student_id_fallback(): void
    {
        $reportService = Mockery::mock(FinanceReportService::class);
        $reportService->shouldReceive('getOutstandingInvoices')->once()->andReturn([
            'invoices' => collect([
                [
                    'Invoice_ID' => 'INV-2026-001',
                    'Invoice_Type' => 'STUDENT',
                    'Student_ID' => 'STU-A',
                    'Due_Date' => '2026-09-30',
                    'Display_Status' => 'Waiting Payment',
                    'Amount' => 500000,
                    'Paid_Amount' => 0,
                    'Remaining_Amount' => 500000,
                ],
            ]),
            'total_outstanding' => 500000,
        ]);

        $config = (new ReportController($reportService))->getExportConfig(Request::create('/exports?report_type=outstanding', 'GET'));
        $mapped = $config['mapRow']($config['data']->first());

        $this->assertSame('INV-2026-001', $mapped[1]);
        $this->assertSame('Siswa: Data siswa tidak ditemukan', $mapped[2]);
        $this->assertRawIdsAbsent($mapped, ['STU-A']);
    }

    public function test_salary_slip_template_does_not_display_raw_employee_reference_id(): void
    {
        $html = view('document.pdf.salary-slip', [
            'document' => [
                'Reference_ID' => 'EMP-A',
                'Title' => 'Aiko Employee',
                'Department' => 'HR',
                'Role' => 'Staff',
            ],
        ])->render();

        $this->assertStringContainsString('No. Pegawai', $html);
        $this->assertStringNotContainsString('Employee ID', $html);
        $this->assertStringNotContainsString('EMP-A', $html);
    }

    private function assertRawIdsAbsent(array $cells, array $tokens): void
    {
        $joined = implode(' | ', array_map(static fn ($value) => (string) $value, $cells));

        foreach ($tokens as $token) {
            $this->assertStringNotContainsString($token, $joined);
        }
    }
}

final class H838ExposedScheduleController extends ScheduleController
{
    public function exposedExportConfig(Request $request): array
    {
        return $this->getExportConfig($request);
    }
}

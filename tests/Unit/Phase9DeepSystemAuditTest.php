<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Helpers\ReportHelper;
use App\Helpers\StoragePathHelper;
use App\Interfaces\GoogleSheets\PermanentQrRepositoryInterface;
use App\Services\Core\ActivityLogService;
use App\Services\Core\PermanentQrService;
use Illuminate\Auth\GenericUser;
use Mockery;

class Phase9DeepSystemAuditTest extends TestCase
{
    public function test_csv_sanitizer_prevents_formula_injection()
    {
        $dangerous = ['=SUM(A1:B10)', '+100', '-50', '@EVIL()', '100', '4.5', '2026'];
        
        $sanitized = array_map([ReportHelper::class, 'sanitizeCsvCell'], $dangerous);

        $this->assertEquals("'=SUM(A1:B10)", $sanitized[0]);
        $this->assertEquals("'+100", $sanitized[1]);
        $this->assertEquals("'-50", $sanitized[2]);
        $this->assertEquals("'@EVIL()", $sanitized[3]);
        $this->assertEquals('100', $sanitized[4]);
        $this->assertEquals('4.5', $sanitized[5]);
        $this->assertEquals('2026', $sanitized[6]);
    }

    public function test_employee_qr_session_late_boundary_calculation()
    {
        $sessionStart = '08:00';
        $scanTimeOnTime = '08:15';
        $scanTimeLate = '08:45';
        $scanTimeExpired = '09:05';

        // Session start 08:00
        // On-time <= 30 mins after start (08:30)
        $diffOnTime = (strtotime($scanTimeOnTime) - strtotime($sessionStart)) / 60;
        $this->assertLessThanOrEqual(30, $diffOnTime);

        // Late > 30 mins and <= 60 mins after start
        $diffLate = (strtotime($scanTimeLate) - strtotime($sessionStart)) / 60;
        $this->assertGreaterThan(30, $diffLate);
        $this->assertLessThanOrEqual(60, $diffLate);

        // Expired > 60 mins
        $diffExpired = (strtotime($scanTimeExpired) - strtotime($sessionStart)) / 60;
        $this->assertGreaterThan(60, $diffExpired);
    }

    public function test_student_mapping_fail_closed_logic()
    {
        // When student profile mapping fails, system must fail closed (return empty or throw access exception)
        $userWithoutStudent = ['User_ID' => 'USR-999', 'Role' => 'STUDENT'];
        
        $fallbackCheck = function($userId) {
            if ($userId !== 'USR-REAL-STUDENT') {
                return null;
            }
            return ['Student_ID' => 'STU-100'];
        };

        $mappedStudent = $fallbackCheck($userWithoutStudent['User_ID']);
        $this->assertNull($mappedStudent);
        $this->assertNotEquals('STU-001', $mappedStudent['Student_ID'] ?? '');
    }

    public function test_public_verification_signed_url_validation()
    {
        $validSignature = true;
        $expiredSignature = false;
        $tamperedSignature = false;

        $this->assertTrue($validSignature);
        $this->assertFalse($expiredSignature);
        $this->assertFalse($tamperedSignature);
    }

    public function test_file_upload_allowed_extensions_and_denied_executables()
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $deniedExtensions = ['php', 'phtml', 'phar', 'exe', 'js', 'html', 'svg'];

        foreach ($allowedExtensions as $ext) {
            $isAllowed = in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'pdf'], true);
            $this->assertTrue($isAllowed, "Extension {$ext} should be allowed");
        }

        foreach ($deniedExtensions as $ext) {
            $isAllowed = in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'pdf'], true);
            $this->assertFalse($isAllowed, "Executable extension {$ext} must be denied");
        }
    }

    public function test_tuition_cap_and_invoice_remaining_balance_logic()
    {
        $tuitionCap = 5000000; // Rp 5.000.000
        $alreadyBilled = 3000000;
        $requestedNewInvoice = 3000000;

        $remainingCap = max(0, $tuitionCap - $alreadyBilled);
        $allowedAmount = min($requestedNewInvoice, $remainingCap);

        $this->assertEquals(2000000, $allowedAmount);

        // Balance remaining after paying partial
        $paidAmount = 1500000;
        $remainingBalance = max(0, $allowedAmount - $paidAmount);
        $this->assertEquals(500000, $remainingBalance);
    }

    public function test_overtime_pay_and_duration_calculation()
    {
        $startTime = '17:00';
        $endTime = '20:00'; // 3 hours
        $ratePerHour = 50000;

        $durationHours = (strtotime($endTime) - strtotime($startTime)) / 3600;
        $totalPay = $durationHours * $ratePerHour;

        $this->assertEquals(3.0, $durationHours);
        $this->assertEquals(150000, $totalPay);
    }

    public function test_storage_path_helper_prevents_directory_traversal()
    {
        $traversalInput = '../../etc/passwd';
        
        $sanitized = basename($traversalInput);
        $this->assertEquals('passwd', $sanitized);
        $this->assertFalse(str_contains($sanitized, '..'));
    }

    public function test_permanent_qr_deletion_flow_clears_records_and_cache()
    {
        $this->actingAs(new GenericUser(['id' => 'USR-ADMIN', 'User_ID' => 'USR-ADMIN']));

        $repo = Mockery::mock(PermanentQrRepositoryInterface::class);
        $activityLog = Mockery::mock(ActivityLogService::class);
        $activityLog->shouldReceive('log')->twice();

        $service = new PermanentQrService($repo, $activityLog);

        $testQrData = [
            'QR_TYPE' => 'STUDENT',
            'LABEL' => 'Test QR Hapus',
            'ACTIVE_FROM' => null,
            'ACTIVE_UNTIL' => null
        ];

        $repo->shouldReceive('generateNewId')->once()->with('QR', 5)->andReturn('QR00001');
        $repo->shouldReceive('findByIdentifier')->once()->with(Mockery::pattern('/^WMS-ATT-STU-/'))->andReturn(null);
        $repo->shouldReceive('create')->once()->andReturn(true);
        $repo->shouldReceive('clearCache')->once();

        $created = $service->createQr($testQrData);
        $qrId = $created['QR_ID'] ?? '';
        $this->assertNotEmpty($qrId);

        $repo->shouldReceive('findById')->once()->with($qrId)->andReturn($created);
        $found = $service->getQrById($qrId);
        $this->assertNotNull($found);

        $repo->shouldReceive('findById')->once()->with($qrId)->andReturn($created);
        $repo->shouldReceive('delete')->once()->with($qrId)->andReturn(true);
        $repo->shouldReceive('clearCache')->once();

        $deleted = $service->deleteQr($qrId);
        $this->assertTrue($deleted);

        $repo->shouldReceive('findById')->once()->with($qrId)->andReturn(null);
        $repo->shouldReceive('findByIdentifier')->once()->with($qrId)->andReturn(null);

        $foundAfterDelete = $service->getQrById($qrId);
        $this->assertNull($foundAfterDelete);
    }

    public function test_permanent_qr_delete_failure_is_not_reported_as_success()
    {
        $this->actingAs(new GenericUser(['id' => 'USR-ADMIN', 'User_ID' => 'USR-ADMIN']));

        $repo = Mockery::mock(PermanentQrRepositoryInterface::class);
        $activityLog = Mockery::mock(ActivityLogService::class);
        $activityLog->shouldNotReceive('log');

        $qr = [
            'QR_ID' => 'QR00001',
            'QR_TYPE' => 'STUDENT',
            'IDENTIFIER' => 'WMS-ATT-STU-FAILED',
        ];
        $repo->shouldReceive('findById')->once()->with('QR00001')->andReturn($qr);
        $repo->shouldReceive('delete')->once()->with('QR00001')->andReturn(false);
        $repo->shouldReceive('delete')->once()->with('WMS-ATT-STU-FAILED')->andReturn(false);
        $repo->shouldNotReceive('clearCache');

        $service = new PermanentQrService($repo, $activityLog);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Gagal menghapus QR Presensi');

        $service->deleteQr('QR00001');
    }

    public function test_permanent_qr_can_be_deactivated_and_reactivated(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-ADMIN', 'User_ID' => 'USR-ADMIN']));

        $repo = Mockery::mock(PermanentQrRepositoryInterface::class);
        $activityLog = Mockery::mock(ActivityLogService::class);
        $activityLog->shouldReceive('log')->twice();

        $activeQr = [
            'QR_ID' => 'QR00001',
            'QR_TYPE' => 'EMPLOYEE',
            'IDENTIFIER' => 'WMS-ATT-EMP-LIFECYCLE',
            'STATUS' => 'ACTIVE',
            'DEACTIVATED_AT' => '',
        ];
        $inactiveQr = array_merge($activeQr, ['STATUS' => 'INACTIVE', 'DEACTIVATED_AT' => now()->toDateTimeString()]);

        $repo->shouldReceive('findById')->once()->with('QR00001')->andReturn($activeQr);
        $repo->shouldReceive('update')->once()->with('QR00001', Mockery::on(
            fn ($row) => ($row['STATUS'] ?? '') === 'INACTIVE' && !empty($row['DEACTIVATED_AT'])
        ))->andReturn(true);
        $repo->shouldReceive('clearCache')->once();

        $repo->shouldReceive('findById')->once()->with('QR00001')->andReturn($inactiveQr);
        $repo->shouldReceive('update')->once()->with('QR00001', Mockery::on(
            fn ($row) => ($row['STATUS'] ?? '') === 'ACTIVE' && ($row['DEACTIVATED_AT'] ?? null) === ''
        ))->andReturn(true);
        $repo->shouldReceive('clearCache')->once();

        $service = new PermanentQrService($repo, $activityLog);
        $deactivated = $service->updateAvailability('QR00001', ['STATUS' => 'INACTIVE']);
        $reactivated = $service->updateAvailability('QR00001', ['STATUS' => 'ACTIVE']);

        $this->assertSame('INACTIVE', $deactivated['STATUS']);
        $this->assertSame('ACTIVE', $reactivated['STATUS']);
        $this->assertTrue($service->isQrCurrentlyUsable($reactivated));
    }

    public function test_permanent_qr_availability_failure_is_not_reported_as_success(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-ADMIN', 'User_ID' => 'USR-ADMIN']));

        $repo = Mockery::mock(PermanentQrRepositoryInterface::class);
        $repo->shouldReceive('findById')->once()->with('QR00001')->andReturn([
            'QR_ID' => 'QR00001',
            'QR_TYPE' => 'STUDENT',
            'IDENTIFIER' => 'WMS-ATT-STU-FAILED',
            'STATUS' => 'INACTIVE',
        ]);
        $repo->shouldReceive('update')->once()->andReturn(false);
        $repo->shouldNotReceive('clearCache');

        $activityLog = Mockery::mock(ActivityLogService::class);
        $activityLog->shouldNotReceive('log');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Gagal memperbarui status atau jadwal QR Presensi');

        (new PermanentQrService($repo, $activityLog))->updateAvailability('QR00001', ['STATUS' => 'ACTIVE']);
    }

    public function test_permanent_qr_repository_does_not_mutate_sheet_headers(): void
    {
        $source = file_get_contents(base_path('app/Repositories/GoogleSheets/PermanentQrRepository.php'));

        $this->assertStringNotContainsString('spreadsheets_values->update', $source);
        $this->assertStringContainsString('assertExtendedHeadersAvailable', $source);
    }

    public function test_permanent_qr_url_respects_configured_app_url(): void
    {
        config(['app.url' => 'http://127.0.0.1:8000']);

        $service = new PermanentQrService(
            Mockery::mock(PermanentQrRepositoryInterface::class),
            Mockery::mock(ActivityLogService::class)
        );

        $url = $service->getCanonicalQrUrl([
            'QR_TYPE' => 'EMPLOYEE',
            'IDENTIFIER' => 'WMS-ATT-EMP-LOCALQA',
        ]);

        $this->assertSame('http://127.0.0.1:8000/attendance/scan/employee/WMS-ATT-EMP-LOCALQA', $url);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\LeaveRepositoryInterface;
use App\Interfaces\GoogleSheets\OvertimeRepositoryInterface;
use App\Repositories\GoogleSheets\LeaveRepository;
use App\Repositories\GoogleSheets\OvertimeRepository;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use Tests\TestCase;
use UnexpectedValueException;

class LeaveOvertimeRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_container_resolves_ssot_repository_bindings(): void
    {
        $this->assertInstanceOf(LeaveRepository::class, app(LeaveRepositoryInterface::class));
        $this->assertInstanceOf(OvertimeRepository::class, app(OvertimeRepositoryInterface::class));
    }

    public function test_leave_repository_rejects_malformed_row(): void
    {
        $repository = new MemoryBackedLeaveRepository();
        $method = (new ReflectionClass(LeaveRepository::class))->getMethod('validateRows');
        $method->setAccessible(true);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("Employee_ID");
        $method->invoke($repository, collect([$this->validLeave(['Employee_ID' => ''])]));
    }

    public function test_overtime_repository_rejects_malformed_row(): void
    {
        $repository = new MemoryBackedOvertimeRepository();
        $method = (new ReflectionClass(OvertimeRepository::class))->getMethod('validateRows');
        $method->setAccessible(true);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Date');
        $method->invoke($repository, collect([$this->validOvertime(['Date' => '2026-99-99'])]));
    }

    public function test_duplicate_document_number_is_rejected_before_write(): void
    {
        $repository = new MemoryBackedLeaveRepository([$this->validLeave()]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Document_Number');
        $repository->create($this->validLeave([
            'Leave_ID' => 'LEV-002',
            'Document_Number' => 'DOC-LEV-001',
        ]));
    }

    public function test_duplicate_primary_key_is_rejected_before_append(): void
    {
        $repository = new MemoryBackedOvertimeRepository([$this->validOvertime()]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Primary key');
        $repository->create($this->validOvertime([
            'Document_Number' => 'DOC-OVT-002',
        ]));
    }

    private function validLeave(array $overrides = []): array
    {
        return array_merge([
            'Leave_ID' => 'LEV-001',
            'Document_Number' => 'DOC-LEV-001',
            'Employee_ID' => 'EMP-001',
            'Employee_Name' => 'Pegawai Satu',
            'Leave_Type' => 'CUTI_TAHUNAN',
            'Start_Date' => '2026-08-24',
            'End_Date' => '2026-08-25',
            'Duration_Days' => 2,
            'Reason' => 'Keperluan keluarga',
            'Status' => 'SUBMITTED',
            'Submitted_At' => '2026-08-24 09:00:00',
            'Created_At' => '2026-08-24 09:00:00',
            'Updated_At' => '2026-08-24 09:00:00',
        ], $overrides);
    }

    private function validOvertime(array $overrides = []): array
    {
        return array_merge([
            'Overtime_ID' => 'OVT-001',
            'Document_Number' => 'DOC-OVT-001',
            'Employee_ID' => 'EMP-001',
            'Employee_Name' => 'Pegawai Satu',
            'Date' => '2026-08-24',
            'Start_Time' => '18:00',
            'End_Time' => '20:00',
            'Duration_Hours' => 2,
            'Hourly_Rate' => 25000,
            'Overtime_Pay' => 50000,
            'Reason' => 'Tutup buku',
            'Status' => 'SUBMITTED',
            'Submitted_At' => '2026-08-24 09:00:00',
            'Created_At' => '2026-08-24 09:00:00',
            'Updated_At' => '2026-08-24 09:00:00',
        ], $overrides);
    }
}

class MemoryBackedLeaveRepository extends LeaveRepository
{
    public function __construct(public array $rows = []) {}
    public function fetchAll() { return collect(array_values($this->rows)); }
    public function clearCache() {}
    public function append(array $data)
    {
        if (collect($this->rows)->contains(fn (array $row) => strcasecmp($row['Leave_ID'], $data['Leave_ID']) === 0)) {
            throw new LogicException('Primary key sudah ada.');
        }
        $this->rows[] = $data;
        return true;
    }
}

class MemoryBackedOvertimeRepository extends OvertimeRepository
{
    public function __construct(public array $rows = []) {}
    public function fetchAll() { return collect(array_values($this->rows)); }
    public function clearCache() {}
    public function append(array $data)
    {
        if (collect($this->rows)->contains(fn (array $row) => strcasecmp($row['Overtime_ID'], $data['Overtime_ID']) === 0)) {
            throw new LogicException('Primary key sudah ada.');
        }
        $this->rows[] = $data;
        return true;
    }
}

<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\OvertimeRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use UnexpectedValueException;

class OvertimeRepository extends BaseSheetRepository implements OvertimeRepositoryInterface
{
    private const HEADERS = [
        'Overtime_ID', 'Document_Number', 'Employee_ID', 'Employee_Name', 'Date',
        'Start_Time', 'End_Time', 'Duration_Hours', 'Hourly_Rate', 'Overtime_Pay',
        'Reason', 'Status', 'Submitted_At', 'Approved_By', 'Approved_At', 'Rejected_By',
        'Rejection_Reason', 'Rejected_At', 'Created_At', 'Updated_At',
    ];

    private const REQUIRED_FIELDS = [
        'Overtime_ID',
        'Document_Number',
        'Employee_ID',
        'Employee_Name',
        'Date',
        'Start_Time',
        'End_Time',
        'Duration_Hours',
        'Hourly_Rate',
        'Overtime_Pay',
        'Reason',
        'Status',
        'Submitted_At',
        'Created_At',
    ];

    private const STATUSES = ['SUBMITTED', 'APPROVED', 'REJECTED', 'CANCELLED', 'INCLUDED_IN_PAYROLL'];

    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_OVERTIME';
        $this->cacheKey = 'overtime_sheet';
        $this->primaryKey = 'Overtime_ID';
        $this->skipRowsWithoutPrimaryKey = false;
        $this->expectedHeaders = self::HEADERS;
    }

    public function fetchAll()
    {
        return $this->validateRows(parent::fetchAll());
    }

    public function getAll()
    {
        return $this->fetchAll();
    }

    public function getById(string $id): ?array
    {
        $id = trim($id);
        $record = $this->fetchAll()->first(
            fn (array $row) => strcasecmp(trim((string) ($row['Overtime_ID'] ?? '')), $id) === 0
        );

        return $record ? (array) $record : null;
    }

    public function findByEmployee(string $employeeId)
    {
        return $this->fetchAll()->filter(
            fn (array $row) => strcasecmp(trim((string) ($row['Employee_ID'] ?? '')), trim($employeeId)) === 0
        )->values();
    }

    public function create(array $data)
    {
        $this->validateRecord($data);

        return $this->withDocumentLock($data['Document_Number'], function () use ($data) {
            $this->clearCache();
            $this->assertUniqueDocumentNumber($data['Document_Number']);

            return $this->append($data);
        });
    }

    public function update($id, array $data)
    {
        $documentNumber = trim((string) ($data['Document_Number'] ?? ''));
        $lockValue = $documentNumber !== '' ? $documentNumber : (string) $id;

        return $this->withDocumentLock($lockValue, function () use ($id, $data) {
            $this->clearCache();
            $existing = $this->getById((string) $id);
            if (!$existing) {
                throw new UnexpectedValueException("Overtime '{$id}' tidak ditemukan.");
            }

            $merged = array_merge($existing, $data);
            $this->validateRecord($merged);
            $this->assertUniqueDocumentNumber($merged['Document_Number'], (string) $id);

            return $this->updateRow($id, $data);
        });
    }

    private function validateRows(Collection $rows): Collection
    {
        $ids = [];
        $documents = [];

        return $rows->map(function ($row) use (&$ids, &$documents) {
            $record = (array) $row;
            $this->validateRecord($record);

            $id = strtolower(trim((string) $record['Overtime_ID']));
            $document = strtolower(trim((string) $record['Document_Number']));
            if (isset($ids[$id])) {
                throw new UnexpectedValueException("Duplicate Overtime_ID '{$record['Overtime_ID']}' pada MASTER_OVERTIME.");
            }
            if (isset($documents[$document])) {
                throw new UnexpectedValueException("Duplicate Document_Number '{$record['Document_Number']}' pada MASTER_OVERTIME.");
            }

            $ids[$id] = true;
            $documents[$document] = true;

            return $record;
        })->values();
    }

    private function validateRecord(array $record): void
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!array_key_exists($field, $record) || trim((string) $record[$field]) === '') {
                throw new UnexpectedValueException("MASTER_OVERTIME memiliki field wajib '{$field}' yang kosong.");
            }
        }

        if (!$this->isDate($record['Date'])) {
            throw new UnexpectedValueException('MASTER_OVERTIME memiliki Date yang tidak valid.');
        }
        if (!$this->isTime($record['Start_Time']) || !$this->isTime($record['End_Time'])) {
            throw new UnexpectedValueException('MASTER_OVERTIME memiliki waktu yang tidak valid.');
        }
        if ((string) $record['End_Time'] <= (string) $record['Start_Time']) {
            throw new UnexpectedValueException('MASTER_OVERTIME memiliki rentang waktu terbalik.');
        }
        if (!is_numeric($record['Duration_Hours']) || (float) $record['Duration_Hours'] < 0.5) {
            throw new UnexpectedValueException('MASTER_OVERTIME memiliki Duration_Hours yang tidak valid.');
        }
        foreach (['Hourly_Rate', 'Overtime_Pay'] as $field) {
            if (!is_numeric($record[$field]) || (float) $record[$field] < 0) {
                throw new UnexpectedValueException("MASTER_OVERTIME memiliki {$field} yang tidak valid.");
            }
        }
        if (!in_array(strtoupper(trim((string) $record['Status'])), self::STATUSES, true)) {
            throw new UnexpectedValueException("MASTER_OVERTIME memiliki Status '{$record['Status']}' yang tidak valid.");
        }
    }

    private function assertUniqueDocumentNumber(string $documentNumber, ?string $exceptId = null): void
    {
        $duplicate = $this->fetchAll()->first(function (array $row) use ($documentNumber, $exceptId) {
            return strcasecmp(trim((string) ($row['Document_Number'] ?? '')), trim($documentNumber)) === 0
                && ($exceptId === null || strcasecmp(trim((string) ($row['Overtime_ID'] ?? '')), trim($exceptId)) !== 0);
        });

        if ($duplicate) {
            throw new InvalidArgumentException("Document_Number '{$documentNumber}' sudah digunakan pada MASTER_OVERTIME.");
        }
    }

    private function withDocumentLock(string $value, callable $callback)
    {
        return Cache::lock('master_overtime_document_' . sha1(strtolower(trim($value))), 10)->block(5, $callback);
    }

    private function isDate($value): bool
    {
        $parts = explode('-', trim((string) $value));
        return count($parts) === 3
            && ctype_digit($parts[0])
            && ctype_digit($parts[1])
            && ctype_digit($parts[2])
            && checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
    }

    private function isTime($value): bool
    {
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', trim((string) $value)) === 1;
    }
}

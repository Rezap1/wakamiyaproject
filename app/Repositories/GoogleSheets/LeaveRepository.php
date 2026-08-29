<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\LeaveRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use UnexpectedValueException;

class LeaveRepository extends BaseSheetRepository implements LeaveRepositoryInterface
{
    private const HEADERS = [
        'Leave_ID', 'Document_Number', 'Employee_ID', 'Employee_Name', 'Leave_Type',
        'Start_Date', 'End_Date', 'Duration_Days', 'Reason', 'Status', 'Submitted_At',
        'Approved_By', 'Approved_At', 'Rejected_By', 'Rejection_Reason', 'Rejected_At',
        'Cancelled_By', 'Cancelled_At', 'Created_At', 'Updated_At',
    ];

    private const REQUIRED_FIELDS = [
        'Leave_ID',
        'Document_Number',
        'Employee_ID',
        'Employee_Name',
        'Leave_Type',
        'Start_Date',
        'End_Date',
        'Duration_Days',
        'Reason',
        'Status',
        'Submitted_At',
        'Created_At',
    ];

    private const STATUSES = ['SUBMITTED', 'APPROVED', 'REJECTED', 'CANCELLED', 'COMPLETED'];

    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_LEAVE';
        $this->cacheKey = 'leave_sheet';
        $this->primaryKey = 'Leave_ID';
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
            fn (array $row) => strcasecmp(trim((string) ($row['Leave_ID'] ?? '')), $id) === 0
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
                throw new UnexpectedValueException("Leave '{$id}' tidak ditemukan.");
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

            $id = strtolower(trim((string) $record['Leave_ID']));
            $document = strtolower(trim((string) $record['Document_Number']));
            if (isset($ids[$id])) {
                throw new UnexpectedValueException("Duplicate Leave_ID '{$record['Leave_ID']}' pada MASTER_LEAVE.");
            }
            if (isset($documents[$document])) {
                throw new UnexpectedValueException("Duplicate Document_Number '{$record['Document_Number']}' pada MASTER_LEAVE.");
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
                throw new UnexpectedValueException("MASTER_LEAVE memiliki field wajib '{$field}' yang kosong.");
            }
        }

        if (!$this->isDate($record['Start_Date']) || !$this->isDate($record['End_Date'])) {
            throw new UnexpectedValueException('MASTER_LEAVE memiliki tanggal yang tidak valid.');
        }
        if ((string) $record['Start_Date'] > (string) $record['End_Date']) {
            throw new UnexpectedValueException('MASTER_LEAVE memiliki rentang tanggal terbalik.');
        }
        if (!is_numeric($record['Duration_Days']) || (int) $record['Duration_Days'] < 1) {
            throw new UnexpectedValueException('MASTER_LEAVE memiliki Duration_Days yang tidak valid.');
        }
        if (!in_array(strtoupper(trim((string) $record['Status'])), self::STATUSES, true)) {
            throw new UnexpectedValueException("MASTER_LEAVE memiliki Status '{$record['Status']}' yang tidak valid.");
        }
    }

    private function assertUniqueDocumentNumber(string $documentNumber, ?string $exceptId = null): void
    {
        $duplicate = $this->fetchAll()->first(function (array $row) use ($documentNumber, $exceptId) {
            return strcasecmp(trim((string) ($row['Document_Number'] ?? '')), trim($documentNumber)) === 0
                && ($exceptId === null || strcasecmp(trim((string) ($row['Leave_ID'] ?? '')), trim($exceptId)) !== 0);
        });

        if ($duplicate) {
            throw new InvalidArgumentException("Document_Number '{$documentNumber}' sudah digunakan pada MASTER_LEAVE.");
        }
    }

    private function withDocumentLock(string $value, callable $callback)
    {
        return Cache::lock('master_leave_document_' . sha1(strtolower(trim($value))), 10)->block(5, $callback);
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
}

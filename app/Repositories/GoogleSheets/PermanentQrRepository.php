<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\PermanentQrRepositoryInterface;
use Carbon\Carbon;

class PermanentQrRepository extends BaseSheetRepository implements PermanentQrRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_PERMANENT_QR';
        $this->cacheKey = 'permanent_qr_sheet';
        $this->primaryKey = 'QR_ID';
    }

    public function fetchActive()
    {
        $all = $this->fetchAll();
        return $all->filter(function ($item) {
            return strtoupper($item['STATUS'] ?? '') === 'ACTIVE';
        })->values();
    }

    public function findByIdentifier(string $identifier)
    {
        $all = $this->fetchAll();
        $target = strtolower(trim($identifier));
        return $all->first(function ($item) use ($target) {
            return strtolower(trim((string) ($item['IDENTIFIER'] ?? ''))) === $target;
        });
    }

    public function findById(string $id)
    {
        $all = $this->fetchAll();
        $target = strtolower(trim($id));
        return $all->first(function ($item) use ($target) {
            $qrId = strtolower(trim((string) ($item['QR_ID'] ?? $item['id'] ?? '')));
            return $qrId === $target;
        });
    }

    public function create(array $data)
    {
        $this->assertExtendedHeadersAvailable();

        // Set standard fields if not present
        if (!isset($data['STATUS'])) {
            $data['STATUS'] = 'ACTIVE';
        }
        if (!isset($data['CREATED_AT'])) {
            $data['CREATED_AT'] = Carbon::now()->toDateTimeString();
        }
        if (!isset($data['UPDATED_AT'])) {
            $data['UPDATED_AT'] = Carbon::now()->toDateTimeString();
        }
        
        return $this->append($data);
    }

    public function update($id, array $data)
    {
        $this->assertExtendedHeadersAvailable();

        return parent::update($id, $data);
    }

    public function deactivate(string $id, string $actorUserId)
    {
        $record = $this->findById($id);
        if (!$record) {
            $record = $this->findByIdentifier($id);
        }
        if ($record) {
            $targetId = $record['QR_ID'] ?? $id;
            $data = [
                'STATUS' => 'INACTIVE',
                'DEACTIVATED_AT' => Carbon::now()->toDateTimeString(),
                'UPDATED_AT' => Carbon::now()->toDateTimeString(),
                'UPDATED_BY' => $actorUserId
            ];
            $this->update($targetId, $data);
            $this->clearCache();
            return true;
        }
        return false;
    }

    private function assertExtendedHeadersAvailable(): void
    {
        $requiredHeaders = [
            'QR_ID',
            'QR_TYPE',
            'IDENTIFIER',
            'LABEL',
            'STATUS',
            'ACTIVE_FROM',
            'ACTIVE_UNTIL',
            'CREATED_AT',
            'CREATED_BY',
            'UPDATED_AT',
            'UPDATED_BY',
            'DEACTIVATED_AT',
        ];

        try {
            $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $this->sheetName . '!1:1');
            $headers = $response->getValues()[0] ?? [];
            $missing = array_values(array_diff($requiredHeaders, $headers));
            if ($missing) {
                throw new \RuntimeException(
                    'Schema MASTER_PERMANENT_QR belum lengkap. Header wajib hilang: ' . implode(', ', $missing)
                );
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to validate MASTER_PERMANENT_QR headers: ' . $e->getMessage());
            throw $e;
        }
    }
}

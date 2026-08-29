<?php

namespace App\Repositories\GoogleSheets;

use Google_Client;
use Google_Service_Sheets;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

abstract class BaseSheetRepository
{
    private const WRITE_INPUT_OPTION = 'RAW';

    protected $client;
    protected $service;
    protected $spreadsheetId;
    protected $sheetName;
    protected $cacheKey;
    protected $primaryKey = 'id';
    protected $cacheTtl = 3600; // 1 hour default
    protected $inMemoryRows = null;
    protected $skipRowsWithoutPrimaryKey = true;
    protected $expectedHeaders = [];

    public function __construct()
    {
        $this->spreadsheetId = config('services.google.spreadsheet_id');
        $this->cacheTtl = config('cache.wms.master', 60); // Default to 60s
        $this->client = new Google_Client();
        $this->client->setApplicationName('Wakamiya Management System');
        $this->client->setScopes([Google_Service_Sheets::SPREADSHEETS]);
        $this->client->setAccessType('offline');
        
        $credentialsPath = storage_path('app/google-credentials.json');
        if (file_exists($credentialsPath)) {
            $this->client->setAuthConfig($credentialsPath);
        } else {
            Log::warning('Google Credentials file not found at: ' . $credentialsPath);
        }

        $this->service = new Google_Service_Sheets($this->client);
    }

    /**
     * Get all rows from the sheet, cached.
     * Maps the rows to associative arrays using the first row as headers.
     */
    public function fetchAll()
    {
        if ($this->inMemoryRows !== null) {
            return clone $this->inMemoryRows;
        }

        $startTime = microtime(true);
        $isHit = Cache::has($this->cacheKey . '_all');

        try {
            $data = Cache::remember($this->cacheKey . '_all', $this->cacheTtl, function () {
                return retry(3, function () {
                    $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $this->sheetName);
                    $values = $response->getValues();

                    if (empty($values)) {
                        $this->assertExpectedHeaders([]);
                        return collect([]);
                    }

                    $headers = array_map(fn ($header) => trim((string) $header), array_shift($values));
                    $this->assertExpectedHeaders($headers);
                    if (empty($values)) {
                        return collect([]);
                    }
                    $data = [];

                    foreach ($values as $row) {
                        $item = [];
                        $isEmptyRow = true;
                        
                        foreach ($headers as $index => $header) {
                            $val = $row[$index] ?? null;
                            $item[$header] = $val;
                            
                            // Check if at least one column has data
                            if (!empty(trim((string)$val))) {
                                $isEmptyRow = false;
                            }
                        }

                        // Skip completely empty rows
                        if ($isEmptyRow) {
                            continue;
                        }

                        // Skip rows missing a primary key (if defined)
                        if ($this->skipRowsWithoutPrimaryKey
                            && !empty($this->primaryKey)
                            && empty(trim((string)($item[$this->primaryKey] ?? '')))) {
                            continue;
                        }

                        $data[] = $item;
                    }

                    return collect($data);
                }, 1000);
            });

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            Log::info("Google Sheets FetchAll on {$this->sheetName}", [
                'duration_ms' => $duration,
                'cache' => $isHit ? 'HIT' : 'MISS'
            ]);

            $this->inMemoryRows = $data instanceof \Illuminate\Support\Collection ? $data : collect($data);

            return clone $this->inMemoryRows;
        } catch (\Exception $e) {
            Log::error("Google API Error during fetchAll on {$this->sheetName}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Clear the cache for this sheet.
     */
    public function clearCache()
    {
        $this->inMemoryRows = null;
        Cache::forget($this->cacheKey . '_all');

        if (in_array($this->sheetName, [
            'MASTER_EMPLOYEE',
            'MASTER_STUDENT',
            'MASTER_TEACHER',
            'MASTER_USER',
            'MASTER_ROLE',
            'MASTER_CLASS',
            'MASTER_BATCH',
        ], true)) {
            \App\Helpers\UserResolverHelper::clearCache();
        }
    }

    /**
     * Generate a new safe sequential ID based on maximum existing number.
     * e.g., Prefix 'USR', padding 6 -> USR000001
     */
    public function generateNewId(string $prefix, int $padding = 6): string
    {
        $lockKey = $this->sheetName . '_write_lock';
        $counterKey = 'id_counter_' . $this->sheetName . '_' . $prefix;

        return Cache::lock($lockKey, 10)->block(5, function () use ($prefix, $padding, $counterKey) {
            if (!Cache::has($counterKey)) {
                $allData = $this->fetchAll();
                $maxNumber = 0;

                foreach ($allData as $row) {
                    if (isset($row[$this->primaryKey])) {
                        $idValue = (string) $row[$this->primaryKey];
                        if (str_starts_with($idValue, $prefix)) {
                            $numericPart = substr($idValue, strlen($prefix));
                            if (is_numeric($numericPart)) {
                                $maxNumber = max($maxNumber, (int) $numericPart);
                            }
                        }
                    }
                }
                Cache::forever($counterKey, $maxNumber);
            }

            $nextNumber = Cache::increment($counterKey);
            return $prefix . str_pad((string)$nextNumber, $padding, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Append a new row to the sheet.
     */
    public function append(array $data)
    {
        $startTime = microtime(true);
        $lockKey = $this->sheetName . '_write_lock';

        try {
            return Cache::lock($lockKey, 10)->block(5, function () use ($data, $startTime) {
                $result = retry(3, function () use ($data) {
                    // Read current rows inside the write lock so PK validation and append are atomic.
                    $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $this->sheetName);
                    $values = $response->getValues();
                    $headers = array_map(fn ($header) => trim((string) $header), $values[0] ?? []);

                    if (empty($headers)) {
                        throw new \RuntimeException("Header sheet '{$this->sheetName}' tidak tersedia.");
                    }
                    $this->assertExpectedHeaders($headers);

                    $primaryKeyHeader = collect($headers)->first(function ($header) {
                        return strcasecmp(trim((string) $header), trim((string) $this->primaryKey)) === 0;
                    });

                    if ($primaryKeyHeader === null) {
                        throw new \RuntimeException("Header primary key '{$this->primaryKey}' tidak ditemukan.");
                    }

                    $primaryKeyValue = trim((string) ($data[$primaryKeyHeader] ?? $data[$this->primaryKey] ?? ''));
                    if ($primaryKeyValue === '') {
                        throw new \InvalidArgumentException("Nilai primary key '{$this->primaryKey}' wajib diisi.");
                    }

                    $primaryKeyIndex = array_search($primaryKeyHeader, $headers, true);
                    foreach (array_slice($values, 1) as $existingRow) {
                        $existingId = trim((string) ($existingRow[$primaryKeyIndex] ?? ''));
                        if ($existingId !== '' && strcasecmp($existingId, $primaryKeyValue) === 0) {
                            throw new \LogicException(
                                "Primary key '{$primaryKeyValue}' sudah ada di sheet '{$this->sheetName}'."
                            );
                        }
                    }

                    $rowValues = [];
                    foreach ($headers as $header) {
                        $rowValues[] = $data[$header] ?? '';
                    }

                    $body = new \Google_Service_Sheets_ValueRange([
                        'values' => [$rowValues]
                    ]);

                    $params = [
                        'valueInputOption' => self::WRITE_INPUT_OPTION
                    ];

                    return $this->service->spreadsheets_values->append($this->spreadsheetId, $this->sheetName, $body, $params);
                }, 1000, fn ($e) => !$e instanceof \InvalidArgumentException
                    && !$e instanceof \LogicException
                    && !$e instanceof \RuntimeException);
                
                $this->clearCache();
                
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                Log::info("Google Sheets Append on {$this->sheetName}", ['duration_ms' => $duration]);
                
                return $result;
            });
        } catch (\Exception $e) {
            Log::error("Google API Error during append on {$this->sheetName}: " . $e->getMessage());
            throw $e;
        }
    }

    public function update($id, array $data)
    {
        return $this->updateRow($id, $data);
    }

    /**
     * Update an existing row by ID (assuming primaryKey is always a header).
     * This is an expensive operation in Google Sheets API, so use carefully.
     */
    public function updateRow($id, array $data)
    {
        $startTime = microtime(true);
        $lockKey = $this->sheetName . '_write_lock';

        try {
            return Cache::lock($lockKey, 10)->block(5, function () use ($id, $data, $startTime) {
                $result = retry(3, function () use ($id, $data) {
                    // Fetch all current values without cache to find the row index
                    $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $this->sheetName);
                    $values = $response->getValues();
                    
                    if (empty($values)) {
                        throw new \RuntimeException("Sheet '{$this->sheetName}' tidak memiliki header atau data.");
                    }

                    $headers = array_map(function ($h) { return trim((string) $h); }, $values[0]);
                    $this->assertExpectedHeaders($headers);
                    $primaryKeyClean = strtolower(trim((string) $this->primaryKey));
                    $idIndex = false;
                    foreach ($headers as $index => $header) {
                        if (strtolower(trim((string) $header)) === $primaryKeyClean) {
                            $idIndex = $index;
                            break;
                        }
                    }
                    
                    if ($idIndex === false) {
                        throw new \Exception("Header '{$this->primaryKey}' not found in sheet.");
                    }

                    $idClean = strtolower(trim((string) $id));
                    $rowIndexToUpdate = -1;
                    foreach ($values as $index => $row) {
                        if ($index > 0 && isset($row[$idIndex]) && strtolower(trim((string) $row[$idIndex])) === $idClean) {
                            $rowIndexToUpdate = $index + 1; // Google Sheets is 1-indexed
                            break;
                        }
                    }

                    if ($rowIndexToUpdate === -1) {
                        throw new \RuntimeException(
                            "Record '{$id}' tidak ditemukan di sheet '{$this->sheetName}'."
                        );
                    }

                    $rowValues = [];
                    foreach ($headers as $header) {
                        // Keep existing value if not provided in $data
                        $existingValue = $values[$rowIndexToUpdate - 1][array_search($header, $headers)] ?? '';
                        $rowValues[] = array_key_exists($header, $data) ? $data[$header] : $existingValue;
                    }

                    $range = $this->sheetName . '!A' . $rowIndexToUpdate;
                    
                    $body = new \Google_Service_Sheets_ValueRange([
                        'values' => [$rowValues]
                    ]);

                    $params = [
                        'valueInputOption' => self::WRITE_INPUT_OPTION
                    ];

                    $this->service->spreadsheets_values->update($this->spreadsheetId, $range, $body, $params);
                    return true;
                }, 1000, fn ($e) => !$e instanceof \RuntimeException);
                
                $this->clearCache();
                
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                Log::info("Google Sheets Update on {$this->sheetName}", ['duration_ms' => $duration]);
                
                return $result;
            });
        } catch (\Exception $e) {
            Log::error("Google API Error during update on {$this->sheetName}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Soft delete a row by setting Is_Active to FALSE.
     */
    public function softDelete($id)
    {
        return $this->update($id, [
            'Is_Active' => 'FALSE',
            'Updated_At' => now()->toDateTimeString()
        ]);
    }

    /**
     * Hard delete a row by ID.
     */
    public function delete($id)
    {
        return $this->hardDelete($id);
    }

    /**
     * Physically remove a row by ID, bypassing repository-level soft delete overrides.
     */
    public function hardDelete($id)
    {
        $startTime = microtime(true);
        $lockKey = $this->sheetName . '_write_lock';

        try {
            $result = Cache::lock($lockKey, 10)->block(5, function () use ($id, $startTime) {
                $delResult = retry(3, function () use ($id) {
                    $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $this->sheetName);
                    $values = $response->getValues();
                    
                    if (empty($values)) {
                        throw new \RuntimeException("Sheet '{$this->sheetName}' tidak memiliki header atau data.");
                    }

                    $headers = array_map(function ($h) { return trim((string) $h); }, $values[0]);
                    $this->assertExpectedHeaders($headers);
                    $primaryKeyClean = strtolower(trim((string) $this->primaryKey));
                    $idIndex = false;
                    foreach ($headers as $index => $header) {
                        if (strtolower(trim((string) $header)) === $primaryKeyClean) {
                            $idIndex = $index;
                            break;
                        }
                    }
                    
                    if ($idIndex === false) {
                        throw new \RuntimeException("Header primary key '{$this->primaryKey}' tidak ditemukan.");
                    }

                    $idClean = strtolower(trim((string) $id));
                    $rowIndexToDelete = -1;
                    foreach ($values as $index => $row) {
                        if ($index > 0 && isset($row[$idIndex]) && strtolower(trim((string) $row[$idIndex])) === $idClean) {
                            $rowIndexToDelete = $index; // 0-indexed for API
                            break;
                        }
                    }

                    if ($rowIndexToDelete === -1) {
                        throw new \RuntimeException(
                            "Record '{$id}' tidak ditemukan di sheet '{$this->sheetName}'."
                        );
                    }

                    $spreadsheet = $this->service->spreadsheets->get($this->spreadsheetId);
                    $sheetId = null;
                    foreach ($spreadsheet->getSheets() as $sheet) {
                        if ($sheet->getProperties()->getTitle() == $this->sheetName) {
                            $sheetId = $sheet->getProperties()->getSheetId();
                            break;
                        }
                    }

                    if ($sheetId === null) {
                        throw new \RuntimeException("Sheet '{$this->sheetName}' tidak ditemukan pada spreadsheet.");
                    }

                    $request = new \Google_Service_Sheets_Request([
                        'deleteDimension' => [
                            'range' => [
                                'sheetId' => $sheetId,
                                'dimension' => 'ROWS',
                                'startIndex' => $rowIndexToDelete,
                                'endIndex' => $rowIndexToDelete + 1
                            ]
                        ]
                    ]);

                    $batchUpdateRequest = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
                        'requests' => [$request]
                    ]);

                    $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $batchUpdateRequest);
                    return true;
                }, 1000, fn ($e) => !$e instanceof \RuntimeException);
                
                $this->clearCache();

                $duration = round((microtime(true) - $startTime) * 1000, 2);
                Log::info("Google Sheets Delete on {$this->sheetName}", ['duration_ms' => $duration]);
                
                return $delResult;
            });

            $this->clearCache();
            return $result;
        } catch (\Exception $e) {
            Log::error("Google API Error during delete on {$this->sheetName}: " . $e->getMessage());
            $this->clearCache();
            throw $e;
        }
    }

    /**
     * Truncate all data rows from the sheet (keeps header).
     * Added specifically for QA Zero-State Reset to avoid API rate limits.
     */
    public function truncateData()
    {
        $startTime = microtime(true);
        $lockKey = $this->sheetName . '_write_lock';

        try {
            $result = Cache::lock($lockKey, 10)->block(5, function () use ($startTime) {
                return retry(3, function () {
                    $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $this->sheetName);
                    $values = $response->getValues();
                    
                    if (empty($values) || count($values) <= 1) {
                        return true; // Already empty (only header or nothing)
                    }

                    $spreadsheet = $this->service->spreadsheets->get($this->spreadsheetId);
                    $sheetId = null;
                    foreach ($spreadsheet->getSheets() as $sheet) {
                        if ($sheet->getProperties()->getTitle() == $this->sheetName) {
                            $sheetId = $sheet->getProperties()->getSheetId();
                            break;
                        }
                    }

                    if ($sheetId === null) {
                        throw new \RuntimeException("Sheet '{$this->sheetName}' tidak ditemukan pada spreadsheet.");
                    }

                    $request = new \Google_Service_Sheets_Request([
                        'deleteDimension' => [
                            'range' => [
                                'sheetId' => $sheetId,
                                'dimension' => 'ROWS',
                                'startIndex' => 1,
                                'endIndex' => count($values) // Delete exactly the number of data rows
                            ]
                        ]
                    ]);

                    $batchUpdateRequest = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
                        'requests' => [$request]
                    ]);

                    $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $batchUpdateRequest);
                    
                    return true;
                }, 1000, fn ($e) => !$e instanceof \RuntimeException);
            });

            $this->clearCache();
            return $result;
        } catch (\Exception $e) {
            Log::error("Google API Error during truncateData on {$this->sheetName}: " . $e->getMessage());
            $this->clearCache();
            throw $e;
        }
    }

    protected function assertExpectedHeaders(array $headers): void
    {
        if (!empty($this->expectedHeaders) && array_values($headers) !== array_values($this->expectedHeaders)) {
            throw new \RuntimeException("Header sheet '{$this->sheetName}' tidak sesuai schema yang diharapkan.");
        }
    }
}

<?php

namespace App\Repositories\GoogleSheets;

use Google_Client;
use Google_Service_Sheets;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Exceptions\AmbiguousSheetWriteException;
use App\Exceptions\DuplicatePrimaryKeyException;
use App\Exceptions\FinancialIntegrityException;
use Throwable;

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

        $this->client->setHttpClient(new \GuzzleHttp\Client([
            'connect_timeout' => 10,
            'timeout' => 30,
        ]));

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
        $cacheKey = $this->cacheKey . '_all';
        $isHit = Cache::has($cacheKey);

        try {
            if ($isHit) {
                $data = Cache::get($cacheKey);
            } else {
                $lockKey = $cacheKey . '_read_lock';
                $data = Cache::lock($lockKey, 45)->block(10, function () use ($cacheKey) {
                    if (Cache::has($cacheKey)) {
                        return Cache::get($cacheKey);
                    }

                    $fresh = $this->fetchAllFresh();
                    Cache::put($cacheKey, $fresh, $this->cacheTtl);

                    return $fresh;
                });
            }

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
     * Read the sheet without using the repository cache. This is intentionally
     * used by allocators and write verification where persisted state is the
     * source of truth.
     */
    public function fetchAllFresh()
    {
        $values = $this->executeWithGoogleRetry(
            fn () => $this->service->spreadsheets_values->get($this->spreadsheetId, $this->sheetName)->getValues(),
            'fetchAllFresh'
        );

        if (empty($values)) {
            $this->assertExpectedHeaders([]);
            return collect([]);
        }

        $headers = array_map(fn ($header) => trim((string) $header), array_shift($values));
        $this->assertExpectedHeaders($headers);
        $data = [];

        foreach ($values as $row) {
            $item = [];
            $isEmptyRow = true;
            foreach ($headers as $index => $header) {
                $val = $row[$index] ?? null;
                $item[$header] = $val;
                if (trim((string) $val) !== '') {
                    $isEmptyRow = false;
                }
            }

            if ($isEmptyRow) {
                continue;
            }
            if ($this->skipRowsWithoutPrimaryKey
                && !empty($this->primaryKey)
                && trim((string) ($item[$this->primaryKey] ?? '')) === '') {
                continue;
            }
            $data[] = $item;
        }

        return collect($data);
    }

    /**
     * Read only the first row of a sheet.  Callers that need to decide whether
     * a write is authoritative must inspect headers even when the sheet has
     * no data rows.  A read error is deliberately propagated.
     */
    public function fetchHeadersFresh(): array
    {
        $values = $this->executeWithGoogleRetry(
            fn () => $this->service->spreadsheets_values->get($this->spreadsheetId, $this->sheetName)->getValues(),
            'fetchHeadersFresh'
        );

        $headers = array_map(fn ($header) => trim((string) $header), $values[0] ?? []);
        $this->assertExpectedHeaders($headers);
        return $headers;
    }

    public function findByIdFresh($id)
    {
        $needle = strtolower(trim((string) $id));
        return $this->fetchAllFresh()->first(function ($row) use ($needle) {
            return strtolower(trim((string) ($row[$this->primaryKey] ?? ''))) === $needle;
        });
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
            return Cache::lock($lockKey, 120)->block(15, function () use ($data, $startTime) {
                $result = $this->executeWithGoogleRetry(function () use ($data) {
                    // Read current rows inside the write lock so PK validation and append are atomic.
                    $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $this->sheetName);
                    $values = $response->getValues();
                    $headers = array_map(fn ($header) => trim((string) $header), $values[0] ?? []);

                    if (empty($headers)) {
                        throw new \RuntimeException("Header sheet '{$this->sheetName}' tidak tersedia.");
                    }
                    $this->assertExpectedHeaders($headers);

                    $this->assertDurableWriteSchema($headers);

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
                            throw new DuplicatePrimaryKeyException(
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

                    try {
                        return $this->service->spreadsheets_values->append($this->spreadsheetId, $this->sheetName, $body, $params);
                    } catch (Throwable $e) {
                        if (!$this->isAmbiguousWriteException($e)) {
                            throw $e;
                        }

                        $verified = $this->findPrimaryKeyInFreshValues($primaryKeyValue);
                        if ($verified === true) {
                            Log::warning('Google Sheets append recovered after ambiguous write', [
                                'sheet' => $this->sheetName,
                                'primary_key' => $primaryKeyValue,
                                'exception' => get_class($e),
                            ]);
                            return true;
                        }
                        if ($verified === null) {
                            throw new AmbiguousSheetWriteException(
                                "Penulisan ke sheet '{$this->sheetName}' belum dapat dikonfirmasi untuk {$primaryKeyValue}.",
                                (int) $e->getCode(),
                                $e
                            );
                        }

                        throw $e;
                    }
                }, 'append');
                
                $this->clearCache();
                
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                Log::info("Google Sheets Append on {$this->sheetName}", ['duration_ms' => $duration]);
                
                return $result;
            });
        } catch (\Exception $e) {
            Log::error("Google API Error during append on {$this->sheetName}: " . $e->getMessage(), [
                'sheet' => $this->sheetName,
                'operation' => 'append',
                'exception' => get_class($e),
                'http_status' => $this->httpStatus($e),
            ]);
            throw $e;
        }
    }

    protected function findPrimaryKeyInFreshValues(string $primaryKeyValue): ?bool
    {
        try {
            $values = $this->service->spreadsheets_values->get($this->spreadsheetId, $this->sheetName)->getValues();
            $headers = array_map(fn ($header) => trim((string) $header), $values[0] ?? []);
            $primaryKeyHeader = collect($headers)->first(fn ($header) => strcasecmp($header, $this->primaryKey) === 0);
            if ($primaryKeyHeader === null) {
                return null;
            }
            $index = array_search($primaryKeyHeader, $headers, true);
            foreach (array_slice($values, 1) as $row) {
                if (strcasecmp(trim((string) ($row[$index] ?? '')), trim($primaryKeyValue)) === 0) {
                    return true;
                }
            }
            return false;
        } catch (Throwable $e) {
            Log::warning('Google Sheets write verification failed', [
                'sheet' => $this->sheetName,
                'operation' => 'verify_append',
                'exception' => get_class($e),
                'http_status' => $this->httpStatus($e),
            ]);
            return null;
        }
    }

    protected function executeWithGoogleRetry(callable $operation, string $operationName, int $maxAttempts = 4)
    {
        $last = null;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $operation($attempt);
            } catch (Throwable $e) {
                $last = $e;
                if (!$this->isRetryableGoogleException($e) || $attempt >= $maxAttempts) {
                    throw $e;
                }

                $delayMs = $this->retryDelayMs($e, $attempt);
                Log::warning('Retrying Google Sheets operation', [
                    'request_id' => function_exists('request') && request()->hasHeader('X-Request-ID') ? request()->header('X-Request-ID') : null,
                    'sheet' => $this->sheetName,
                    'operation' => $operationName,
                    'attempt' => $attempt,
                    'next_attempt' => $attempt + 1,
                    'delay_ms' => $delayMs,
                    'http_status' => $this->httpStatus($e),
                    'exception' => get_class($e),
                ]);
                usleep($delayMs * 1000);
            }
        }
        throw $last;
    }

    protected function isRetryableGoogleException(Throwable $e): bool
    {
        $status = $this->httpStatus($e);
        if (in_array($status, [429, 500, 502, 503, 504], true)) {
            return true;
        }
        if ($status >= 400 && $status < 500) {
            return false;
        }
        if ($e instanceof \GuzzleHttp\Exception\TransferException) {
            return true;
        }
        $message = strtolower($e->getMessage());
        return str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'connection reset')
            || str_contains($message, 'temporarily unavailable');
    }

    protected function isAmbiguousWriteException(Throwable $e): bool
    {
        if ($e instanceof \GuzzleHttp\Exception\TransferException) {
            return true;
        }
        $status = $this->httpStatus($e);
        if (in_array($status, [429, 500, 502, 503, 504], true)) {
            return true;
        }
        $message = strtolower($e->getMessage());
        return str_contains($message, 'timeout')
            || str_contains($message, 'timed out')
            || str_contains($message, 'connection reset');
    }

    protected function httpStatus(Throwable $e): int
    {
        $code = (int) $e->getCode();
        if ($code >= 100 && $code <= 599) {
            return $code;
        }
        if (method_exists($e, 'getResponse') && $e->getResponse()) {
            return (int) $e->getResponse()->getStatusCode();
        }
        return 0;
    }

    protected function retryDelayMs(Throwable $e, int $attempt): int
    {
        $retryAfter = null;
        if (method_exists($e, 'getResponse') && $e->getResponse()) {
            $retryAfter = $e->getResponse()->getHeaderLine('Retry-After');
        }
        if (is_numeric($retryAfter)) {
            return min(15000, max(100, (int) $retryAfter * 1000));
        }
        $base = min(4000, 250 * (2 ** ($attempt - 1)));
        return $base + random_int(0, max(1, (int) ($base * 0.25)));
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
        $data = $this->stripPrimaryKeyFromUpdateData($data);
        $startTime = microtime(true);
        $lockKey = $this->sheetName . '_write_lock';

        try {
            return Cache::lock($lockKey, 120)->block(15, function () use ($id, $data, $startTime) {
                $result = $this->executeWithGoogleRetry(function () use ($id, $data) {
                    // Fetch all current values without cache to find the row index
                    $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $this->sheetName);
                    $values = $response->getValues();
                    
                    if (empty($values)) {
                        throw new \RuntimeException("Sheet '{$this->sheetName}' tidak memiliki header atau data.");
                    }

                    $headers = array_map(function ($h) { return trim((string) $h); }, $values[0]);
                    $this->assertExpectedHeaders($headers);
                    $this->assertDurableWriteSchema($headers);
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

                    try {
                        $this->service->spreadsheets_values->update($this->spreadsheetId, $range, $body, $params);
                    } catch (Throwable $e) {
                        if (!$this->isAmbiguousWriteException($e)) {
                            throw $e;
                        }
                        $verified = $this->findRowMatchesFresh($id, $data);
                        if ($verified === true) {
                            Log::warning('Google Sheets update recovered after ambiguous write', [
                                'sheet' => $this->sheetName,
                                'primary_key' => (string) $id,
                                'exception' => get_class($e),
                            ]);
                            return true;
                        }
                        if ($verified === null) {
                            throw new AmbiguousSheetWriteException(
                                "Update ke sheet '{$this->sheetName}' belum dapat dikonfirmasi untuk {$id}.",
                                (int) $e->getCode(),
                                $e
                            );
                        }
                        throw $e;
                    }
                    return true;
                }, 'update');
                
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

    protected function findRowMatchesFresh($id, array $expected): ?bool
    {
        try {
            $row = $this->findByIdFresh($id);
            if (!$row) {
                return false;
            }
            foreach ($expected as $key => $value) {
                if ((string) ($row[$key] ?? '') !== (string) $value) {
                    return false;
                }
            }
            return true;
        } catch (Throwable $e) {
            Log::warning('Google Sheets update verification failed', [
                'sheet' => $this->sheetName,
                'operation' => 'verify_update',
                'exception' => get_class($e),
                'http_status' => $this->httpStatus($e),
            ]);
            return null;
        }
    }

    protected function stripPrimaryKeyFromUpdateData(array $data): array
    {
        $primaryKeyClean = strtolower(trim((string) $this->primaryKey));
        if ($primaryKeyClean === '') {
            return $data;
        }

        foreach (array_keys($data) as $key) {
            if (strtolower(trim((string) $key)) === $primaryKeyClean) {
                unset($data[$key]);
            }
        }

        return $data;
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

    /**
     * Finance writes may not proceed against a sheet that cannot persist the
     * fields the application uses for correctness, audit, or identity.
     */
    protected function assertDurableWriteSchema(array $headers): void
    {
        $required = config("finance.schema.{$this->sheetName}", []);
        if (!$required) {
            return;
        }

        $missing = array_values(array_diff($required, $headers));
        if ($missing !== []) {
            throw new FinancialIntegrityException(
                "Sheet {$this->sheetName} tidak memiliki kolom wajib: "
                . implode(', ', $missing)
                . '; penulisan dihentikan untuk mencegah kehilangan data.'
            );
        }
    }
}

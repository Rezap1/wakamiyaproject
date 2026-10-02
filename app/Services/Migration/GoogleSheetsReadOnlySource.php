<?php

namespace App\Services\Migration;

use Google\Service\Sheets;
use Google_Client;
use Google_Service_Sheets;
use GuzzleHttp\Client;
use RuntimeException;

final class GoogleSheetsReadOnlySource implements SheetSource
{
    private Sheets $service;

    private string $spreadsheetId;

    public function __construct(?Google_Client $client = null, ?string $spreadsheetId = null)
    {
        $this->spreadsheetId = $spreadsheetId ?? (string) config('services.google.spreadsheet_id');
        if ($this->spreadsheetId === '') {
            throw new RuntimeException('GOOGLE_SPREADSHEET_ID is required.');
        }

        $client ??= new Google_Client;
        $client->setApplicationName('WMS MySQL M1 read-only importer');
        $client->setScopes([Google_Service_Sheets::SPREADSHEETS_READONLY]);
        $client->setAccessType('offline');

        $credentialsPath = storage_path('app/google-credentials.json');
        if (! is_file($credentialsPath)) {
            throw new RuntimeException("Google credentials not found at {$credentialsPath}.");
        }
        $client->setAuthConfig($credentialsPath);
        $client->setHttpClient(new Client(['connect_timeout' => 10, 'timeout' => 60]));

        $this->service = new Sheets($client);
    }

    public function read(string $sheet): array
    {
        return $this->readMany([$sheet])[$sheet];
    }

    /**
     * Fetch all requested ranges in one read-only API request. Besides being a
     * coherent snapshot, this stays safely below the per-user read quota.
     *
     * @param  list<string>  $sheets
     * @return array<string, array{headers: list<string>, rows: list<array{source_row: int, values: array<string, mixed>}>}>
     */
    public function readMany(array $sheets): array
    {
        $ranges = array_map(
            static fn (string $sheet): string => "'".str_replace("'", "''", $sheet)."'!A:ZZ",
            $sheets
        );
        $response = $this->service->spreadsheets_values->batchGet($this->spreadsheetId, [
            'ranges' => $ranges,
            'valueRenderOption' => 'UNFORMATTED_VALUE',
            'dateTimeRenderOption' => 'FORMATTED_STRING',
        ]);
        $snapshot = [];

        $valueRanges = $response->getValueRanges();
        foreach ($sheets as $index => $sheet) {
            $valueRange = $valueRanges[$index] ?? null;
            $values = $valueRange?->getValues() ?? [];
            $snapshot[$sheet] = $this->mapValues($values);
        }

        return $snapshot;
    }

    /** @return array{headers: list<string>, rows: list<array{source_row: int, values: array<string, mixed>}>} */
    private function mapValues(array $values): array
    {

        $headers = array_map(static fn ($header): string => trim((string) $header), array_shift($values) ?? []);
        $rows = [];

        foreach ($values as $offset => $valuesRow) {
            $hasValue = false;
            foreach ($valuesRow as $value) {
                if ($value !== null && (! is_string($value) || trim($value) !== '')) {
                    $hasValue = true;
                    break;
                }
            }
            if (! $hasValue) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = $valuesRow[$index] ?? null;
            }
            $rows[] = ['source_row' => $offset + 2, 'values' => $row];
        }

        return ['headers' => $headers, 'rows' => $rows];
    }
}

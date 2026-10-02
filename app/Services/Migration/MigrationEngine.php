<?php

namespace App\Services\Migration;

use App\Support\Migration\WmsMigrationManifest;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

final class MigrationEngine
{
    private ConnectionInterface $database;

    public function __construct(
        private readonly string $connection,
        private readonly ValueNormalizer $normalizer,
    ) {
        $this->database = DB::connection($connection);
    }

    /**
     * @param  array<string, array{headers: list<string>, rows: list<array{source_row: int, values: array<string, mixed>}>}>  $snapshot
     * @return array<string, mixed>
     */
    public function run(array $snapshot, bool $dryRun = false, bool $reconcileOnly = false): array
    {
        $report = ['tables' => [], 'orphans' => []];

        foreach (WmsMigrationManifest::contracts() as $sheet => $contract) {
            $source = $snapshot[$sheet] ?? ['headers' => [], 'rows' => []];
            $prepared = $this->prepare($contract, $source);
            $targetBefore = $this->targetRows($contract);
            $identity = $contract['preserve_duplicate_ids'] ? '_source_row_number' : $contract['primary_key'];
            $targetByIdentity = $this->keyRows($targetBefore, $identity);
            $insert = [];
            $update = [];
            $unchanged = 0;

            foreach ($prepared['rows'] as $row) {
                $key = $this->identityLookupKey($row[$identity], $identity);
                if (! isset($targetByIdentity[$key])) {
                    $insert[] = $row;
                } elseif ($this->sameBusinessRow($contract, $row, $targetByIdentity[$key])) {
                    $unchanged++;
                } else {
                    $update[] = $row;
                }
            }

            if (! $dryRun && ! $reconcileOnly && ($insert !== [] || $update !== [])) {
                $this->database->transaction(function () use ($contract, $identity, $insert, $update): void {
                    foreach (array_chunk([...$insert, ...$update], 250) as $chunk) {
                        $updateColumns = $contract['headers'];
                        $this->database->table($contract['table'])->upsert($chunk, [$identity], $updateColumns);
                    }
                });
            }

            $targetAfter = ($dryRun || $reconcileOnly) ? $targetBefore : $this->targetRows($contract);
            $reconciliation = $this->reconcile($contract, $prepared, $targetAfter);
            $report['tables'][$sheet] = [
                'source_rows' => count($source['rows']),
                'target_rows' => count($targetAfter),
                'inserted' => $reconcileOnly ? 0 : count($insert),
                'updated' => $reconcileOnly ? 0 : count($update),
                'unchanged' => $unchanged,
                'skipped' => $prepared['skipped'],
                'duplicate_ids' => $prepared['duplicate_ids'],
                'conversion_errors' => $prepared['conversion_errors'],
                'conversion_warnings' => $prepared['conversion_warnings'],
                ...$reconciliation,
            ];
        }

        $report['orphans'] = $this->auditRelationships();
        $report['finance_by_type'] = $this->financeByType($snapshot);

        return $report;
    }

    /** @return array{rows: list<array<string, mixed>>, skipped: int, duplicate_ids: array<string, list<int>>, conversion_errors: list<array<string, mixed>>, conversion_warnings: list<array<string, mixed>>} */
    private function prepare(array $contract, array $source): array
    {
        if ($source['headers'] !== $contract['headers']) {
            throw new \RuntimeException("Header drift detected for {$contract['sheet']}.");
        }

        $idRows = [];
        $displayIds = [];
        foreach ($source['rows'] as $sourceRow) {
            $id = trim((string) ($sourceRow['values'][$contract['primary_key']] ?? ''));
            if ($id !== '') {
                $lookup = mb_strtolower($id, 'UTF-8');
                $displayIds[$lookup] ??= $id;
                $idRows[$lookup][] = $sourceRow['source_row'];
            }
        }
        $duplicateRows = array_filter($idRows, static fn (array $rows): bool => count($rows) > 1);
        $duplicates = [];
        foreach ($duplicateRows as $lookup => $duplicateSourceRows) {
            $duplicates[$displayIds[$lookup]] = $duplicateSourceRows;
        }

        $rows = [];
        $errors = [];
        $warnings = [];
        $skipped = 0;

        foreach ($source['rows'] as $sourceRow) {
            $rawId = trim((string) ($sourceRow['values'][$contract['primary_key']] ?? ''));
            $idLookup = mb_strtolower($rawId, 'UTF-8');
            if ($rawId === '' || (! $contract['preserve_duplicate_ids'] && isset($duplicateRows[$idLookup]))) {
                $skipped++;

                continue;
            }

            $row = [];
            $rowErrors = [];
            foreach ($contract['headers'] as $column) {
                try {
                    $normalized = $this->normalizer->normalize(
                        $sourceRow['values'][$column] ?? null,
                        WmsMigrationManifest::columnType($column)
                    );
                    $row[$column] = $normalized['value'];
                    foreach ($normalized['warnings'] as $warning) {
                        $warnings[] = ['row' => $sourceRow['source_row'], 'column' => $column, 'issue' => $warning];
                    }
                } catch (Throwable $exception) {
                    $rowErrors[] = ['row' => $sourceRow['source_row'], 'column' => $column, 'issue' => $exception->getMessage()];
                }
            }

            if ($rowErrors !== []) {
                array_push($errors, ...$rowErrors);
                $skipped++;

                continue;
            }

            if ($contract['preserve_duplicate_ids']) {
                $row['_source_row_number'] = $sourceRow['source_row'];
            }
            $rows[] = $row;
        }

        return [
            'rows' => $rows,
            'skipped' => $skipped,
            'duplicate_ids' => $duplicates,
            'conversion_errors' => $errors,
            'conversion_warnings' => $warnings,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function targetRows(array $contract): array
    {
        return array_map(static fn ($row): array => (array) $row, $this->database->table($contract['table'])->get()->all());
    }

    /** @param list<array<string, mixed>> $rows */
    private function keyRows(array $rows, string $key): array
    {
        $keyed = [];
        foreach ($rows as $row) {
            $keyed[$this->identityLookupKey($row[$key] ?? '', $key)] = $row;
        }

        return $keyed;
    }

    private function sameBusinessRow(array $contract, array $source, array $target): bool
    {
        foreach ($contract['headers'] as $column) {
            $sourceValue = $source[$column] ?? null;
            $targetValue = $target[$column] ?? null;

            try {
                $targetValue = $this->normalizer->normalize($targetValue, WmsMigrationManifest::columnType($column))['value'];
            } catch (Throwable) {
                return false;
            }

            if ($sourceValue !== $targetValue) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function reconcile(array $contract, array $prepared, array $target): array
    {
        $identity = $contract['preserve_duplicate_ids'] ? '_source_row_number' : $contract['primary_key'];
        $sourceKeys = [];
        foreach ($prepared['rows'] as $row) {
            $sourceKeys[$this->identityLookupKey($row[$identity], $identity)] = (string) $row[$identity];
        }
        $targetKeys = [];
        foreach ($target as $row) {
            $targetKeys[$this->identityLookupKey($row[$identity] ?? '', $identity)] = (string) ($row[$identity] ?? '');
        }
        $matching = array_intersect_key($sourceKeys, $targetKeys);
        $missing = array_values(array_diff_key($sourceKeys, $targetKeys));
        $extra = array_values(array_diff_key($targetKeys, $sourceKeys));
        $numeric = [];

        foreach ($contract['headers'] as $column) {
            if (WmsMigrationManifest::columnType($column) !== 'decimal') {
                continue;
            }
            $sourceSum = $this->sumDecimal($prepared['rows'], $column);
            $targetSum = $this->sumDecimal($target, $column);
            $numeric[$column] = [
                'source' => $sourceSum,
                'target' => $targetSum,
                'difference' => $this->subtractDecimal($targetSum, $sourceSum),
            ];
        }

        return [
            'matching_ids' => count($matching),
            'missing_ids' => $missing,
            'extra_ids' => $extra,
            'numeric_reconciliation' => $numeric,
        ];
    }

    private function identityLookupKey(mixed $value, string $identity): string
    {
        $value = (string) $value;

        return $identity === '_source_row_number' ? $value : mb_strtolower($value, 'UTF-8');
    }

    /** @return list<array<string, mixed>> */
    private function auditRelationships(): array
    {
        $orphans = [];
        $contracts = WmsMigrationManifest::contracts();

        foreach (WmsMigrationManifest::relationships() as $relationship) {
            $parentValues = array_fill_keys(array_map(
                static fn ($value): string => (string) $value,
                $this->database->table($relationship['parent'])->whereNotNull($relationship['parent_key'])->pluck($relationship['parent_key'])->all()
            ), true);
            $childKey = $contracts[$relationship['child']]['primary_key'];
            $missing = [];
            $rows = $this->database->table($relationship['child'])
                ->whereNotNull($relationship['column'])
                ->where($relationship['column'], '<>', '')
                ->get([$childKey, $relationship['column']]);

            foreach ($rows as $row) {
                $reference = (string) $row->{$relationship['column']};
                if (! isset($parentValues[$reference])) {
                    $missing[] = ['child_id' => (string) $row->{$childKey}, 'missing_reference' => $reference];
                }
            }

            if ($missing !== []) {
                $orphans[] = [...$relationship, 'count' => count($missing), 'records' => $missing];
            }
        }

        return $orphans;
    }

    /** @return array<string, array{source: string, target: string, difference: string}> */
    private function financeByType(array $snapshot): array
    {
        $sourceGroups = [];
        foreach ($snapshot['FINANCE_TRANSACTION']['rows'] as $sourceRow) {
            $type = strtoupper(trim((string) ($sourceRow['values']['Type'] ?? '')));
            $amount = $this->normalizer->normalize($sourceRow['values']['Amount'] ?? null, 'decimal')['value'];
            if ($type === '' || $amount === null) {
                continue;
            }
            $sourceGroups[$type] = $this->addDecimal($sourceGroups[$type] ?? '0.0000', $amount);
        }

        $targetGroups = [];
        foreach ($this->database->table('FINANCE_TRANSACTION')->get(['Type', 'Amount']) as $targetRow) {
            $type = strtoupper(trim((string) $targetRow->Type));
            $amount = $this->normalizer->normalize($targetRow->Amount, 'decimal')['value'];
            if ($type === '' || $amount === null) {
                continue;
            }
            $targetGroups[$type] = $this->addDecimal($targetGroups[$type] ?? '0.0000', $amount);
        }

        $result = [];
        foreach (array_unique([...array_keys($sourceGroups), ...array_keys($targetGroups)]) as $type) {
            $source = $sourceGroups[$type] ?? '0.0000';
            $target = $targetGroups[$type] ?? '0.0000';
            $result[$type] = [
                'source' => $source,
                'target' => $target,
                'difference' => $this->subtractDecimal($target, $source),
            ];
        }
        ksort($result);

        return $result;
    }

    /** @param list<array<string, mixed>> $rows */
    private function sumDecimal(array $rows, string $column): string
    {
        $sum = '0.0000';
        foreach ($rows as $row) {
            if (($row[$column] ?? null) === null) {
                continue;
            }
            $value = $this->normalizer->normalize($row[$column], 'decimal')['value'];
            $sum = $this->addDecimal($sum, $value);
        }

        return $sum;
    }

    private function subtractDecimal(string $left, string $right): string
    {
        return $this->addDecimal($left, str_starts_with($right, '-') ? substr($right, 1) : '-'.$right);
    }

    private function addDecimal(string $left, string $right): string
    {
        $leftScaled = $this->toScaledInteger($left);
        $rightScaled = $this->toScaledInteger($right);
        $sum = $this->addSignedIntegers($leftScaled, $rightScaled);
        $sign = str_starts_with($sum, '-') ? '-' : '';
        $digits = str_pad(ltrim($sum, '-'), 5, '0', STR_PAD_LEFT);

        return $sign.substr($digits, 0, -4).'.'.substr($digits, -4);
    }

    private function addSignedIntegers(string $left, string $right): string
    {
        $leftNegative = str_starts_with($left, '-');
        $rightNegative = str_starts_with($right, '-');
        $left = ltrim($left, '-');
        $right = ltrim($right, '-');

        if ($leftNegative === $rightNegative) {
            $result = $this->addUnsignedIntegers($left, $right);

            return $leftNegative && $result !== '0' ? '-'.$result : $result;
        }

        $comparison = strlen($left) <=> strlen($right);
        if ($comparison === 0) {
            $comparison = strcmp($left, $right);
        }
        if ($comparison === 0) {
            return '0';
        }

        $leftIsLarger = $comparison > 0;
        $result = $this->subtractUnsignedIntegers($leftIsLarger ? $left : $right, $leftIsLarger ? $right : $left);
        $negative = $leftIsLarger ? $leftNegative : $rightNegative;

        return $negative ? '-'.$result : $result;
    }

    private function addUnsignedIntegers(string $left, string $right): string
    {
        $carry = 0;
        $result = '';
        for ($leftIndex = strlen($left) - 1, $rightIndex = strlen($right) - 1; $leftIndex >= 0 || $rightIndex >= 0 || $carry > 0; $leftIndex--, $rightIndex--) {
            $sum = ($leftIndex >= 0 ? (int) $left[$leftIndex] : 0) + ($rightIndex >= 0 ? (int) $right[$rightIndex] : 0) + $carry;
            $result = ($sum % 10).$result;
            $carry = intdiv($sum, 10);
        }

        return ltrim($result, '0') ?: '0';
    }

    private function subtractUnsignedIntegers(string $larger, string $smaller): string
    {
        $borrow = 0;
        $result = '';
        for ($largerIndex = strlen($larger) - 1, $smallerIndex = strlen($smaller) - 1; $largerIndex >= 0; $largerIndex--, $smallerIndex--) {
            $digit = (int) $larger[$largerIndex] - $borrow - ($smallerIndex >= 0 ? (int) $smaller[$smallerIndex] : 0);
            if ($digit < 0) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }
            $result = $digit.$result;
        }

        return ltrim($result, '0') ?: '0';
    }

    private function toScaledInteger(string $decimal): string
    {
        $negative = str_starts_with($decimal, '-');
        [$whole, $fraction] = explode('.', ltrim($decimal, '-'));
        $scaled = ltrim($whole.str_pad($fraction, 4, '0'), '0');
        $scaled = $scaled === '' ? '0' : $scaled;

        return $negative && $scaled !== '0' ? '-'.$scaled : $scaled;
    }
}

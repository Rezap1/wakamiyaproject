<?php

namespace App\Repositories\MySql;

use App\Interfaces\AgendaRepositoryInterface;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AgendaRepository implements AgendaRepositoryInterface
{
    private const TABLE = 'WMS_AGENDA';

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->baseQuery();

        if (($search = trim((string) ($filters['search'] ?? ''))) !== '') {
            $query->where(function ($nested) use ($search): void {
                $like = '%'.$search.'%';
                $nested->where('a.Agenda_ID', 'like', $like)
                    ->orWhere('a.Related_Parties', 'like', $like)
                    ->orWhere('a.Purpose', 'like', $like)
                    ->orWhere('a.Activity_Result', 'like', $like)
                    ->orWhere('a.Follow_Up', 'like', $like)
                    ->orWhere('pic.Full_Name', 'like', $like);
            });
        }

        foreach ([
            'Agenda_Type' => 'type',
            'PIC_User_ID' => 'pic',
            'Status' => 'agenda_status',
            'Follow_Up_Status' => 'follow_up_status',
        ] as $column => $filter) {
            if (($value = trim((string) ($filters[$filter] ?? ''))) !== '') {
                $query->where('a.'.$column, $value);
            }
        }

        if (($from = trim((string) ($filters['date_from'] ?? ''))) !== '') {
            $query->whereDate('a.Agenda_Date', '>=', $from);
        }
        if (($to = trim((string) ($filters['date_to'] ?? ''))) !== '') {
            $query->whereDate('a.Agenda_Date', '<=', $to);
        }

        return $query->orderByDesc('a.Agenda_Date')
            ->orderByDesc('a.Start_Time')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(string $id): ?array
    {
        $row = $this->baseQuery()->where('a.Agenda_ID', $id)->first();

        return $row ? (array) $row : null;
    }

    public function create(array $data): array
    {
        $this->connection()->table(self::TABLE)->insert($data);

        return $this->find((string) $data['Agenda_ID']) ?? $data;
    }

    public function update(string $id, array $data): bool
    {
        return $this->connection()->table(self::TABLE)
            ->where('Agenda_ID', $id)
            ->whereNull('Deleted_At')
            ->update($data) > 0;
    }

    public function delete(string $id, string $actorId): bool
    {
        return $this->connection()->table(self::TABLE)
            ->where('Agenda_ID', $id)
            ->whereNull('Deleted_At')
            ->update([
                'Deleted_At' => now()->toDateTimeString(),
                'Updated_At' => now()->toDateTimeString(),
                'Updated_By' => $actorId,
            ]) > 0;
    }

    public function generateNewId(string $prefix = 'AGD', int $padding = 6): string
    {
        $quotedPrefix = preg_quote($prefix, '/');
        $next = $this->allocateNextSequence(
            strtolower(self::TABLE.':Agenda_ID:'.$prefix),
            static fn (string $value): ?int => preg_match('/^'.$quotedPrefix.'(\d+)$/i', $value, $matches)
                ? (int) $matches[1]
                : null,
        );

        return $prefix.str_pad((string) $next, $padding, '0', STR_PAD_LEFT);
    }

    public function eligiblePics(): Collection
    {
        return $this->eligiblePicQuery()
            ->select(['u.User_ID', 'u.Full_Name', 'u.Username', 'u.Role_ID', 'r.Role_Name'])
            ->orderBy('u.Full_Name')
            ->get()
            ->map(fn (object $row): array => (array) $row);
    }

    public function isEligiblePic(string $userId): bool
    {
        return $this->eligiblePicQuery()->where('u.User_ID', $userId)->exists();
    }

    private function baseQuery()
    {
        return $this->connection()->table(self::TABLE.' as a')
            ->leftJoin('MASTER_USER as pic', 'pic.User_ID', '=', 'a.PIC_User_ID')
            ->leftJoin('MASTER_USER as creator', 'creator.User_ID', '=', 'a.Created_By')
            ->whereNull('a.Deleted_At')
            ->select([
                'a.*',
                'pic.Full_Name as PIC_Name',
                'creator.Full_Name as Creator_Name',
            ]);
    }

    private function eligiblePicQuery()
    {
        return $this->connection()->table('MASTER_USER as u')
            ->leftJoin('MASTER_ROLE as r', 'r.Role_ID', '=', 'u.Role_ID')
            ->where(function ($query): void {
                $query->whereNull('u.Is_Active')
                    ->orWhereNotIn('u.Is_Active', ['FALSE', '0', 'INACTIVE', 'DISABLED']);
            })
            ->where(function ($query): void {
                $query->where(function ($role): void {
                    $role->whereNotNull('r.Role_ID')
                        ->whereRaw('UPPER(r.Role_Name) <> ?', ['STUDENT'])
                        ->where(function ($active): void {
                            $active->whereNull('r.Is_Active')
                                ->orWhereNotIn('r.Is_Active', ['FALSE', '0', 'INACTIVE', 'DISABLED']);
                        });
                })->orWhereRaw("UPPER(COALESCE(u.Role_ID, '')) = ?", ['MASTER']);
            });
    }

    /** @param Closure(string): ?int $extractNumber */
    private function allocateNextSequence(string $sequenceKey, Closure $extractNumber): int
    {
        return $this->connection()->transaction(function () use ($sequenceKey, $extractNumber): int {
            $sequenceTable = (string) config('wms_runtime.sequence_table', 'WMS_ID_SEQUENCE');
            $this->connection()->table($sequenceTable)->insertOrIgnore([
                'Sequence_Key' => $sequenceKey,
                'Last_Value' => 0,
                'Updated_At' => now()->toDateTimeString(),
            ]);
            $sequence = $this->connection()->table($sequenceTable)
                ->where('Sequence_Key', $sequenceKey)
                ->lockForUpdate()
                ->first();
            if ($sequence === null) {
                throw new RuntimeException("Sequence '{$sequenceKey}' tidak dapat dikunci.");
            }

            $current = (int) $sequence->Last_Value;
            if ($current === 0) {
                $maximum = 0;
                $this->connection()->table(self::TABLE)
                    ->whereNotNull('Agenda_ID')
                    ->orderBy('Agenda_ID')
                    ->select('Agenda_ID')
                    ->chunk(500, function (Collection $rows) use (&$maximum, $extractNumber): void {
                        foreach ($rows as $row) {
                            $number = $extractNumber(trim((string) $row->Agenda_ID));
                            if ($number !== null) {
                                $maximum = max($maximum, $number);
                            }
                        }
                    });
                $current = $maximum;
            }

            $next = $current + 1;
            $this->connection()->table($sequenceTable)
                ->where('Sequence_Key', $sequenceKey)
                ->update(['Last_Value' => $next, 'Updated_At' => now()->toDateTimeString()]);

            return $next;
        }, 5);
    }

    private function connection(): ConnectionInterface
    {
        $connection = DB::connection((string) config('wms_runtime.connection', config('database.default')));
        if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            $expected = (string) config('wms_runtime.database', 'wms_mysql');
            if ((string) $connection->getDatabaseName() !== $expected) {
                throw new RuntimeException("WMS runtime database harus EXACTLY '{$expected}'.");
            }
        } elseif (! app()->environment('testing')) {
            throw new RuntimeException('Driver database agenda tidak diizinkan untuk runtime WMS.');
        }

        return $connection;
    }
}

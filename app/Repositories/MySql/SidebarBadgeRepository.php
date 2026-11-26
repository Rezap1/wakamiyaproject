<?php

namespace App\Repositories\MySql;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only aggregate queries used by the shared sidebar.
 *
 * Keeping these counts here prevents the layout from hydrating complete WMS
 * tables on every page request. Every method fails closed when an optional
 * runtime table/column is unavailable.
 */
class SidebarBadgeRepository
{
    private const PENDING_ATTENDANCE_STATUSES = [
        '',
        'PENDING',
        'WAITING',
        'WAITING_APPROVAL',
        'WAITING_REVIEW',
        'SUBMITTED',
    ];

    /** @var array<string,bool> */
    private array $tableAvailability = [];

    /** @var array<string,bool> */
    private array $columnAvailability = [];

    public function pendingApprovals(string $role): int
    {
        return $this->safely(function () use ($role): int {
            $status = $this->wrap('Status');
            $query = $this->connection()->table('MASTER_APPROVAL')
                ->whereRaw("UPPER(TRIM(COALESCE({$status}, ?))) = ?", ['', 'WAITING APPROVAL']);

            $role = strtoupper(trim($role));
            if ($role !== 'ADMINISTRATOR') {
                // ApprovalController currently authorizes scoped roles from
                // Current_Approver. Do not infer Current_Role as an alias here,
                // otherwise the badge could expose records absent from its page.
                if (! $this->hasColumn('MASTER_APPROVAL', 'Current_Approver')) {
                    return 0;
                }
                $approver = $this->wrap('Current_Approver');
                $query->whereRaw("UPPER(TRIM(COALESCE({$approver}, ?))) = ?", ['', $role]);
            }

            return $query->count();
        });
    }

    public function paymentsWaitingVerification(): int
    {
        return $this->safely(function (): int {
            $status = $this->wrap('Status');

            return $this->connection()->table('FINANCE_PAYMENT')
                ->whereRaw("UPPER(TRIM(COALESCE({$status}, ?))) = ?", ['', 'WAITING VERIFICATION'])
                ->count();
        });
    }

    public function teacherAttendanceRequests(string $userId): int
    {
        return $this->safely(function () use ($userId): int {
            $teacherIds = $this->connection()->table('MASTER_TEACHER')
                ->where('User_ID', trim($userId))
                ->where(fn (Builder $query) => $this->whereActive($query))
                ->pluck('Teacher_ID')
                ->map(fn ($id) => trim((string) $id))
                ->filter()
                ->unique()
                ->values();

            // Ambiguous or missing identities must never broaden teacher scope.
            if ($teacherIds->count() !== 1) {
                return 0;
            }
            $teacherId = $teacherIds->first();

            $requests = $this->connection()->table('MASTER_ATTENDANCE_REQUEST as request')
                ->join('MASTER_STUDENT as student', 'student.Student_ID', '=', 'request.Student_ID')
                ->where(fn (Builder $query) => $this->whereActive($query, 'student.Is_Active'));
            $this->wherePendingAttendance($requests, 'request.Status');

            $requests->where(function (Builder $scope) use ($teacherId): void {
                $scope->where(function (Builder $classBased) use ($teacherId): void {
                    $classBased->where(function (Builder $type): void {
                        $attendanceType = $this->wrap('request.Attendance_Type');
                        $type->whereIn(DB::raw("UPPER(TRIM(COALESCE({$attendanceType}, '')))"), ['CLASS_QR', 'CLASS_MANUAL'])
                            ->orWhere(function (Builder $legacy): void {
                                $attendanceType = $this->wrap('request.Attendance_Type');
                                $scheduleId = $this->wrap('request.Schedule_ID');
                                $legacy->whereRaw("TRIM(COALESCE({$attendanceType}, ?)) = ?", ['', ''])
                                    ->whereRaw("TRIM(COALESCE({$scheduleId}, ?)) = ?", ['', '']);
                            });
                    });
                    $classBased->where(function (Builder $class): void {
                        $class->whereNull('request.Class_ID')
                            ->orWhere('request.Class_ID', '')
                            ->orWhereColumn('request.Class_ID', 'student.Class_ID');
                    });
                    $classBased->whereExists(function (Builder $classes) use ($teacherId): void {
                        $classes->selectRaw('1')
                            ->from('MASTER_CLASS as class')
                            ->whereColumn('class.Class_ID', 'student.Class_ID')
                            ->where(fn (Builder $query) => $this->whereActive($query, 'class.Is_Active'))
                            ->where(function (Builder $authority) use ($teacherId): void {
                                $authority->where('class.Homeroom_Teacher_ID', $teacherId)
                                    ->orWhereExists(function (Builder $schedules) use ($teacherId): void {
                                        $schedules->selectRaw('1')
                                            ->from('MASTER_SCHEDULE as class_schedule')
                                            ->whereColumn('class_schedule.Class_ID', 'class.Class_ID')
                                            ->where('class_schedule.Teacher_ID', $teacherId)
                                            ->where(fn (Builder $query) => $this->whereActive($query, 'class_schedule.Is_Active'));
                                    });
                            });
                    });
                })->orWhere(function (Builder $scheduled) use ($teacherId): void {
                    $scheduled->where(function (Builder $type): void {
                        $attendanceType = $this->wrap('request.Attendance_Type');
                        $type->whereRaw("UPPER(TRIM(COALESCE({$attendanceType}, ?))) = ?", ['', 'SCHEDULE'])
                            ->orWhere(function (Builder $legacy): void {
                                $attendanceType = $this->wrap('request.Attendance_Type');
                                $scheduleId = $this->wrap('request.Schedule_ID');
                                $legacy->whereRaw("TRIM(COALESCE({$attendanceType}, ?)) = ?", ['', ''])
                                    ->whereRaw("TRIM(COALESCE({$scheduleId}, ?)) <> ?", ['', '']);
                            });
                    });
                    $scheduled->whereExists(function (Builder $schedules) use ($teacherId): void {
                        $schedules->selectRaw('1')
                            ->from('MASTER_SCHEDULE as request_schedule')
                            ->whereColumn('request_schedule.Schedule_ID', 'request.Schedule_ID')
                            ->whereColumn('request_schedule.Class_ID', 'student.Class_ID')
                            ->where('request_schedule.Teacher_ID', $teacherId)
                            ->where(fn (Builder $query) => $this->whereActive($query, 'request_schedule.Is_Active'));
                    });
                });
            });

            return $requests->count('request.Request_ID');
        });
    }

    public function activeIncompleteRegularQuizzes(string $userId, CarbonImmutable $now): int
    {
        return $this->safely(function () use ($userId, $now): int {
            $students = $this->connection()->table('MASTER_STUDENT')
                ->where('User_ID', trim($userId))
                ->where(fn (Builder $query) => $this->whereActive($query))
                ->get(['Student_ID', 'Class_ID']);

            // Fail closed for broken identity mappings.
            if ($students->count() !== 1) {
                return 0;
            }
            $student = $students->first();
            $studentId = trim((string) $student->Student_ID);
            $classId = trim((string) $student->Class_ID);
            if ($studentId === '' || $classId === '') {
                return 0;
            }

            $quizStatus = $this->wrap('quiz.Status');
            $query = $this->connection()->table('QUIZ as quiz')
                ->whereRaw("UPPER(TRIM(COALESCE({$quizStatus}, ?))) = ?", ['', 'PUBLISHED'])
                ->where('quiz.Start_At', '<=', $now->toDateTimeString())
                ->where('quiz.End_At', '>', $now->toDateTimeString())
                ->whereNotExists(function (Builder $result) use ($studentId): void {
                    $result->selectRaw('1')
                        ->from('QUIZ_RESULT as result')
                        ->whereColumn('result.Quiz_ID', 'quiz.Quiz_ID')
                        ->where('result.Student_ID', $studentId);
                });

            if ($this->hasTable('QUIZ_AUDIENCE_CLASS')) {
                $query->where(function (Builder $audience) use ($classId): void {
                    $audience->whereExists(function (Builder $assigned) use ($classId): void {
                        $assigned->selectRaw('1')
                            ->from('QUIZ_AUDIENCE_CLASS as audience_match')
                            ->whereColumn('audience_match.Quiz_ID', 'quiz.Quiz_ID')
                            ->where('audience_match.Class_ID', $classId);
                    })->orWhere(function (Builder $legacy) use ($classId): void {
                        $legacy->whereNotExists(function (Builder $assigned): void {
                            $assigned->selectRaw('1')
                                ->from('QUIZ_AUDIENCE_CLASS as any_audience')
                                ->whereColumn('any_audience.Quiz_ID', 'quiz.Quiz_ID');
                        })->where('quiz.Class_ID', $classId);
                    });
                });
            } else {
                $query->where('quiz.Class_ID', $classId);
            }

            return $query->count('quiz.Quiz_ID');
        });
    }

    private function wherePendingAttendance(Builder $query, string $column): void
    {
        $wrapped = $query->getGrammar()->wrap($column);
        $query->whereIn(
            DB::raw("UPPER(REPLACE(REPLACE(TRIM(COALESCE({$wrapped}, '')), ' ', '_'), '-', '_'))"),
            self::PENDING_ATTENDANCE_STATUSES
        );
    }

    private function whereActive(Builder $query, string $column = 'Is_Active'): void
    {
        $wrapped = $query->getGrammar()->wrap($column);
        $query->whereNotIn(DB::raw("UPPER(TRIM(COALESCE({$wrapped}, 'TRUE')))"), [
            'FALSE',
            '0',
            'INACTIVE',
            'NONACTIVE',
            'NON_ACTIVE',
            'DISABLED',
            'ARCHIVED',
            'DROPPED',
            'CANCELLED',
        ]);
    }

    private function connection(): ConnectionInterface
    {
        return DB::connection((string) config('wms_runtime.connection', config('database.default')));
    }

    private function wrap(string $column): string
    {
        return $this->connection()->getQueryGrammar()->wrap($column);
    }

    private function hasTable(string $table): bool
    {
        return $this->tableAvailability[$table]
            ??= Schema::connection($this->connection()->getName())->hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;

        return $this->columnAvailability[$key]
            ??= Schema::connection($this->connection()->getName())->hasColumn($table, $column);
    }

    private function safely(callable $query): int
    {
        try {
            return max(0, (int) $query());
        } catch (\Throwable) {
            // The sidebar is shared infrastructure. A missing optional module
            // must hide its badge rather than take down unrelated pages.
            return 0;
        }
    }
}

<?php

namespace Tests\Unit;

use App\Repositories\MySql\SidebarBadgeRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SidebarBadgeRepositoryTest extends TestCase
{
    private SidebarBadgeRepository $badges;

    protected function setUp(): void
    {
        parent::setUp();
        $this->badges = new SidebarBadgeRepository;
    }

    public function test_approval_and_payment_counts_follow_pending_action_lifecycle(): void
    {
        Schema::create('MASTER_APPROVAL', function (Blueprint $table): void {
            $table->string('Approval_ID')->primary();
            $table->string('Current_Approver')->nullable();
            $table->string('Status')->nullable();
        });
        Schema::create('FINANCE_PAYMENT', function (Blueprint $table): void {
            $table->string('Payment_ID')->primary();
            $table->string('Status')->nullable();
        });

        DB::table('MASTER_APPROVAL')->insert([
            ['Approval_ID' => 'APP-DIR', 'Current_Approver' => 'DIRECTOR', 'Status' => 'Waiting Approval'],
            ['Approval_ID' => 'APP-FIN', 'Current_Approver' => 'FINANCE', 'Status' => 'Waiting Approval'],
            ['Approval_ID' => 'APP-DONE', 'Current_Approver' => '', 'Status' => 'Approved'],
        ]);
        DB::table('FINANCE_PAYMENT')->insert([
            ['Payment_ID' => 'PAY-1', 'Status' => 'Waiting Verification'],
            ['Payment_ID' => 'PAY-2', 'Status' => 'waiting verification'],
            ['Payment_ID' => 'PAY-3', 'Status' => 'Verified'],
        ]);

        $this->assertSame(2, $this->badges->pendingApprovals('ADMINISTRATOR'));
        $this->assertSame(1, $this->badges->pendingApprovals('DIRECTOR'));
        $this->assertSame(2, $this->badges->paymentsWaitingVerification());

        DB::table('MASTER_APPROVAL')->where('Approval_ID', 'APP-DIR')->update(['Status' => 'Approved']);
        DB::table('FINANCE_PAYMENT')->where('Payment_ID', 'PAY-1')->update(['Status' => 'Verified']);

        $this->assertSame(0, $this->badges->pendingApprovals('DIRECTOR'));
        $this->assertSame(1, $this->badges->pendingApprovals('ADMINISTRATOR'));
        $this->assertSame(1, $this->badges->paymentsWaitingVerification());
    }

    public function test_scoped_teacher_count_excludes_other_classes_and_drops_only_after_decision(): void
    {
        $this->createAttendanceTables();
        DB::table('MASTER_TEACHER')->insert([
            ['Teacher_ID' => 'T-1', 'User_ID' => 'U-1', 'Is_Active' => 'TRUE'],
            ['Teacher_ID' => 'T-2', 'User_ID' => 'U-2', 'Is_Active' => 'TRUE'],
        ]);
        DB::table('MASTER_CLASS')->insert([
            ['Class_ID' => 'C-1', 'Homeroom_Teacher_ID' => 'T-1', 'Is_Active' => 'TRUE'],
            ['Class_ID' => 'C-2', 'Homeroom_Teacher_ID' => 'T-2', 'Is_Active' => 'TRUE'],
        ]);
        DB::table('MASTER_SCHEDULE')->insert([
            ['Schedule_ID' => 'SCH-1', 'Class_ID' => 'C-1', 'Teacher_ID' => 'T-1', 'Is_Active' => 'TRUE'],
            ['Schedule_ID' => 'SCH-2', 'Class_ID' => 'C-2', 'Teacher_ID' => 'T-2', 'Is_Active' => 'TRUE'],
        ]);
        DB::table('MASTER_STUDENT')->insert([
            ['Student_ID' => 'S-1', 'User_ID' => 'US-1', 'Class_ID' => 'C-1', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'S-2', 'User_ID' => 'US-2', 'Class_ID' => 'C-2', 'Is_Active' => 'TRUE'],
        ]);
        DB::table('MASTER_ATTENDANCE_REQUEST')->insert([
            ['Request_ID' => 'REQ-CLASS-1', 'Student_ID' => 'S-1', 'Class_ID' => 'C-1', 'Schedule_ID' => '', 'Attendance_Type' => 'CLASS_QR', 'Status' => 'PENDING'],
            ['Request_ID' => 'REQ-SCHEDULE-1', 'Student_ID' => 'S-1', 'Class_ID' => 'C-1', 'Schedule_ID' => 'SCH-1', 'Attendance_Type' => 'SCHEDULE', 'Status' => 'WAITING_REVIEW'],
            ['Request_ID' => 'REQ-CLASS-2', 'Student_ID' => 'S-2', 'Class_ID' => 'C-2', 'Schedule_ID' => '', 'Attendance_Type' => 'CLASS_MANUAL', 'Status' => 'PENDING'],
            ['Request_ID' => 'REQ-DONE', 'Student_ID' => 'S-1', 'Class_ID' => 'C-1', 'Schedule_ID' => '', 'Attendance_Type' => 'CLASS_QR', 'Status' => 'APPROVED'],
            ['Request_ID' => 'REQ-FORGED', 'Student_ID' => 'S-2', 'Class_ID' => 'C-1', 'Schedule_ID' => '', 'Attendance_Type' => 'CLASS_QR', 'Status' => 'PENDING'],
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->assertSame(2, $this->badges->teacherAttendanceRequests('U-1'));
        $teacherQueries = DB::getQueryLog();
        $this->assertCount(2, $teacherQueries, 'Teacher badge should resolve identity once and aggregate once.');
        $this->assertSame(1, collect($teacherQueries)->filter(
            fn (array $query) => str_contains(strtolower($query['query']), 'count(')
        )->count());
        $this->assertFalse(collect($teacherQueries)->contains(
            fn (array $query) => str_contains(strtolower($query['query']), 'select *')
        ));
        $this->assertSame(1, $this->badges->teacherAttendanceRequests('U-2'));

        // Merely opening the page does not mutate pending state.
        $this->assertSame(2, $this->badges->teacherAttendanceRequests('U-1'));
        DB::table('MASTER_ATTENDANCE_REQUEST')->where('Request_ID', 'REQ-CLASS-1')->update(['Status' => 'REJECTED']);
        $this->assertSame(1, $this->badges->teacherAttendanceRequests('U-1'));
    }

    public function test_regular_quiz_count_is_student_scoped_and_never_reads_practice_quizzes(): void
    {
        $this->createQuizTables();
        DB::table('MASTER_STUDENT')->insert([
            ['Student_ID' => 'S-1', 'User_ID' => 'U-S1', 'Class_ID' => 'C-1', 'Is_Active' => 'TRUE'],
            ['Student_ID' => 'S-2', 'User_ID' => 'U-S2', 'Class_ID' => 'C-2', 'Is_Active' => 'TRUE'],
        ]);
        DB::table('QUIZ')->insert([
            ['Quiz_ID' => 'Q-LEGACY', 'Class_ID' => 'C-1', 'Start_At' => '2026-11-26 08:00:00', 'End_At' => '2026-11-26 12:00:00', 'Status' => 'PUBLISHED'],
            ['Quiz_ID' => 'Q-SHARED', 'Class_ID' => 'C-X', 'Start_At' => '2026-11-26 08:00:00', 'End_At' => '2026-11-26 12:00:00', 'Status' => 'PUBLISHED'],
            ['Quiz_ID' => 'Q-OTHER', 'Class_ID' => 'C-2', 'Start_At' => '2026-11-26 08:00:00', 'End_At' => '2026-11-26 12:00:00', 'Status' => 'PUBLISHED'],
            ['Quiz_ID' => 'Q-FUTURE', 'Class_ID' => 'C-1', 'Start_At' => '2026-11-27 08:00:00', 'End_At' => '2026-11-27 12:00:00', 'Status' => 'PUBLISHED'],
            ['Quiz_ID' => 'Q-DRAFT', 'Class_ID' => 'C-1', 'Start_At' => '2026-11-26 08:00:00', 'End_At' => '2026-11-26 12:00:00', 'Status' => 'DRAFT'],
        ]);
        DB::table('QUIZ_AUDIENCE_CLASS')->insert([
            ['Quiz_ID' => 'Q-SHARED', 'Class_ID' => 'C-1'],
            ['Quiz_ID' => 'Q-SHARED', 'Class_ID' => 'C-2'],
        ]);
        DB::table('QUIZ_RESULT')->insert([
            ['Result_ID' => 'R-1', 'Quiz_ID' => 'Q-LEGACY', 'Student_ID' => 'S-1'],
        ]);
        DB::table('PRACTICE_QUIZ')->insert([
            ['Practice_Quiz_ID' => 'P-FOREVER', 'Status' => 'PUBLISHED'],
        ]);

        $now = CarbonImmutable::parse('2026-11-26 10:00:00', 'Asia/Jakarta');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->assertSame(1, $this->badges->activeIncompleteRegularQuizzes('U-S1', $now));
        $studentQueries = DB::getQueryLog();
        $this->assertLessThanOrEqual(3, count($studentQueries), 'Student badge should use two data queries plus at most one audience schema check.');
        $this->assertSame(1, collect($studentQueries)->filter(
            fn (array $query) => str_contains(strtolower($query['query']), 'count(')
        )->count());
        $this->assertFalse(collect($studentQueries)->contains(
            fn (array $query) => str_contains(strtolower($query['query']), 'select *')
        ));
        $this->assertSame(2, $this->badges->activeIncompleteRegularQuizzes('U-S2', $now));

        DB::table('QUIZ_RESULT')->insert([
            'Result_ID' => 'R-2', 'Quiz_ID' => 'Q-SHARED', 'Student_ID' => 'S-1',
        ]);
        $this->assertSame(0, $this->badges->activeIncompleteRegularQuizzes('U-S1', $now));
    }

    public function test_aggregate_query_budget_does_not_hydrate_business_collections(): void
    {
        Schema::create('FINANCE_PAYMENT', function (Blueprint $table): void {
            $table->string('Payment_ID')->primary();
            $table->string('Status')->nullable();
        });
        DB::table('FINANCE_PAYMENT')->insert(['Payment_ID' => 'PAY-1', 'Status' => 'Waiting Verification']);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->assertSame(1, $this->badges->paymentsWaitingVerification());
        $queries = DB::getQueryLog();

        $this->assertCount(1, $queries);
        $this->assertStringContainsString('count(', strtolower($queries[0]['query']));
        $this->assertStringNotContainsString('select *', strtolower($queries[0]['query']));
    }

    private function createAttendanceTables(): void
    {
        Schema::create('MASTER_TEACHER', function (Blueprint $table): void {
            $table->string('Teacher_ID')->primary();
            $table->string('User_ID');
            $table->string('Is_Active')->nullable();
        });
        Schema::create('MASTER_CLASS', function (Blueprint $table): void {
            $table->string('Class_ID')->primary();
            $table->string('Homeroom_Teacher_ID')->nullable();
            $table->string('Is_Active')->nullable();
        });
        Schema::create('MASTER_SCHEDULE', function (Blueprint $table): void {
            $table->string('Schedule_ID')->primary();
            $table->string('Class_ID');
            $table->string('Teacher_ID');
            $table->string('Is_Active')->nullable();
        });
        Schema::create('MASTER_STUDENT', function (Blueprint $table): void {
            $table->string('Student_ID')->primary();
            $table->string('User_ID');
            $table->string('Class_ID');
            $table->string('Is_Active')->nullable();
        });
        Schema::create('MASTER_ATTENDANCE_REQUEST', function (Blueprint $table): void {
            $table->string('Request_ID')->primary();
            $table->string('Student_ID');
            $table->string('Class_ID')->nullable();
            $table->string('Schedule_ID')->nullable();
            $table->string('Attendance_Type')->nullable();
            $table->string('Status')->nullable();
        });
    }

    private function createQuizTables(): void
    {
        Schema::create('MASTER_STUDENT', function (Blueprint $table): void {
            $table->string('Student_ID')->primary();
            $table->string('User_ID');
            $table->string('Class_ID');
            $table->string('Is_Active')->nullable();
        });
        Schema::create('QUIZ', function (Blueprint $table): void {
            $table->string('Quiz_ID')->primary();
            $table->string('Class_ID');
            $table->dateTime('Start_At');
            $table->dateTime('End_At');
            $table->string('Status');
        });
        Schema::create('QUIZ_RESULT', function (Blueprint $table): void {
            $table->string('Result_ID')->primary();
            $table->string('Quiz_ID');
            $table->string('Student_ID');
        });
        Schema::create('QUIZ_AUDIENCE_CLASS', function (Blueprint $table): void {
            $table->string('Quiz_ID');
            $table->string('Class_ID');
        });
        Schema::create('PRACTICE_QUIZ', function (Blueprint $table): void {
            $table->string('Practice_Quiz_ID')->primary();
            $table->string('Status');
        });
    }
}

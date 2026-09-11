<?php

namespace Tests\Feature;

use App\Http\Controllers\Student\AttendanceHistoryController;
use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Services\Attendance\AttendanceRequestService;
use App\Services\Core\RoleService;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StudentAttendanceHistoryTest extends TestCase
{
    public function test_student_route_is_accessible_and_renders_separate_page(): void
    {
        $this->bindRouteDependencies('ROLE-STUDENT', 'STUDENT', []);
        $this->actingAs($this->user('USR-A', 'ROLE-STUDENT'));

        $this->get(route('attendances.my-history'))
            ->assertOk()
            ->assertSee('Riwayat Absensi')
            ->assertSee('Belum ada riwayat absensi.');
    }

    public function test_non_student_role_is_denied_by_rbac(): void
    {
        $this->bindRouteDependencies('ROLE-HR', 'HR', []);
        $this->actingAs($this->user('USR-HR', 'ROLE-HR'));

        $this->get(route('attendances.my-history'))->assertForbidden();
    }

    public function test_history_contains_only_authenticated_students_attendance(): void
    {
        $view = $this->historyView([
            $this->attendance('OWN', 'STU-A', '2026-09-05'),
            $this->attendance('OTHER', 'STU-B', '2026-09-06'),
        ]);

        $this->assertSame(['OWN'], $view->getData()['latestAttendances']->pluck('Attendance_ID')->all());
    }

    public function test_forged_student_id_query_is_ignored(): void
    {
        $view = $this->historyView([
            $this->attendance('OWN', 'STU-A', '2026-09-05'),
            $this->attendance('FORGED', 'STU-B', '2026-09-06'),
        ], ['student_id' => 'STU-B']);

        $this->assertSame(['OWN'], $view->getData()['latestAttendances']->pluck('Attendance_ID')->all());
    }

    public function test_unmapped_student_fails_closed_with_403(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-X', 'User_ID' => 'USR-X']));
        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->once()->andReturn(collect());
        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldNotReceive('fetchAll');
        $controller = new AttendanceHistoryController(
            $attendanceRepo,
            $studentRepo,
            Mockery::mock(AttendanceRequestService::class)
        );

        try {
            $controller->index(Request::create('/attendance/my-history'));
            $this->fail('Expected unmapped student identity to fail closed.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_zero_history_returns_empty_collection(): void
    {
        $this->assertCount(0, $this->historyView([])->getData()['latestAttendances']);
    }

    public function test_one_history_returns_one_record(): void
    {
        $this->assertCount(1, $this->historyView([
            $this->attendance('ATT-1', 'STU-A', '2026-09-01'),
        ])->getData()['latestAttendances']);
    }

    public function test_five_histories_return_five_records(): void
    {
        $this->assertCount(5, $this->historyView($this->datedAttendances(5))->getData()['latestAttendances']);
    }

    public function test_six_histories_return_only_newest_five(): void
    {
        $ids = $this->historyView($this->datedAttendances(6))
            ->getData()['latestAttendances']->pluck('Attendance_ID')->all();

        $this->assertCount(5, $ids);
        $this->assertSame(['ATT-6', 'ATT-5', 'ATT-4', 'ATT-3', 'ATT-2'], $ids);
        $this->assertNotContains('ATT-1', $ids);
    }

    public function test_seven_histories_return_only_newest_five(): void
    {
        $ids = $this->historyView($this->datedAttendances(7))
            ->getData()['latestAttendances']->pluck('Attendance_ID')->all();

        $this->assertSame(['ATT-7', 'ATT-6', 'ATT-5', 'ATT-4', 'ATT-3'], $ids);
    }

    public function test_records_are_sorted_newest_to_oldest_by_attendance_date(): void
    {
        $ids = $this->historyView([
            $this->attendance('MIDDLE', 'STU-A', '2026-09-04'),
            $this->attendance('OLDEST', 'STU-A', '2026-09-01'),
            $this->attendance('NEWEST', 'STU-A', '2026-09-07'),
        ])->getData()['latestAttendances']->pluck('Attendance_ID')->all();

        $this->assertSame(['NEWEST', 'MIDDLE', 'OLDEST'], $ids);
    }

    public function test_attendance_date_wins_over_later_creation_date(): void
    {
        $ids = $this->historyView([
            $this->attendance('OLDER-DATE', 'STU-A', '2026-09-01', '09:00:00', '2026-09-10 09:00:00'),
            $this->attendance('NEWER-DATE', 'STU-A', '2026-09-02', '07:00:00', '2026-09-02 07:00:00'),
        ])->getData()['latestAttendances']->pluck('Attendance_ID')->all();

        $this->assertSame(['NEWER-DATE', 'OLDER-DATE'], $ids);
    }

    public function test_legacy_date_is_used_only_when_canonical_attendance_date_is_empty(): void
    {
        $legacy = $this->attendance('LEGACY', 'STU-A', '2026-09-01');
        $legacy['Attendance_Date'] = '';
        $legacy['Date'] = '2026-09-08';
        $canonical = $this->attendance('CANONICAL', 'STU-A', '2026-09-07');
        $canonical['Date'] = '2026-09-30';

        $ids = $this->historyView([$canonical, $legacy])
            ->getData()['latestAttendances']->pluck('Attendance_ID')->all();

        $this->assertSame(['LEGACY', 'CANONICAL'], $ids);
    }

    public function test_same_date_uses_check_in_then_created_at_then_id_deterministically(): void
    {
        $ids = $this->historyView([
            $this->attendance('ATT-A', 'STU-A', '2026-09-05', '08:00:00', '2026-09-05 08:05:00'),
            $this->attendance('ATT-B', 'STU-A', '2026-09-05', '09:00:00', '2026-09-05 09:00:00'),
            $this->attendance('ATT-C', 'STU-A', '2026-09-05', '08:00:00', '2026-09-05 08:10:00'),
            $this->attendance('ATT-D', 'STU-A', '2026-09-05', '08:00:00', '2026-09-05 08:10:00'),
        ])->getData()['latestAttendances']->pluck('Attendance_ID')->all();

        $this->assertSame(['ATT-B', 'ATT-D', 'ATT-C', 'ATT-A'], $ids);
    }

    public function test_limit_is_applied_after_student_filtering(): void
    {
        $otherStudentsNewest = [];
        for ($day = 20; $day <= 26; $day++) {
            $otherStudentsNewest[] = $this->attendance('OTHER-' . $day, 'STU-B', "2026-09-{$day}");
        }

        $ids = $this->historyView(array_merge($otherStudentsNewest, $this->datedAttendances(6)))
            ->getData()['latestAttendances']->pluck('Attendance_ID')->all();

        $this->assertSame(['ATT-6', 'ATT-5', 'ATT-4', 'ATT-3', 'ATT-2'], $ids);
    }

    public function test_history_read_does_not_mutate_or_delete_old_repository_rows(): void
    {
        $sourceRows = collect($this->datedAttendances(6));
        $this->actingAs(new GenericUser(['id' => 'USR-A', 'User_ID' => 'USR-A']));
        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Student_ID' => 'STU-A', 'User_ID' => 'USR-A'],
        ]));
        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('fetchAll')->once()->andReturn($sourceRows);
        $attendanceRepo->shouldNotReceive('create', 'update', 'softDelete');
        $requests = Mockery::mock(AttendanceRequestService::class);
        $requests->shouldReceive('getStudentRequests')->once()->with('STU-A')->andReturn(collect());

        (new AttendanceHistoryController($attendanceRepo, $studentRepo, $requests))
            ->index(Request::create('/attendance/my-history'));

        $this->assertCount(6, $sourceRows);
        $this->assertSame('ATT-1', $sourceRows->first()['Attendance_ID']);
    }

    public function test_empty_state_and_no_pagination_controls_are_defined(): void
    {
        $source = file_get_contents(resource_path('views/attendance/my_history.blade.php'));

        $this->assertStringContainsString('Belum ada riwayat absensi.', $source);
        $this->assertStringNotContainsString('pagination', strtolower($source));
        $this->assertStringNotContainsString('load more', strtolower($source));
    }

    public function test_statuses_use_existing_indonesian_attendance_helper(): void
    {
        $source = file_get_contents(resource_path('views/attendance/my_history.blade.php'));

        $this->assertStringContainsString('AttendanceStatusHelper::label', $source);
        $this->assertStringContainsString('AttendanceStatusHelper::badgeColor', $source);
        $this->assertSame('Hadir', \App\Helpers\AttendanceStatusHelper::label('PRESENT'));
        $this->assertSame('Izin', \App\Helpers\AttendanceStatusHelper::label('PERMISSION'));
    }

    public function test_page_presents_indonesian_date_check_in_and_status(): void
    {
        $html = view('attendance.my_history', [
            'latestAttendances' => collect([$this->attendance('ATT-1', 'STU-A', '2026-09-07', '07:54:00')]),
            'student' => ['Student_ID' => 'STU-A'],
            'hadirBulanIni' => 1,
            'terlambatBulanIni' => 0,
            'totalPresensiSaya' => 1,
            'userRole' => 'STUDENT',
        ])->render();

        $this->assertStringContainsString('Senin, 7 September 2026', $html);
        $this->assertStringContainsString('07:54', $html);
        $this->assertStringContainsString('Hadir', $html);
    }

    public function test_attendance_history_is_removed_from_nilai_view(): void
    {
        $source = file_get_contents(resource_path('views/academic/student/progress.blade.php'));

        $this->assertStringNotContainsString('Riwayat Kehadiran', $source);
        $this->assertStringNotContainsString('$myAttendances', $source);
        $this->assertStringNotContainsString('student.export-attendances', $source);
    }

    public function test_nilai_view_still_renders_scores_and_assessment_details(): void
    {
        $html = view('academic.student.progress', [
            'progress' => ['gpa' => 90, 'attendance' => 100, 'total_assessments' => 1],
            'studentId' => 'STU-A',
            'myScores' => collect([[
                'Assessment_Category' => 'UJIAN_BAB',
                'Score' => 90,
                'Evaluation_Details' => json_encode(['notes' => 'Baik']),
                'Created_At' => '2026-09-07 08:00:00',
            ]]),
            'assessmentConfigs' => [],
            'userRole' => 'STUDENT',
        ])->render();

        $this->assertStringContainsString('Riwayat Nilai', $html);
        $this->assertStringContainsString('Penilaian UJIAN_BAB', $html);
        $this->assertStringContainsString('90', $html);
        $this->assertStringNotContainsString('Riwayat Kehadiran', $html);
    }

    public function test_desktop_student_menu_contains_attendance_history(): void
    {
        $this->actingAs(new GenericUser(['id' => 'USR-A', 'User_ID' => 'USR-A', 'Role' => 'STUDENT']));
        $html = view('components.dashboard.sidebar', ['userRole' => 'STUDENT'])->render();

        $this->assertStringContainsString(route('attendances.my-history'), $html);
        $this->assertStringContainsString('Riwayat Absensi', $html);
    }

    public function test_mobile_student_navigation_contains_attendance_history(): void
    {
        $html = view('components.mobile-bottom-nav', ['userRole' => 'STUDENT'])->render();

        $this->assertStringContainsString(route('attendances.my-history'), $html);
        $this->assertStringContainsString('Riwayat Absensi', $html);
        $this->assertStringContainsString('min-h-[44px]', $html);
    }

    public function test_history_layout_is_mobile_first_and_expands_to_desktop_cards(): void
    {
        $source = file_get_contents(resource_path('views/attendance/my_history.blade.php'));

        $this->assertStringContainsString('min-w-0', $source);
        $this->assertStringContainsString('break-words', $source);
        $this->assertStringContainsString('md:grid-cols-2', $source);
        $this->assertStringNotContainsString('overflow-x-auto', $source);
        $this->assertStringNotContainsString('whitespace-nowrap', $source);
    }

    public function test_history_route_has_no_student_identifier_and_is_read_only(): void
    {
        $route = app('router')->getRoutes()->getByName('attendances.my-history');

        $this->assertNotNull($route);
        $this->assertSame('attendance/my-history', $route->uri());
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertStringNotContainsString('{student', strtolower($route->uri()));
        $this->assertContains('role:STUDENT', $route->gatherMiddleware());
    }

    private function historyView(array $attendances, array $query = [])
    {
        $this->actingAs(new GenericUser(['id' => 'USR-A', 'User_ID' => 'USR-A']));
        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Student_ID' => 'STU-A', 'User_ID' => 'USR-A'],
            ['Student_ID' => 'STU-B', 'User_ID' => 'USR-B'],
        ]));
        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('fetchAll')->once()->andReturn(collect($attendances));
        $requests = Mockery::mock(AttendanceRequestService::class);
        $requests->shouldReceive('getStudentRequests')->once()->with('STU-A')->andReturn(collect());

        $controller = new AttendanceHistoryController($attendanceRepo, $studentRepo, $requests);

        return $controller->index(Request::create('/attendance/my-history', 'GET', $query));
    }

    private function bindRouteDependencies(string $roleId, string $roleName, array $attendances): void
    {
        $roleService = Mockery::mock(RoleService::class);
        $roleService->shouldReceive('getRoleById')->zeroOrMoreTimes()->with($roleId)->andReturn([
            'Role_ID' => $roleId,
            'Role_Name' => $roleName,
            'Is_Active' => 'TRUE',
        ]);
        $this->app->instance(RoleService::class, $roleService);

        $studentRepo = Mockery::mock(StudentRepositoryInterface::class);
        $studentRepo->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect([
            ['Student_ID' => 'STU-A', 'User_ID' => 'USR-A'],
        ]));
        $this->app->instance(StudentRepositoryInterface::class, $studentRepo);

        $attendanceRepo = Mockery::mock(AttendanceRepositoryInterface::class);
        $attendanceRepo->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn(collect($attendances));
        $this->app->instance(AttendanceRepositoryInterface::class, $attendanceRepo);

        $requests = Mockery::mock(AttendanceRequestService::class);
        $requests->shouldReceive('getStudentRequests')->zeroOrMoreTimes()->andReturn(collect());
        $this->app->instance(AttendanceRequestService::class, $requests);
    }

    private function user(string $userId, string $roleId): GenericUser
    {
        return new GenericUser([
            'id' => $userId,
            'User_ID' => $userId,
            'Role_ID' => $roleId,
            'Role' => str_replace('ROLE-', '', $roleId),
        ]);
    }

    private function attendance(
        string $id,
        string $studentId,
        string $date,
        string $checkIn = '08:00:00',
        ?string $createdAt = null,
        string $status = 'PRESENT'
    ): array {
        return [
            'Attendance_ID' => $id,
            'Student_ID' => $studentId,
            'Attendance_Date' => $date,
            'Check_In_Time' => $checkIn,
            'Status' => $status,
            'Created_At' => $createdAt ?? $date . ' ' . $checkIn,
        ];
    }

    private function datedAttendances(int $count): array
    {
        $rows = [];
        for ($day = 1; $day <= $count; $day++) {
            $rows[] = $this->attendance('ATT-' . $day, 'STU-A', sprintf('2026-09-%02d', $day));
        }

        return $rows;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class MobileNavigationHardeningTest extends TestCase
{
    public function test_teacher_bottom_navigation_uses_teacher_workspace_routes(): void
    {
        $html = view('components.mobile-bottom-nav', ['userRole' => 'TEACHER'])->render();

        $this->assertStringContainsString(route('teacher.workspace.assignments'), $html);
        $this->assertStringContainsString(route('teacher.workspace.scores'), $html);
        $this->assertStringNotContainsString(route('assignments.index'), $html);
    }

    public function test_hr_bottom_navigation_uses_hr_attendance_monitoring(): void
    {
        $html = view('components.mobile-bottom-nav', ['userRole' => 'HR'])->render();

        $this->assertStringContainsString(route('hr.attendance.monitoring'), $html);
        $this->assertStringNotContainsString(route('attendances.index'), $html);
    }

    public function test_employee_bottom_navigation_has_mobile_primary_destinations(): void
    {
        $html = view('components.mobile-bottom-nav', ['userRole' => 'EMPLOYEE'])->render();

        $this->assertStringContainsString(route('dashboard.personal-payroll'), $html);
        $this->assertStringContainsString(route('hr.attendance.qr.scanner'), $html);
        $this->assertStringContainsString(route('notifications.index'), $html);
        $this->assertStringContainsString(route('profile.index'), $html);
    }

    public function test_marketing_navigation_does_not_expose_administrator_documents(): void
    {
        Auth::login($this->fakeUser('MARKETING'));

        $bottomNav = view('components.mobile-bottom-nav', ['userRole' => 'MARKETING'])->render();
        $drawer = view('components.dashboard.sidebar', ['userRole' => 'MARKETING'])->render();

        $this->assertStringContainsString(route('companies.index'), $bottomNav);
        $this->assertStringContainsString(route('notifications.index'), $bottomNav);
        $this->assertStringNotContainsString(route('documents.index'), $bottomNav);
        $this->assertStringNotContainsString(route('documents.index'), $drawer);
    }

    public function test_director_drawer_uses_authorized_finance_reporting_routes(): void
    {
        Auth::login($this->fakeUser('DIRECTOR'));

        $drawer = view('components.dashboard.sidebar', ['userRole' => 'DIRECTOR'])->render();

        $this->assertStringContainsString(route('approvals.index'), $drawer);
        $this->assertStringContainsString(route('transactions.index'), $drawer);
        $this->assertStringContainsString(route('reports.finance.index'), $drawer);
        $this->assertStringNotContainsString(route('finance.smart_generator.index'), $drawer);
        $this->assertStringNotContainsString(route('alumni.index'), $drawer);
    }

    public function test_teacher_drawer_contains_all_teacher_workspace_destinations(): void
    {
        Auth::login($this->fakeUser('TEACHER'));

        $html = view('components.dashboard.sidebar', ['userRole' => 'TEACHER'])->render();

        foreach ([
            'teacher.workspace.schedule',
            'teacher.workspace.classes',
            'teacher.workspace.students',
            'teacher.workspace.attendances',
            'teacher.workspace.attendance-requests',
            'teacher.workspace.scores',
            'teacher.workspace.assignments',
            'teacher.workspace.calendar',
            'teacher.workspace.reports',
        ] as $routeName) {
            $this->assertStringContainsString(route($routeName), $html);
        }
    }

    public function test_student_drawer_contains_authorized_student_destinations(): void
    {
        Auth::login($this->fakeUser('STUDENT'));

        $html = view('components.dashboard.sidebar', ['userRole' => 'STUDENT'])->render();

        foreach ([
            'attendances.student.scanner',
            'attendances.my-history',
            'student.schedule',
            'student.calendar',
            'student.subjects',
            'student.portal.assignments',
            'student.progress',
            'student.portal.materials',
            'student.billing.index',
            'student.attendance.requests.index',
            'notifications.index',
        ] as $routeName) {
            $this->assertStringContainsString(route($routeName), $html);
        }
    }

    public function test_mobile_shell_exposes_drawer_trigger_and_safe_area_hooks(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('wms-mobile-header', $layout);
        $this->assertStringContainsString('wms-mobile-menu-trigger', $layout);
        $this->assertStringContainsString('toggle-sidebar-mobile', $layout);
        $this->assertStringContainsString('@stack(\'scripts\')', $layout);
        $this->assertStringContainsString('100dvh', $css);
        $this->assertStringContainsString('env(safe-area-inset-bottom', $css);
        $this->assertStringContainsString('wms-mobile-drawer-open', $css);
    }

    public function test_teacher_dashboard_does_not_keep_placeholder_mobile_actions(): void
    {
        $source = file_get_contents(resource_path('views/dashboard/teacher.blade.php'));

        $this->assertStringNotContainsString("'link' => '#'", $source);
        $this->assertStringContainsString("route('teacher.workspace.calendar')", $source);
        $this->assertStringContainsString("route('teacher.workspace.reports')", $source);
    }

    public function test_mobile_student_and_teacher_views_do_not_use_raw_id_fallbacks_as_labels(): void
    {
        $teacherClasses = file_get_contents(resource_path('views/academic/teacher/classes.blade.php'));
        $teacherStudents = file_get_contents(resource_path('views/academic/teacher/students.blade.php'));
        $studentMaterials = file_get_contents(resource_path('views/student/portal/materials.blade.php'));

        $this->assertStringNotContainsString("\$c['Class_Code'] ?? \$c['Class_ID']", $teacherClasses);
        $this->assertStringNotContainsString("\$c['Batch_Name'] ?? \$c['Batch_ID']", $teacherClasses);
        $this->assertStringNotContainsString("\$s['Batch_ID'] ??", $teacherStudents);
        $this->assertStringNotContainsString("\$subject['Subject_Code'] ?? \$subject['Subject_ID']", $studentMaterials);
        $this->assertStringContainsString('Kode kelas belum tersedia', $teacherClasses);
        $this->assertStringContainsString('Batch tidak ditemukan', $teacherStudents);
        $this->assertStringContainsString('Kode materi belum tersedia', $studentMaterials);
    }

    private function fakeUser(string $role): User
    {
        $user = new User();
        $user->setAttribute('id', 9001);
        $user->setAttribute('Username', $role . 'User');
        $user->setAttribute('Name', $role . ' User');
        $user->setAttribute('Role', $role);

        return $user;
    }
}

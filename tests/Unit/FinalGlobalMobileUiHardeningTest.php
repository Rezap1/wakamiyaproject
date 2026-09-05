<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class FinalGlobalMobileUiHardeningTest extends TestCase
{
    public function test_layout_is_the_only_owner_of_mobile_application_header(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $dashboard = file_get_contents(resource_path('views/components/mobile-dashboard-hero.blade.php'));

        $this->assertSame(1, substr_count($layout, 'data-mobile-app-header'));
        $this->assertStringContainsString('viewport-fit=cover', $layout);
        $this->assertStringNotContainsString('toggleSidebar()', $dashboard);
        $this->assertStringNotContainsString('data-mobile-app-header', $dashboard);
        $this->assertStringNotContainsString("route('notifications.index') }}\" class=\"relative", $dashboard);
    }

    public function test_mobile_dashboard_contract_has_no_tablet_breakpoint_gap(): void
    {
        $admin = file_get_contents(resource_path('views/dashboard/index.blade.php'));
        $student = file_get_contents(resource_path('views/dashboard/student.blade.php'));
        $component = file_get_contents(resource_path('views/components/mobile-dashboard-hero.blade.php'));

        $this->assertStringContainsString('block lg:hidden', $admin);
        $this->assertStringContainsString('hidden lg:block', $admin);
        $this->assertStringNotContainsString('block md:hidden mb-4', $admin);
        $this->assertStringContainsString('data-mobile-dashboard', $component);
        $this->assertStringContainsString('data-mobile-dashboard-actions', $component);
        $this->assertStringNotContainsString('pb-28', $component);
        $this->assertStringContainsString('block md:hidden', $student);
    }

    public function test_role_dashboard_actions_only_render_existing_authorized_routes(): void
    {
        foreach (['MASTER', 'ADMINISTRATOR', 'ACADEMIC', 'FINANCE', 'HR', 'MARKETING', 'DIRECTOR', 'STUDENT', 'EMPLOYEE'] as $role) {
            Auth::login($this->fakeUser($role));
            $html = view('components.mobile-dashboard-hero', [
                'userRole' => $role,
                'kpiData' => [],
            ])->render();

            $this->assertStringContainsString('data-role="'.$role.'"', $html, $role);
            $this->assertStringNotContainsString('href="#"', $html, $role);
        }

        Auth::login($this->fakeUser('MARKETING'));
        $marketing = view('components.mobile-dashboard-hero', ['userRole' => 'MARKETING'])->render();
        $this->assertStringNotContainsString(route('documents.index'), $marketing);

        Auth::login($this->fakeUser('DIRECTOR'));
        $director = view('components.mobile-dashboard-hero', ['userRole' => 'DIRECTOR'])->render();
        $this->assertStringNotContainsString(route('finance.smart_generator.index'), $director);
    }

    public function test_employee_dashboard_route_has_a_real_presentation_target(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Core/DashboardController.php'));
        $authController = file_get_contents(app_path('Http/Controllers/Core/AuthController.php'));
        $view = file_get_contents(resource_path('views/dashboard/employee.blade.php'));

        $this->assertStringContainsString("str_contains(\$roleName, 'employee')", $controller);
        $this->assertStringContainsString("return view('dashboard.employee')", $controller);
        $this->assertStringContainsString("\$alias === 'employee'", $authController);
        $this->assertStringContainsString("redirect()->intended(route('dashboard'))", $authController);
        $this->assertStringContainsString('user-role="EMPLOYEE"', $view);
        $this->assertStringContainsString("route('dashboard.personal-payroll')", $view);
        $this->assertStringContainsString("route('hr.attendance.qr.scanner')", $view);
    }

    public function test_shared_mobile_css_reserves_navigation_and_prevents_body_overflow(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('overflow-x: clip', $css);
        $this->assertStringContainsString('.wms-page-content > *', $css);
        $this->assertStringContainsString('.wms-mobile-action-grid', $css);
        $this->assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr))', $css);
        $this->assertStringContainsString('env(safe-area-inset-bottom', $css);
        $this->assertStringContainsString('.wms-modal-open', $css);
        $this->assertStringNotContainsString('body { overflow-x: auto', $css);
    }

    public function test_shared_overlays_fit_mobile_viewport_and_expose_accessible_controls(): void
    {
        $modal = file_get_contents(resource_path('views/components/modal.blade.php'));
        $confirm = file_get_contents(resource_path('views/components/confirm-dialog.blade.php'));
        $toast = file_get_contents(resource_path('views/components/toast.blade.php'));

        $this->assertStringContainsString('max-h-[calc(100dvh-1.5rem)]', $modal);
        $this->assertStringContainsString('aria-labelledby="{{ $id }}-title"', $modal);
        $this->assertStringContainsString('w-11 h-11', $modal);
        $this->assertStringContainsString('aria-describedby="confirm-message"', $confirm);
        $this->assertStringContainsString('max-h-[calc(100dvh-1.5rem)]', $confirm);
        $this->assertStringContainsString("event.key === 'Escape'", $confirm);
        $this->assertStringContainsString('left-3 right-3', $toast);
        $this->assertStringContainsString('aria-live="polite"', $toast);
        $this->assertStringContainsString('aria-label="Tutup notifikasi"', $toast);
    }

    public function test_searchable_select_exposes_label_and_listbox_relationships(): void
    {
        $select = file_get_contents(resource_path('views/components/universal/searchable-select.blade.php'));

        $this->assertStringContainsString('for="{{ $name }}-trigger"', $select);
        $this->assertStringContainsString('aria-haspopup="listbox"', $select);
        $this->assertStringContainsString('role="listbox"', $select);
        $this->assertStringContainsString('role="option"', $select);
    }

    public function test_scan_actions_use_a_real_camera_icon_instead_of_fallback(): void
    {
        $icons = file_get_contents(resource_path('views/components/sidebar/icon.blade.php'));

        $this->assertStringContainsString("@case('camera')", $icons);
    }

    private function fakeUser(string $role): User
    {
        $user = new User();
        $user->setAttribute('id', 8450);
        $user->setAttribute('User_ID', 'USR-H845-'.$role);
        $user->setAttribute('Username', $role.'User');
        $user->setAttribute('Full_Name', 'Pengguna '.$role);
        $user->setAttribute('Role', $role);

        return $user;
    }
}

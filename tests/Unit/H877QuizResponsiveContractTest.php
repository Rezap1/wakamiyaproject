<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class H877QuizResponsiveContractTest extends TestCase
{
    #[DataProvider('mobileWidths')]
    public function test_student_player_has_mobile_safe_contract_at_required_widths(int $width): void
    {
        $player = file_get_contents(resource_path('views/quiz/student/player.blade.php'));
        $index = file_get_contents(resource_path('views/quiz/student/index.blade.php'));
        $leaderboard = file_get_contents(resource_path('views/quiz/leaderboard.blade.php'));
        $teacherForm = file_get_contents(resource_path('views/quiz/teacher/form.blade.php'));
        $teacherResults = file_get_contents(resource_path('views/quiz/teacher/results.blade.php'));
        $teacherScores = file_get_contents(resource_path('views/academic/teacher/scores.blade.php'));
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertContains($width, [375, 390, 430]);
        $this->assertStringContainsString('width=device-width', $layout);
        $this->assertStringContainsString('max-w-3xl', $player);
        $this->assertStringContainsString('pb-32', $player);
        $this->assertStringContainsString('min-h-14', $player);
        $this->assertStringContainsString('sticky', $player);
        $this->assertStringContainsString('pb-28', $index);
        $this->assertStringContainsString('max-w-4xl', $leaderboard);
        $this->assertStringContainsString('min-w-0', $leaderboard);
        $this->assertStringContainsString('grid-cols-[2.75rem_minmax(0,1fr)]', $teacherForm);
        $this->assertStringContainsString('data-option-input', $teacherForm);
        $this->assertStringContainsString('min-h-12', $teacherResults);
        $this->assertStringContainsString('pb-28', $teacherResults);
        $this->assertStringContainsString('grid-cols-2', $teacherScores);
        $this->assertStringContainsString('min-h-11', $teacherScores);
        $this->assertStringContainsString('pb-28', $teacherScores);
    }

    public static function mobileWidths(): array
    {
        return [[375], [390], [430]];
    }

    public function test_tablet_desktop_teacher_builder_and_navigation_contracts(): void
    {
        $form = file_get_contents(resource_path('views/quiz/teacher/form.blade.php'));
        $nav = file_get_contents(resource_path('views/components/mobile-bottom-nav.blade.php'));
        $fab = file_get_contents(resource_path('views/components/floating-qr-button.blade.php'));

        $this->assertStringContainsString('max-w-5xl', $form);
        $this->assertStringContainsString('sm:grid-cols-2', $form);
        $this->assertStringContainsString('md:bottom-4', $form);
        $this->assertStringContainsString("'teacher.quizzes.index'", $nav);
        $this->assertStringContainsString("'student.quizzes.index'", $nav);
        $this->assertStringContainsString("route('attendances.student.scanner')", $fab);
        $this->assertStringContainsString('env(safe-area-inset-bottom', $nav);
    }

    public function test_raw_point_dashboard_delete_and_blade_render_contracts(): void
    {
        $studentResult = file_get_contents(resource_path('views/quiz/student/result.blade.php'));
        $studentIndex = file_get_contents(resource_path('views/quiz/student/index.blade.php'));
        $teacherShow = file_get_contents(resource_path('views/quiz/teacher/show.blade.php'));
        $teacherResults = file_get_contents(resource_path('views/quiz/teacher/results.blade.php'));
        $teacherResult = file_get_contents(resource_path('views/quiz/teacher/result.blade.php'));
        $dashboard = file_get_contents(resource_path('views/dashboard/student.blade.php'));

        $this->assertStringContainsString('$result[\'Raw_Score\']', $studentResult);
        $this->assertStringNotContainsString('$result[\'Normalized_Score\']', $studentResult);
        $this->assertStringContainsString('$quiz[\'Raw_Score\']', $studentIndex);
        $this->assertStringNotContainsString('$quiz[\'Normalized_Score\']', $studentIndex);
        $this->assertStringContainsString('Hapus kuis ini?', $teacherShow);
        $this->assertStringContainsString('hasil/nilai siswa, dan poin leaderboard', $teacherShow);
        $this->assertStringContainsString('Hapus Permanen', $teacherShow);
        $this->assertStringContainsString('env(safe-area-inset-bottom', $teacherShow);
        $this->assertStringNotContainsString('Normalized_Score', $teacherShow.$teacherResults.$teacherResult);
        $this->assertStringContainsString('student-quiz-dashboard-heading', $dashboard);
        $this->assertStringContainsString('Tersedia Sekarang', $dashboard);
        $this->assertStringContainsString('Peringkat Kuis', $dashboard);
        $this->assertStringContainsString('order-1', $dashboard);

        $html = view('quiz.leaderboard', [
            'entries' => collect([['Rank' => 1, 'Student_ID' => 'S1', 'Student_Name' => 'Aiko', 'Quiz_Count' => 2, 'Points' => 55]]),
            'period' => ['label' => '1–14 Oktober 2026'],
            'currentStudentId' => 'S1',
            'backRoute' => '/dashboard',
            'classId' => 'C1',
            'className' => 'Kelas Sakura',
            'classOptions' => collect(),
            'userRole' => 'STUDENT',
        ])->render();
        $this->assertStringContainsString('2 kuis', $html);
        $this->assertStringContainsString('>Anda<', $html);
        $this->assertStringContainsString('55 poin', $html);
        $this->assertStringNotContainsString('@if', $html);
        $this->assertStringNotContainsString('@endif', $html);
        $this->assertStringNotContainsString('$currentStudentId', $html);
    }

    #[DataProvider('modalWidths')]
    public function test_delete_modal_has_dynamic_viewport_scroll_lock_and_reachable_footer_contract(int $width): void
    {
        $teacherShow = file_get_contents(resource_path('views/quiz/teacher/show.blade.php'));

        $this->assertContains($width, [375, 390, 430, 768, 1024]);
        $this->assertStringContainsString("document.body.classList.toggle('overflow-hidden', deleteOpen)", $teacherShow);
        $this->assertStringContainsString('max-h-[calc(100dvh-1.5rem)]', $teacherShow);
        $this->assertStringContainsString('min-h-0 overflow-y-auto', $teacherShow);
        $this->assertStringContainsString('sticky bottom-0', $teacherShow);
        $this->assertStringContainsString('shrink-0', $teacherShow);
        $this->assertStringContainsString('env(safe-area-inset-bottom)', $teacherShow);
        $this->assertStringContainsString('z-[80]', $teacherShow);
        $this->assertStringContainsString('Batal', $teacherShow);
        $this->assertStringContainsString('Hapus Permanen', $teacherShow);
    }

    public static function modalWidths(): array
    {
        return [[375], [390], [430], [768], [1024]];
    }

    public function test_class_first_and_leaderboard_visual_contracts_use_real_icons_and_bounded_sections(): void
    {
        $classIndex = file_get_contents(resource_path('views/quiz/teacher/index.blade.php'));
        $classHub = file_get_contents(resource_path('views/quiz/teacher/class-hub.blade.php'));
        $leaderboard = file_get_contents(resource_path('views/quiz/leaderboard.blade.php'));
        $dashboard = file_get_contents(resource_path('views/dashboard/student.blade.php'));

        $this->assertStringContainsString('Buka Kelas', $classIndex);
        $this->assertStringContainsString('Belum ada kelas aktif', $classIndex);
        $this->assertStringContainsString('Belum ada kuis untuk kelas ini.', $classHub);
        $this->assertStringContainsString('teacher.quizzes.class.create', $classHub);
        $this->assertStringContainsString('section_limit', $classHub);
        foreach (['name="trophy"', 'name="star"', 'name="calendar"'] as $icon) {
            $this->assertStringContainsString($icon, $leaderboard.$classHub.$dashboard);
        }
        foreach (['bg-amber-400', 'bg-slate-300', 'bg-orange-300'] as $topTone) {
            $this->assertStringContainsString($topTone, $leaderboard);
            $this->assertStringContainsString($topTone, $dashboard);
        }
        $this->assertStringContainsString('break-words', $leaderboard);
        $this->assertStringContainsString('Posisi Anda', $dashboard);
        $this->assertStringNotContainsString('🏆', $leaderboard.$classHub.$dashboard);
        $this->assertStringNotContainsString('🥇', $leaderboard.$classHub.$dashboard);
    }

    public function test_frontend_uses_bundled_alpine_and_dashboard_notification_snapshot(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $javascript = file_get_contents(resource_path('js/app.js'));
        $teacherDashboard = file_get_contents(resource_path('views/dashboard/teacher.blade.php'));
        $appProvider = file_get_contents(app_path('Providers/AppServiceProvider.php'));

        $this->assertStringNotContainsString('cdn.jsdelivr.net/npm/alpinejs', $layout);
        $this->assertStringContainsString("import Alpine from 'alpinejs'", $javascript);
        $this->assertStringContainsString('isset($unreadNotifications)', $layout);
        $this->assertStringContainsString('$mobileUnreadCount = (int) $unreadNotifications', $layout);
        $this->assertStringNotContainsString('DashboardContextService', $teacherDashboard);
        $this->assertStringNotContainsString('app(', $teacherDashboard);
        $this->assertStringContainsString('$dashboardContext', $teacherDashboard);
        $this->assertStringContainsString("attributes->get('wms.dashboard_context')", $appProvider);
        $this->assertStringContainsString("attributes->set('wms.dashboard_context'", $appProvider);
        $this->assertStringContainsString("['dashboardContext'] ?? null", $appProvider);
    }
}

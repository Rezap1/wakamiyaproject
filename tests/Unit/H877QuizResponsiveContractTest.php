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
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertContains($width, [375, 390, 430]);
        $this->assertStringContainsString('width=device-width', $layout);
        $this->assertStringContainsString('max-w-3xl', $player);
        $this->assertStringContainsString('pb-32', $player);
        $this->assertStringContainsString('min-h-14', $player);
        $this->assertStringContainsString('sticky', $player);
        $this->assertStringContainsString('pb-28', $index);
        $this->assertStringContainsString('max-w-3xl', $leaderboard);
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
}

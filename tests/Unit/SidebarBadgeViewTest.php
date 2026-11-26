<?php

namespace Tests\Unit;

use Tests\TestCase;

class SidebarBadgeViewTest extends TestCase
{
    public function test_badge_caps_at_99_plus_and_has_accessible_label(): void
    {
        $html = view('components.sidebar.badge', [
            'count' => 125,
            'label' => 'kuis reguler aktif belum diselesaikan',
        ])->render();

        $this->assertStringContainsString('99+', $html);
        $this->assertStringContainsString('aria-label="125 kuis reguler aktif belum diselesaikan"', $html);
        $this->assertStringContainsString('bg-red-600', $html);
    }

    public function test_zero_badge_renders_nothing(): void
    {
        $this->assertSame('', trim(view('components.sidebar.badge', ['count' => 0])->render()));
    }

    public function test_nav_link_keeps_label_and_badge_in_a_bounded_flex_row(): void
    {
        $html = view('components.sidebar.nav-link', [
            'href' => '/student/quizzes',
            'badge' => 3,
            'badgeLabel' => 'kuis reguler aktif belum diselesaikan',
            'slot' => 'Kuis',
        ])->render();

        $this->assertStringContainsString('min-w-0', $html);
        $this->assertStringContainsString('overflow-hidden', $html);
        $this->assertStringContainsString('truncate', $html);
        $this->assertStringContainsString('>3<', preg_replace('/\s+/', '', $html));
    }
}

<?php

namespace Tests\Unit;

use Illuminate\Auth\GenericUser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class H868StudentDashboardKpiMobileContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(new GenericUser([
            'id' => 'USR-STU-1',
            'User_ID' => 'USR-STU-1',
            'Role' => 'STUDENT',
            'Full_Name' => 'Student Mobile',
        ]));
    }

    public function test_top_summary_keeps_three_canonical_values_and_status(): void
    {
        $source = $this->studentDashboardSource();

        $this->assertStringContainsString('id="education-payment-summary-heading"', $source);
        $this->assertStringContainsString("number_format(\$kpi['biaya_pendidikan']", $source);
        $this->assertStringContainsString("number_format(\$kpi['sudah_dibayar']", $source);
        $this->assertStringContainsString("number_format(\$kpi['sisa_biaya_pendidikan']", $source);
        $this->assertStringContainsString("\$kpi['status_biaya_pendidikan']", $source);
    }

    public function test_lower_kpi_contains_exact_final_three_cards_without_duplicates(): void
    {
        $source = $this->studentDashboardSource();
        preg_match('/\$formattedKpi = \[(.*?)\n    \];/s', $source, $matches);
        $kpi = $matches[1] ?? '';

        $this->assertSame(3, substr_count($kpi, "['title' =>"));
        $this->assertStringContainsString('"Kelas Hari Ini"', $kpi);
        $this->assertStringContainsString("'Tagihan dari LPK'", $kpi);
        $this->assertStringContainsString("'Pengajuan Presensi'", $kpi);
        $this->assertStringNotContainsString("'Biaya Pendidikan'", $kpi);
        $this->assertStringNotContainsString("'Sudah Dibayar'", $kpi);
        $this->assertStringNotContainsString("'Sisa Biaya Pendidikan'", $kpi);
        $this->assertStringNotContainsString("'Tagihan dari Master'", $kpi);
    }

    public function test_money_values_are_non_wrapping_and_mobile_summary_stacks_until_tablet(): void
    {
        $dashboard = $this->studentDashboardSource();
        $mobileHero = file_get_contents(resource_path('views/components/mobile-dashboard-hero.blade.php'));

        $this->assertSame(3, substr_count($dashboard, 'whitespace-nowrap text-lg font-black'));
        $this->assertStringContainsString('grid grid-cols-1 gap-3 sm:grid-cols-3', $dashboard);
        $this->assertStringContainsString("'whitespace-nowrap text-xl min-[390px]:text-2xl'", $mobileHero);
        $this->assertStringContainsString("'min-[360px]:grid-cols-2'", $mobileHero);
        $this->assertStringContainsString('count($metrics) % 2 === 1', $mobileHero);
    }

    #[DataProvider('mobileViewports')]
    public function test_mobile_viewport_contract_keeps_money_readable(int $viewport): void
    {
        $html = $this->renderDashboard();

        $this->assertLessThan(640, $viewport);
        $this->assertStringContainsString('sm:grid-cols-3', $html);
        $this->assertStringContainsString('whitespace-nowrap', $html);
        $this->assertStringContainsString('Rp 7.500.000', $html);
        $this->assertStringContainsString('Rp 0', $html);
        $this->assertStringContainsString('Sisa Biaya Pendidikan', $html);
        $this->assertStringContainsString('Tagihan dari LPK', $html);
        $this->assertStringNotContainsString('Tagihan dari Master', $html);
    }

    public static function mobileViewports(): array
    {
        return [
            '375px' => [375],
            '390px' => [390],
            '430px' => [430],
        ];
    }

    public function test_desktop_contract_uses_three_proportional_kpi_columns(): void
    {
        $dashboard = $this->studentDashboardSource();
        $actionCenter = file_get_contents(resource_path('views/components/dashboard/action-center.blade.php'));

        $this->assertSame(3, substr_count($this->formattedKpiSource($dashboard), "['title' =>"));
        $this->assertStringContainsString("3 => 'lg:grid-cols-3'", $actionCenter);
    }

    public function test_bottom_navigation_safe_spacing_remains_intact(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('padding-bottom: calc(7.25rem + env(safe-area-inset-bottom, 0px));', $css);
        $this->assertStringContainsString('min-height: calc(4rem + env(safe-area-inset-bottom, 0px));', $css);
        $this->assertStringContainsString('<x-mobile-bottom-nav', $layout);
    }

    private function renderDashboard(): string
    {
        return view('dashboard.student', [
            'announcements' => collect(),
            'kpi' => [
                'today_class' => 1,
                'biaya_pendidikan' => 7_500_000,
                'sudah_dibayar' => 0,
                'sisa_biaya_pendidikan' => 7_500_000,
                'status_biaya_pendidikan' => 'BELUM BAYAR',
                'tagihan_master' => 0,
                'request_pending' => 0,
                'request_approved' => 0,
            ],
            'langProgress' => 0,
            'reminders' => [],
            'recentActivities' => [],
        ])->render();
    }

    private function studentDashboardSource(): string
    {
        return file_get_contents(resource_path('views/dashboard/student.blade.php'));
    }

    private function formattedKpiSource(string $source): string
    {
        preg_match('/\$formattedKpi = \[(.*?)\n    \];/s', $source, $matches);

        return $matches[1] ?? '';
    }
}

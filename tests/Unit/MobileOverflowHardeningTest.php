<?php

namespace Tests\Unit;

use Tests\TestCase;

class MobileOverflowHardeningTest extends TestCase
{
    public function test_student_billing_uses_mobile_cards_with_reachable_payment_actions(): void
    {
        $source = file_get_contents(resource_path('views/student/billing/index.blade.php'));

        $this->assertStringContainsString('data-mobile-billing-cards', $source);
        $this->assertStringContainsString('md:hidden', $source);
        $this->assertStringContainsString('Detail / Bayar', $source);
        $this->assertStringContainsString('Total Tagihan', $source);
        $this->assertStringContainsString('Sudah Dibayar', $source);
        $this->assertStringContainsString('Sisa', $source);
        $this->assertStringContainsString('Jatuh Tempo', $source);
        $this->assertStringContainsString('Bayar Mandiri', $source);
    }

    public function test_student_billing_desktop_table_is_local_scroll_region_not_body_overflow(): void
    {
        $source = file_get_contents(resource_path('views/student/billing/index.blade.php'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('data-desktop-billing-table', $source);
        $this->assertStringContainsString('app-table-responsive hidden md:block', $source);
        $this->assertStringContainsString('.app-table-responsive', $css);
        $this->assertStringContainsString('overflow-x: auto', $css);
        $this->assertStringContainsString('touch-action: pan-x pan-y', $css);
        $this->assertStringNotContainsString('body { overflow-x: auto', $css);
    }

    public function test_shared_table_components_enforce_local_scroll_and_shrink_safe_parents(): void
    {
        $universalTable = file_get_contents(resource_path('views/components/universal/data-table.blade.php'));
        $legacyTable = file_get_contents(resource_path('views/components/table.blade.php'));
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('flex min-w-0 max-w-full flex-col', $universalTable);
        $this->assertStringContainsString('app-table-responsive', $universalTable);
        $this->assertStringContainsString('app-table-responsive', $legacyTable);
        $this->assertStringContainsString('wms-page-content', $layout);
        $this->assertStringContainsString('.main-content,', $css);
        $this->assertStringContainsString('min-width: 0', $css);
    }

    public function test_finance_critical_action_columns_use_wrapping_action_groups(): void
    {
        foreach ([
            'views/finance/payments/index.blade.php',
            'views/finance/invoices/index.blade.php',
            'views/finance/transactions/index.blade.php',
            'views/hr/payroll/index.blade.php',
            'views/student/billing/show.blade.php',
        ] as $relativePath) {
            $source = file_get_contents(resource_path($relativePath));

            $this->assertStringContainsString('wms-action-group', $source, $relativePath);
        }
    }
}

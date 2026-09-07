<?php

namespace Tests\Feature;

use App\Services\Core\RoleService;
use App\Services\Dashboard\FinanceDashboardService;
use Illuminate\Auth\GenericUser;
use Mockery;
use Tests\TestCase;

class FinanceDashboardPresentationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_finance_dashboard_renders_single_finance_presentation_without_duplicate_hero_or_salary_card(): void
    {
        $this->actingAs($this->financeUser());
        $this->mockFinanceRoleService();
        $this->app->instance(FinanceDashboardService::class, $this->financeDashboardService());

        $response = $this->get(route('dashboard.finance'));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, 'data-finance-dashboard'));
        $this->assertStringContainsString('Dasbor Keuangan', $html);
        $this->assertStringNotContainsString('Dashboard Keuangan', $html);
        $this->assertStringContainsString('Pusat kendali keuangan WMS.', $html);
        $this->assertStringContainsString('Saldo Kas', $html);
        $this->assertStringContainsString('Penerimaan Bulan Ini', $html);
        $this->assertStringContainsString('Arus Kas Bulanan', $html);
        $this->assertStringContainsString('Penerimaan', $html);
        $this->assertStringContainsString('Pengeluaran', $html);
        $this->assertStringContainsString('Aksi Cepat', $html);
        $this->assertStringContainsString('Verifikasi Pembayaran Diperlukan', $html);
        $this->assertStringContainsString('Tagihan Jatuh Tempo', $html);
        $this->assertStringContainsString('Masuk', $html);
        $this->assertStringContainsString('Pemasaran', $html);
        $this->assertStringContainsString('Transaksi Keuangan', $html);
        $this->assertStringNotContainsString('Ringkasan Keuangan', $html);
        $this->assertStringNotContainsString('Cash Collected Bulan Ini', $html);
        $this->assertStringNotContainsString('Gaji Bulan Ini', $html);
        $this->assertStringNotContainsString('Income', $html);
        $this->assertStringNotContainsString('Expense', $html);
        $this->assertStringNotContainsString('Payment Verification Needed', $html);
        $this->assertStringNotContainsString('Invoice Overdue', $html);
        $this->assertStringNotContainsString('Login', $html);
        $this->assertStringNotContainsString('Marketing', $html);
        $this->assertStringNotContainsString('Selamat datang', $html);
    }

    private function financeUser(): GenericUser
    {
        return new GenericUser([
            'id' => 'USR-FINANCE',
            'User_ID' => 'USR-FINANCE',
            'Role_ID' => 'ROLE-FINANCE',
            'Role' => 'FINANCE',
        ]);
    }

    private function mockFinanceRoleService(): void
    {
        $roleService = Mockery::mock(RoleService::class);
        $roleService->shouldReceive('getRoleById')->with('ROLE-FINANCE')->andReturn([
            'Role_ID' => 'ROLE-FINANCE',
            'Role_Name' => 'FINANCE',
            'Is_Active' => 'TRUE',
        ])->zeroOrMoreTimes();
        $this->app->instance(RoleService::class, $roleService);
    }

    private function financeDashboardService(): FinanceDashboardService
    {
        $service = Mockery::mock(FinanceDashboardService::class);
        $service->shouldReceive('getDashboardData')->once()->andReturn([
            'kpi' => [
                'cash_balance' => 1250000,
                'revenue_this_month' => 4500000,
                'expense_this_month' => 1250000,
                'outstanding_amount' => 875000,
                'pending_verification' => 3,
                'collection_rate' => 68,
                'overdue_invoices' => 2,
            ],
            'charts' => [
                'cashFlow' => [
                    'labels' => ['Apr', 'Mei'],
                    'income' => [1000000, 2000000],
                    'expense' => [500000, 750000],
                ],
            ],
            'reminders' => [
                [
                    'title' => 'Payment Verification Needed',
                    'description' => 'Terdapat 3 pembayaran menunggu verifikasi.',
                    'action_url' => route('payments.index'),
                ],
                [
                    'title' => 'Invoice Overdue',
                    'description' => 'Terdapat 2 tagihan melewati jatuh tempo.',
                    'action_url' => route('invoices.index'),
                ],
            ],
            'recentActivities' => [
                [
                    'title' => 'LOGIN',
                    'description' => 'FINANCE â€” Aktivitas LOGIN pada USR-FINANCE',
                    'time' => 'Baru saja',
                ],
                [
                    'title' => 'Generate_Invoice',
                    'description' => 'FINANCE_TRANSACTION â€” Aktivitas Generate_Invoice pada INV-001',
                    'time' => '1 jam lalu',
                ],
                [
                    'title' => 'PUBLISH',
                    'description' => 'MARKETING â€” Aktivitas PUBLISH pada CMP-001',
                    'time' => '1 jam lalu',
                ],
            ],
            'notifications' => [
                'pendingVerification' => [],
            ],
            'unreadNotifications' => 0,
        ]);

        return $service;
    }
}

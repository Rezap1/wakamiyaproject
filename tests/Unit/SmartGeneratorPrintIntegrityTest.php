<?php

namespace Tests\Unit;

use App\Http\Controllers\Finance\SmartGeneratorController;
use App\Services\Core\SystemSettingService;
use App\Services\Finance\InvoiceService;
use Illuminate\Support\Facades\Route;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class SmartGeneratorPrintIntegrityTest extends TestCase
{
    private function generatorView(): string
    {
        return (string) file_get_contents(resource_path('views/finance/smart_generator.blade.php'));
    }

    private function invoiceView(): string
    {
        return (string) file_get_contents(resource_path('views/pdf/smart_generator_invoice.blade.php'));
    }

    private function receiptView(): string
    {
        return (string) file_get_contents(resource_path('views/pdf/smart_generator_kwitansi.blade.php'));
    }

    public function test_generator_has_one_authoritative_printable_region(): void
    {
        $view = $this->generatorView();
        $this->assertSame(1, substr_count($view, 'data-printable-document="true"'));
        $this->assertStringContainsString('id="a4CanvasSheet"', $view);
    }

    public function test_each_live_document_variant_marks_its_body(): void
    {
        $view = $this->generatorView();
        $this->assertStringContainsString('data-document-body="invoice"', $view);
        $this->assertStringContainsString('data-document-body="kwitansi"', $view);
    }

    public function test_print_css_does_not_hide_the_entire_body_with_visibility(): void
    {
        $view = $this->generatorView();
        $this->assertStringNotContainsString('body * {\n        visibility: hidden', $view);
        $this->assertStringContainsString('body.smart-generator-printing', $view);
    }

    public function test_print_css_hides_only_application_chrome(): void
    {
        $view = $this->generatorView();
        foreach (['#sidebar', '.wms-mobile-header', '.mobile-bottom-nav', '#toast-container', '.smart-generator-ui'] as $selector) {
            $this->assertStringContainsString('body.smart-generator-printing ' . $selector, $view);
        }
        $this->assertStringContainsString('.main-content > *:not(#main-content)', $view);
    }

    public function test_scroll_parent_is_unclipped_in_print_mode(): void
    {
        $view = $this->generatorView();
        $this->assertStringContainsString('.smart-generator-preview-viewport', $view);
        $this->assertStringContainsString('max-height: none !important', $view);
        $this->assertStringContainsString('overflow: visible !important', $view);
    }

    public function test_print_contract_is_a4_portrait_with_zero_browser_margin(): void
    {
        $view = $this->generatorView();
        $this->assertStringContainsString('size: A4 portrait', $view);
        $this->assertStringContainsString('margin: 0;', $view);
        $this->assertStringContainsString('width: 210mm !important', $view);
        $this->assertStringContainsString('min-height: 297mm !important', $view);
    }

    public function test_printable_document_flows_across_pages(): void
    {
        $view = $this->generatorView();
        $this->assertStringContainsString('page-break-inside: auto', $view);
        $this->assertStringContainsString('break-inside: auto', $view);
        $this->assertStringContainsString('display: table-header-group', $view);
    }

    public function test_long_table_cells_wrap_without_horizontal_overflow(): void
    {
        $view = $this->generatorView();
        $this->assertStringContainsString('overflow-wrap: anywhere', $view);
        $this->assertStringContainsString('word-break: normal', $view);
        $this->assertStringContainsString('#a4CanvasSheet table', $view);
    }

    public function test_print_waits_for_alpine_dom_fonts_and_images(): void
    {
        $view = $this->generatorView();
        $this->assertStringContainsString('waitForPrintableDocument', $view);
        $this->assertStringContainsString('this.$nextTick', $view);
        $this->assertStringContainsString('document.fonts.ready', $view);
        $this->assertStringContainsString('image.decode', $view);
        $this->assertStringContainsString('requestAnimationFrame', $view);
    }

    public function test_empty_document_is_blocked_with_indonesian_message(): void
    {
        $view = $this->generatorView();
        $this->assertStringContainsString("Dokumen belum siap untuk dicetak.", $view);
        $this->assertStringContainsString("Dokumen belum siap untuk diekspor.", $view);
        $this->assertStringContainsString("body.textContent.trim() === ''", $view);
    }

    public function test_print_and_export_prevent_double_clicks(): void
    {
        $view = $this->generatorView();
        $this->assertStringContainsString("renderState === 'printing'", $view);
        $this->assertStringContainsString("renderState === 'generating'", $view);
        $this->assertStringContainsString(':disabled="renderState ===', $view);
    }

    public function test_print_state_is_removed_after_afterprint(): void
    {
        $view = $this->generatorView();
        $this->assertStringContainsString("document.body.classList.add('smart-generator-printing')", $view);
        $this->assertStringContainsString("document.body.classList.remove('smart-generator-printing')", $view);
        $this->assertStringContainsString("afterprint", $view);
    }

    public function test_logo_signature_and_stamp_are_kept_in_print_region(): void
    {
        $view = $this->generatorView();
        foreach (['company.company_logo', 'company.signature', 'company.stamp'] as $binding) {
            $this->assertStringContainsString($binding, $view);
        }
        $this->assertStringContainsString('print-color-adjust: exact', $view);
    }

    public function test_live_labels_are_indonesian_and_have_no_placeholder_tokens(): void
    {
        $view = $this->generatorView();
        foreach (['Pratinjau', 'Cetak', 'Ekspor', 'Simpan Ke Riwayat', 'Batal'] as $label) {
            $this->assertStringContainsString($label, $view);
        }
        $this->assertStringNotContainsString('{{name}}', $view);
        $this->assertStringNotContainsString('[STUDENT_ID]', $view);
        $this->assertStringNotContainsString('${field}', $view);
    }

    public function test_mobile_chrome_is_excluded_without_changing_document_markup(): void
    {
        $view = $this->generatorView();
        $this->assertStringContainsString('.mobile-bottom-nav', $view);
        $this->assertStringContainsString('body.smart-generator-printing > .fixed', $view);
        $this->assertStringContainsString('data-printable-document="true"', $view);
    }

    public function test_server_invoice_template_is_a4_and_print_safe(): void
    {
        $view = $this->invoiceView();
        $this->assertStringContainsString('size: A4 portrait', $view);
        $this->assertStringContainsString("font-family: 'Helvetica', 'Arial', sans-serif", $view);
        $this->assertStringContainsString('class="items-table"', $view);
        $this->assertStringContainsString('class="signature-box"', $view);
    }

    public function test_server_receipt_template_contains_signature_and_indonesian_labels(): void
    {
        $view = $this->receiptView();
        $this->assertStringContainsString('KWITANSI PEMBAYARAN', $view);
        $this->assertStringContainsString('class="signature-box"', $view);
        $this->assertStringContainsString('class="footer-watermark"', $view);
    }

    public function test_export_controller_uses_same_prepared_data_for_pdf_generation(): void
    {
        $source = (string) file_get_contents(app_path('Http/Controllers/Finance/SmartGeneratorController.php'));
        $this->assertStringContainsString('$data = $this->prepareDocumentData($request);', $source);
        $this->assertStringContainsString("Pdf::loadView(\$viewName, ['data' => \$data])", $source);
        $this->assertStringContainsString("\$pdf->setPaper('A4', 'portrait')", $source);
    }

    public function test_history_and_email_reuse_the_same_document_presenter(): void
    {
        $source = (string) file_get_contents(app_path('Http/Controllers/Finance/SmartGeneratorController.php'));
        $this->assertGreaterThanOrEqual(3, substr_count($source, '$this->prepareDocumentData($request)'));
        $this->assertStringContainsString('sendDocumentEmail($payload)', $source);
    }

    public function test_smart_generator_routes_remain_finance_authorized(): void
    {
        $routes = Route::getRoutes();
        foreach (['finance.smart_generator.index', 'finance.smart_generator.pdf', 'finance.smart_generator.save'] as $name) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route, $name . ' route missing');
            $this->assertContains('role:ADMINISTRATOR,FINANCE', $route->middleware());
        }
    }

    public function test_student_invoice_presenter_ignores_client_supplied_identity(): void
    {
        $source = (string) file_get_contents(app_path('Http/Controllers/Finance/SmartGeneratorController.php'));
        $this->assertStringContainsString('prepareStudentInvoiceDocumentData', $source);
        $this->assertStringContainsString('$invoice = $this->invoiceService->getById($sourceId)', $source);
        $this->assertStringContainsString('$student = $studentRepo->findById($invoice[\'Student_ID\'])', $source);
    }

    public function test_document_presenter_rejects_invalid_source_invoice(): void
    {
        $source = (string) file_get_contents(app_path('Http/Controllers/Finance/SmartGeneratorController.php'));
        $this->assertStringContainsString('Source invoice wajib dipilih', $source);
        $this->assertStringContainsString('bukan invoice siswa yang valid', $source);
    }

    public function test_pdf_templates_escape_dynamic_text_and_use_safe_image_data(): void
    {
        $invoice = $this->invoiceView();
        $receipt = $this->receiptView();
        $this->assertStringContainsString('nl2br(e($data[\'client_address\']', $invoice);
        $this->assertStringContainsString('nl2br(e($data[\'payment_for\']', $receipt);
        $this->assertStringContainsString("'company_logo' => \$logoBase64", (string) file_get_contents(app_path('Http/Controllers/Finance/SmartGeneratorController.php')));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

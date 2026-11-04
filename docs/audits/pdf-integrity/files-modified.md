# File yang diubah/ditambahkan

Daftar ini hanya mencakup perubahan audit dan file tracked yang terlihat berubah. Finance ReportController sudah memiliki perbaikan lokal pengguna pada awal audit; perubahan itu dipertahankan. File untracked lama bukan hasil audit dan tidak disentuh.

## File tracked yang berubah

- [app/Http/Controllers/Academic/AnnouncementController.php](../../../app/Http/Controllers/Academic/AnnouncementController.php)
- [app/Http/Controllers/Academic/AssessmentController.php](../../../app/Http/Controllers/Academic/AssessmentController.php)
- [app/Http/Controllers/Academic/AssignmentController.php](../../../app/Http/Controllers/Academic/AssignmentController.php)
- [app/Http/Controllers/Academic/AttendanceController.php](../../../app/Http/Controllers/Academic/AttendanceController.php)
- [app/Http/Controllers/Academic/ScheduleController.php](../../../app/Http/Controllers/Academic/ScheduleController.php)
- [app/Http/Controllers/Academic/ScoreController.php](../../../app/Http/Controllers/Academic/ScoreController.php)
- [app/Http/Controllers/Academic/SubjectController.php](../../../app/Http/Controllers/Academic/SubjectController.php)
- [app/Http/Controllers/Academic/TeacherWorkspaceController.php](../../../app/Http/Controllers/Academic/TeacherWorkspaceController.php)
- [app/Http/Controllers/Core/ActivityController.php](../../../app/Http/Controllers/Core/ActivityController.php)
- [app/Http/Controllers/Core/AuditLogController.php](../../../app/Http/Controllers/Core/AuditLogController.php)
- [app/Http/Controllers/Core/DepartmentController.php](../../../app/Http/Controllers/Core/DepartmentController.php)
- [app/Http/Controllers/Core/EmployeeController.php](../../../app/Http/Controllers/Core/EmployeeController.php)
- [app/Http/Controllers/Core/NotificationController.php](../../../app/Http/Controllers/Core/NotificationController.php)
- [app/Http/Controllers/Core/PositionController.php](../../../app/Http/Controllers/Core/PositionController.php)
- [app/Http/Controllers/Core/StudentController.php](../../../app/Http/Controllers/Core/StudentController.php)
- [app/Http/Controllers/Core/TeacherController.php](../../../app/Http/Controllers/Core/TeacherController.php)
- [app/Http/Controllers/Finance/AccountController.php](../../../app/Http/Controllers/Finance/AccountController.php)
- [app/Http/Controllers/Finance/InvoiceController.php](../../../app/Http/Controllers/Finance/InvoiceController.php)
- [app/Http/Controllers/Finance/PaymentController.php](../../../app/Http/Controllers/Finance/PaymentController.php)
- [app/Http/Controllers/Finance/ReportController.php](../../../app/Http/Controllers/Finance/ReportController.php)
- [app/Http/Controllers/Finance/SmartGeneratorController.php](../../../app/Http/Controllers/Finance/SmartGeneratorController.php)
- [app/Http/Controllers/Finance/TransactionController.php](../../../app/Http/Controllers/Finance/TransactionController.php)
- [app/Http/Controllers/Hr/PayrollController.php](../../../app/Http/Controllers/Hr/PayrollController.php)
- [app/Repositories/GoogleSheets/DocumentRepository.php](../../../app/Repositories/GoogleSheets/DocumentRepository.php)
- [app/Repositories/GoogleSheets/OvertimeRepository.php](../../../app/Repositories/GoogleSheets/OvertimeRepository.php)
- [app/Services/Core/ActivityService.php](../../../app/Services/Core/ActivityService.php)
- [app/Services/Core/StudentService.php](../../../app/Services/Core/StudentService.php)
- [app/Services/Core/SystemSettingService.php](../../../app/Services/Core/SystemSettingService.php)
- [app/Services/Finance/FinanceReportService.php](../../../app/Services/Finance/FinanceReportService.php)
- [app/Services/HR/PayrollService.php](../../../app/Services/HR/PayrollService.php)
- [app/Traits/Exportable.php](../../../app/Traits/Exportable.php)
- [resources/views/academic/teacher/scores.blade.php](../../../resources/views/academic/teacher/scores.blade.php)
- [resources/views/attendance/qr/print.blade.php](../../../resources/views/attendance/qr/print.blade.php)
- [resources/views/finance/reports/cash_flow.blade.php](../../../resources/views/finance/reports/cash_flow.blade.php)
- [resources/views/finance/smart_generator.blade.php](../../../resources/views/finance/smart_generator.blade.php)
- [resources/views/pdf/academic_report.blade.php](../../../resources/views/pdf/academic_report.blade.php)
- [resources/views/pdf/components/footer.blade.php](../../../resources/views/pdf/components/footer.blade.php)
- [resources/views/pdf/generic_table.blade.php](../../../resources/views/pdf/generic_table.blade.php)
- [resources/views/pdf/official_payslip.blade.php](../../../resources/views/pdf/official_payslip.blade.php)
- [resources/views/pdf/print_layout.blade.php](../../../resources/views/pdf/print_layout.blade.php)
- [resources/views/pdf/report_layout.blade.php](../../../resources/views/pdf/report_layout.blade.php)
- [resources/views/pdf/smart_generator_invoice.blade.php](../../../resources/views/pdf/smart_generator_invoice.blade.php)
- [resources/views/pdf/smart_generator_kwitansi.blade.php](../../../resources/views/pdf/smart_generator_kwitansi.blade.php)
- [tests/Unit/FinanceHardeningIntegrityTest.php](../../../tests/Unit/FinanceHardeningIntegrityTest.php)
- [tests/Unit/HumanReadableReportingTest.php](../../../tests/Unit/HumanReadableReportingTest.php)
- [tests/Unit/SmartGeneratorStudentInvoiceIntegrityTest.php](../../../tests/Unit/SmartGeneratorStudentInvoiceIntegrityTest.php)

## File baru audit

- [tests/Feature/GlobalPdfIntegrityTest.php](../../../tests/Feature/GlobalPdfIntegrityTest.php)
- [tests/Support/InteractsWithPdf.php](../../../tests/Support/InteractsWithPdf.php)
- [tests/Support/PdfDatabaseFixture.php](../../../tests/Support/PdfDatabaseFixture.php)
- [tests/Support/pdf_inventory.php](../../../tests/Support/pdf_inventory.php)
- [tests/Support/pdf_text.py](../../../tests/Support/pdf_text.py)
- [tests/Support/pdf_render.py](../../../tests/Support/pdf_render.py)
- [tests/Support/pdf_audit_matrix.py](../../../tests/Support/pdf_audit_matrix.py)
- [docs/audits/pdf-integrity/REPORT.md](../../../docs/audits/pdf-integrity/REPORT.md)
- [docs/audits/pdf-integrity/all-export-download-routes.json](../../../docs/audits/pdf-integrity/all-export-download-routes.json)
- [docs/audits/pdf-integrity/inventory.md](../../../docs/audits/pdf-integrity/inventory.md)
- [docs/audits/pdf-integrity/matrix.csv](../../../docs/audits/pdf-integrity/matrix.csv)
- [docs/audits/pdf-integrity/matrix.json](../../../docs/audits/pdf-integrity/matrix.json)
- [docs/audits/pdf-integrity/production-diagnostics-read-only.sql](../../../docs/audits/pdf-integrity/production-diagnostics-read-only.sql)
- [docs/audits/pdf-integrity/routes.json](../../../docs/audits/pdf-integrity/routes.json)
- [docs/audits/pdf-integrity/templates.md](../../../docs/audits/pdf-integrity/templates.md)

## Artefak tambahan (dipertahankan)

- `tmp/pdf-integrity/sqlite` dan `mysql`: PDF aktual, teks, arsip fixture; MySQL juga render PNG dan manifest hash.
- `tmp/pdf-integrity/teacher-*`: HTML/screenshot/hasil interaksi browser fixture.
- `tmp/pdf-*.xml`, `tmp/pdf-*.log`, `tmp/pdf-php-lint.json`: hasil test, lint dan jejak awal yang gagal.
- `tmp/pdf-audit-python`: dependency lokal ekstraksi/render; bukan dependency aplikasi produksi.
- `tmp/pdf_teacher_ui_check.cjs`, `tmp/align_pdf_queries.py`, `tmp/remove_pdf_sample_defaults.py`: script pendukung audit; dua script transformasi bersifat sekali jalan, jangan dijalankan ulang sebagai setup.

Jumlah file tracked berubah: 46. Daftar file baru di atas belum termasuk direktori bukti tmp.

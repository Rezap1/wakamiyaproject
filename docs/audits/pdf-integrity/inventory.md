# Inventaris dan bukti PDF

57 alur/varian aktif (jenis bisnis dan konteks akses dihitung terpisah). Preview/print yang berbagi payload dicatat di routes.json, bukan dihitung sebagai jenis bisnis baru.

PASS fixture hanya berarti identitas/jumlah/angka pada fixture yang dicantumkan cocok di SQLite dan MySQL. Ini bukan sertifikasi semua variasi atau data produksi.

| Modul | PDF / alur | Route | Controller / service | Sumber | Filter | Status |
|---|---|---|---|---|---|---|
| Core | Daftar users | users.export-pdf | Core / UserController@exportPdf | MASTER_USER + MASTER_ROLE | search, role, status; export all/today/range/current_page; sort/orientation | PASS fixture |
| Core | Daftar departments | departments.export-pdf | Core / DepartmentController@exportPdf | MASTER_DEPARTMENT + MASTER_EMPLOYEE | search, status; export all/today/range/current_page; sort/orientation | PASS fixture |
| Core | Daftar positions | positions.export-pdf | Core / PositionController@exportPdf | MASTER_POSITION + MASTER_DEPARTMENT | search, department, status; export all/today/range/current_page; sort/orientation | PASS fixture |
| Core | Daftar employees | employees.export-pdf | Core / EmployeeController@exportPdf | MASTER_EMPLOYEE + MASTER_USER + department/position | search, department, position, status, date_from/to (Join_Date/Created_At); export all/today/range/current_page; sort/orientation | PASS fixture |
| Core | Daftar teachers | teachers.export-pdf | Core / TeacherController@exportPdf | MASTER_TEACHER + MASTER_USER + MASTER_EMPLOYEE | search, teaching, status; export all/today/range/current_page; sort/orientation | PASS fixture |
| Core | Daftar programs | programs.export-pdf | Core / ProgramController@exportPdf | MASTER_PROGRAM | search/status sesuai controller; export all/today/range/current_page; sort/orientation | PASS fixture |
| Core | Daftar students | students.export-pdf | Core / StudentController@exportPdf | MASTER_STUDENT + program/batch/class | search, program, batch, class, status, registration date; export all/today/range/current_page; sort/orientation | PASS fixture |
| Core | Daftar companies | companies.export-pdf | Core / CompanyController@exportPdf | MASTER_COMPANY | search/status sesuai controller; export all/today/range/current_page; sort/orientation | PASS fixture |
| Core | Daftar modules | modules.export-pdf | Core / ModuleController@exportPdf | MASTER_MODULE | search/status sesuai controller; export all/today/range/current_page; sort/orientation | PASS fixture |
| Finance | Daftar accounts | accounts.export-pdf | Finance / AccountController@exportPdf | MASTER_ACCOUNT | search/status sesuai controller; export all/today/range/current_page; sort/orientation | PASS fixture |
| Finance | Daftar transactions | transactions.export-pdf | Finance / TransactionController@exportPdf | FINANCE_TRANSACTION + account/source repositories | search/status sesuai controller; export all/today/range/current_page; sort/orientation | PASS fixture |
| Finance | Daftar invoices | invoices.export-pdf | Finance / InvoiceController@exportPdf | FINANCE_INVOICE + FINANCE_PAYMENT + payer | search/payer, type, status; export all/today/range/current_page; sort/orientation | PASS fixture |
| Finance | Daftar payments | payments.export-pdf | Finance / PaymentController@exportPdf | FINANCE_PAYMENT + invoice/payer | search/payer, payment date; export all/today/range/current_page; sort/orientation | PASS fixture |
| Hr | Daftar payrolls | payrolls.export-pdf | Hr / PayrollController@exportPdf | MASTER_PAYROLL + MASTER_EMPLOYEE | search/employee, date, role scope; export all/today/range/current_page; sort/orientation | PASS fixture |
| Hr | Daftar hr.leaves | hr.leaves.export-pdf | Hr / LeaveController@exportPdf | MASTER_LEAVE + MASTER_EMPLOYEE | search/status sesuai controller; export all/today/range/current_page; sort/orientation | PASS fixture |
| Hr | Daftar hr.overtimes | hr.overtimes.export-pdf | Hr / OvertimeController@exportPdf | MASTER_OVERTIME + MASTER_EMPLOYEE | search/status sesuai controller; export all/today/range/current_page; sort/orientation | PASS fixture |
| Core | Daftar audit | audit.export-pdf | Core / AuditLogController@exportPdf | MASTER_AUDIT_LOG + MASTER_USER | all history; current_page = latest 100; export all/today/range/current_page; sort/orientation | PASS fixture |
| Academic | Daftar batches | batches.export-pdf | Academic / BatchController@exportPdf | MASTER_BATCH + MASTER_PROGRAM | search/status sesuai controller; export all/today/range/current_page; sort/orientation | PASS fixture |
| Academic | Daftar classes | classes.export-pdf | Academic / ClassController@exportPdf | MASTER_CLASS + batch/program/teacher | search/status sesuai controller; export all/today/range/current_page; sort/orientation | PASS fixture |
| Academic | Daftar assessments | assessments.export-pdf | Academic / AssessmentController@exportPdf | ASSESSMENTS + class/subject/teacher | search/status sesuai controller; export all/today/range/current_page; sort/orientation | PASS fixture |
| Academic | Daftar scores | scores.export-pdf | Academic / ScoreController@exportPdf | MASTER_SCORE + student/assessment/assignment | search/name, category, business assessment date; export all/today/range/current_page; sort/orientation | PASS fixture |
| Academic | Daftar subjects | subjects.export-pdf | Academic / SubjectController@exportPdf | MASTER_SUBJECT + MASTER_PROGRAM | search, status; export all/today/range/current_page; sort/orientation | PASS fixture |
| Academic | Daftar assignments | assignments.export-pdf | Academic / AssignmentController@exportPdf | MASTER_ASSIGNMENT + class/subject/teacher | search/status sesuai controller; export all/today/range/current_page; sort/orientation | PASS fixture |
| Core | Daftar notifications | notifications.export-pdf | Core / NotificationController@exportPdf | MASTER_NOTIFICATION (current user) | search/status sesuai controller; export all/today/range/current_page; sort/orientation | PASS fixture |
| Core | Daftar activity | activity.export-pdf | Core / ActivityController@exportPdf | MASTER_AUDIT_LOG (role/user scope) | keyword, module, role/user; export all/today/range/current_page; sort/orientation | PASS fixture |
| Academic | Daftar announcements | announcements.export-pdf | Academic / AnnouncementController@exportPdf | MASTER_ANNOUNCEMENT (managed visibility) | search/status sesuai controller; export all/today/range/current_page; sort/orientation | PASS fixture |
| Academic | Jadwal | schedules.export-pdf | Academic / ScheduleController@exportPdf | MASTER_SCHEDULE + class/subject/teacher | search; creation date (recurring schedule); unpaginated | PASS fixture |
| Finance | Invoice resmi | invoices.pdf | Finance / InvoiceController@downloadInvoicePdf | FINANCE_INVOICE + FINANCE_PAYMENT + student | INV-AUDIT | PASS fixture |
| Finance | Bukti pembayaran | payments.receipt | Finance / PaymentController@downloadReceiptPdf | FINANCE_PAYMENT + invoice + receiving account | PAY-AUDIT | PASS fixture |
| Hr | Slip gaji | payrolls.pdf | Hr / PayrollController@downloadPayslipPdf | MASTER_PAYROLL + MASTER_EMPLOYEE | PRL-AUDIT; HR/Finance scope | PASS fixture |
| Hr | Persetujuan cuti | leaves.pdf |  / Hr / LeaveController@downloadLeavePdf | MASTER_LEAVE + employee | LEV-AUDIT | PASS fixture |
| Hr | Persetujuan lembur | overtimes.pdf |  / Hr / OvertimeController@downloadOvertimePdf | MASTER_OVERTIME + employee | OVT-AUDIT | PASS fixture |
| Student | Invoice siswa | student.billing.invoice-pdf | Finance / StudentBillingController@downloadInvoicePdf | FINANCE_INVOICE + payment + authenticated student | own invoice INV-AUDIT | PASS fixture |
| Student | Bukti pembayaran siswa | student.billing.payment-receipt | Finance / PaymentController@downloadReceiptPdf | FINANCE_PAYMENT + authenticated student | own payment PAY-AUDIT | PASS fixture |
| Academic | Presensi Academic | attendances.export-pdf | Academic / AttendanceController@exportPdf | MASTER_ATTENDANCE + class roster | date, class, status, search | PASS fixture |
| Teacher | Presensi Teacher | teacher.workspace.reports.attendances-pdf | Academic / TeacherWorkspaceController@exportAttendancesPdf | MASTER_ATTENDANCE + authorized managed roster | teacher class/date scope | PASS fixture |
| Student | Presensi Student | student.export-attendances-pdf | Academic / StudentWorkspaceController@exportAttendancesPdf | MASTER_ATTENDANCE + authenticated student | own student | PASS fixture |
| Academic | Presensi harian | attendance.reports.pdf | Academic / AttendanceReportController@pdf | MASTER_ATTENDANCE + class/student roster | report_type=harian; class; student optional; business dates | PASS fixture |
| Academic | Presensi mingguan | attendance.reports.pdf | Academic / AttendanceReportController@pdf | MASTER_ATTENDANCE + class/student roster | report_type=mingguan; class; student optional; business dates | PASS fixture |
| Academic | Presensi bulanan | attendance.reports.pdf | Academic / AttendanceReportController@pdf | MASTER_ATTENDANCE + class/student roster | report_type=bulanan; class; student optional; business dates | PASS fixture |
| Academic | Presensi rentang | attendance.reports.pdf | Academic / AttendanceReportController@pdf | MASTER_ATTENDANCE + class/student roster | report_type=rentang; class; student optional; business dates | PASS fixture |
| Teacher | Nilai Teacher student | teacher.workspace.reports.scores-pdf | Academic / TeacherWorkspaceController@exportScoresPdf | MASTER_SCORE + managed student/class + subject | mode=student; entity; all_periods/date; subject/category | PASS fixture |
| Teacher | Nilai Teacher class | teacher.workspace.reports.scores-pdf | Academic / TeacherWorkspaceController@exportScoresPdf | MASTER_SCORE + managed student/class + subject | mode=class; entity; all_periods/date; subject/category | PASS fixture |
| Student | Nilai Student | student.export-scores-pdf | Academic / StudentWorkspaceController@exportScoresPdf | MASTER_SCORE + authenticated student | own identity | PASS fixture |
| Finance | Arus kas | reports.finance.export-pdf | Finance / ReportController@exportPdf | FINANCE_TRANSACTION + MASTER_ACCOUNT | business dates, account ID/code, category; independent opening/period sums | PASS fixture |
| Finance | Piutang | reports.finance.export-pdf | Finance / ReportController@exportPdf | FINANCE_INVOICE + verified FINANCE_PAYMENT + payer | report_type=outstanding; type/student/company; creation date | PASS fixture |
| Academic | Alumni pilihan | alumni.export-selected-pdf | Academic / AlumniController@exportSelectedPdf | MASTER_ALUMNI + program/batch/class | selected alumni_ids, scoped records | PASS fixture |
| Finance | Smart manual invoice | finance.smart_generator.pdf | Finance / SmartGeneratorController@exportPdf | Request manual | doc_type/source_type/source_id; manual line arithmetic or canonical invoice | PASS fixture |
| Finance | Smart manual kwitansi | finance.smart_generator.pdf | Finance / SmartGeneratorController@exportPdf | Request manual | doc_type/source_type/source_id; manual line arithmetic or canonical invoice | PASS fixture |
| Finance | Smart student invoice | finance.smart_generator.pdf | Finance / SmartGeneratorController@exportPdf | Canonical InvoiceService + verified payments + student | doc_type/source_type/source_id; manual line arithmetic or canonical invoice | PASS fixture |
| Finance | Smart student kwitansi | finance.smart_generator.pdf | Finance / SmartGeneratorController@exportPdf | Canonical InvoiceService + verified payments + student | doc_type/source_type/source_id; manual line arithmetic or canonical invoice | PASS fixture |
| Core | QR presensi permanen | attendance.qr.pdf | Core / PermanentQrController@downloadPdf | MASTER_PERMANENT_QR + lifecycle/window configuration | authorized QR_ID; validity window | PASS fixture |
| Automation | Arsip otomatis Certificate | service:Certificate | StudentService::updateStudent → DocumentAutomationService | MASTER_STUDENT + MASTER_PROGRAM | persisted business identity; version/hash; private storage | PASS fixture |
| Automation | Arsip otomatis AcademicReport | service:AcademicReport | StudentService::updateStudent → DocumentAutomationService | MASTER_STUDENT + MASTER_SCORE + assessment/assignment | persisted business identity; version/hash; private storage | PASS fixture |
| Automation | Arsip otomatis Payroll | service:Payroll | PayrollService::updateStatus → DocumentAutomationService | MASTER_PAYROLL + MASTER_EMPLOYEE | persisted business identity; version/hash; private storage | PASS fixture |
| Automation | Lampiran email invoice | finance.smart_generator.send_email | Finance / SmartGeneratorController@sendEmail → EmailDeliveryService | SmartGenerator payload → EmailDeliveryService | Mail::raw intercepted, no email sent | PASS fixture |
| Automation | Lampiran email kwitansi | finance.smart_generator.send_email | Finance / SmartGeneratorController@sendEmail → EmailDeliveryService | SmartGenerator payload → EmailDeliveryService | Mail::raw intercepted, no email sent | PASS fixture |

## Rekonsiliasi per alur

Jumlah pada dokumen individual berarti satu entitas/dokumen. Daftar menggunakan metadata Total Data; detail juga diperiksa lewat identitas dan nominal pada teks PDF sebenarnya. Nominal N/A berarti laporan tidak menampilkan total uang.

| PDF | Record sumber | Record PDF | Angka sumber | Angka PDF | Bukti PDF / teks MySQL |
|---|---:|---:|---|---|---|
| Daftar users | 3 | 3 | N/A | N/A | [PDF](../../../tmp/pdf-integrity/mysql/users.pdf) / [teks](../../../tmp/pdf-integrity/mysql/users.txt) |
| Daftar departments | 1 | 1 | N/A | N/A | [PDF](../../../tmp/pdf-integrity/mysql/departments.pdf) / [teks](../../../tmp/pdf-integrity/mysql/departments.txt) |
| Daftar positions | 1 | 1 | N/A | N/A | [PDF](../../../tmp/pdf-integrity/mysql/positions.pdf) / [teks](../../../tmp/pdf-integrity/mysql/positions.txt) |
| Daftar employees | 1 | 1 | N/A | N/A | [PDF](../../../tmp/pdf-integrity/mysql/employees.pdf) / [teks](../../../tmp/pdf-integrity/mysql/employees.txt) |
| Daftar teachers | 1 | 1 | N/A | N/A | [PDF](../../../tmp/pdf-integrity/mysql/teachers.pdf) / [teks](../../../tmp/pdf-integrity/mysql/teachers.txt) |
| Daftar programs | 1 | 1 | N/A | N/A | [PDF](../../../tmp/pdf-integrity/mysql/programs.pdf) / [teks](../../../tmp/pdf-integrity/mysql/programs.txt) |
| Daftar students | 1 | 1 | N/A | N/A | [PDF](../../../tmp/pdf-integrity/mysql/students.pdf) / [teks](../../../tmp/pdf-integrity/mysql/students.txt) |
| Daftar companies | 1 | 1 | N/A | N/A | [PDF](../../../tmp/pdf-integrity/mysql/companies.pdf) / [teks](../../../tmp/pdf-integrity/mysql/companies.txt) |
| Daftar modules | 1 | 1 | N/A | N/A | [PDF](../../../tmp/pdf-integrity/mysql/modules.pdf) / [teks](../../../tmp/pdf-integrity/mysql/modules.txt) |
| Daftar accounts | 1 | 1 | N/A | N/A | [PDF](../../../tmp/pdf-integrity/mysql/accounts.pdf) / [teks](../../../tmp/pdf-integrity/mysql/accounts.txt) |
| Daftar transactions | 4 | 4 | Nominal baris 100.000; 500.000; 1.250.000; 1.500.000 | Nominal baris 100.000; 500.000; 1.250.000; 1.500.000 | [PDF](../../../tmp/pdf-integrity/mysql/transactions.pdf) / [teks](../../../tmp/pdf-integrity/mysql/transactions.txt) |
| Daftar invoices | 1 | 1 | Tagihan 2.000.000; sisa 1.500.000 | Tagihan 2.000.000; sisa 1.500.000 | [PDF](../../../tmp/pdf-integrity/mysql/invoices.pdf) / [teks](../../../tmp/pdf-integrity/mysql/invoices.txt) |
| Daftar payments | 1 | 1 | 500.000 | 500.000 | [PDF](../../../tmp/pdf-integrity/mysql/payments.pdf) / [teks](../../../tmp/pdf-integrity/mysql/payments.txt) |
| Daftar payrolls | 1 | 1 | Pokok 3.000.000; potongan 100.000; net 3.100.000 | Pokok 3.000.000; potongan 100.000; net 3.100.000 | [PDF](../../../tmp/pdf-integrity/mysql/payrolls.pdf) / [teks](../../../tmp/pdf-integrity/mysql/payrolls.txt) |
| Daftar hr.leaves | 1 | 1 | N/A | N/A | [PDF](../../../tmp/pdf-integrity/mysql/hr.leaves.pdf) / [teks](../../../tmp/pdf-integrity/mysql/hr.leaves.txt) |
| Daftar hr.overtimes | 1 | 1 | N/A | N/A | [PDF](../../../tmp/pdf-integrity/mysql/hr.overtimes.pdf) / [teks](../../../tmp/pdf-integrity/mysql/hr.overtimes.txt) |
| Daftar audit | 1 | 1 | N/A | N/A | [PDF](../../../tmp/pdf-integrity/mysql/audit.pdf) / [teks](../../../tmp/pdf-integrity/mysql/audit.txt) |
| Daftar batches | 1 | 1 | N/A | N/A | [PDF](../../../tmp/pdf-integrity/mysql/batches.pdf) / [teks](../../../tmp/pdf-integrity/mysql/batches.txt) |
| Daftar classes | 1 | 1 | N/A | N/A | [PDF](../../../tmp/pdf-integrity/mysql/classes.pdf) / [teks](../../../tmp/pdf-integrity/mysql/classes.txt) |
| Daftar assessments | 1 | 1 | N/A | N/A | [PDF](../../../tmp/pdf-integrity/mysql/assessments.pdf) / [teks](../../../tmp/pdf-integrity/mysql/assessments.txt) |
| Daftar scores | 1 | 1 | Nilai 88,5 | Nilai 88,5 | [PDF](../../../tmp/pdf-integrity/mysql/scores.pdf) / [teks](../../../tmp/pdf-integrity/mysql/scores.txt) |
| Daftar subjects | 1 | 1 | N/A | N/A | [PDF](../../../tmp/pdf-integrity/mysql/subjects.pdf) / [teks](../../../tmp/pdf-integrity/mysql/subjects.txt) |
| Daftar assignments | 1 | 1 | N/A | N/A | [PDF](../../../tmp/pdf-integrity/mysql/assignments.pdf) / [teks](../../../tmp/pdf-integrity/mysql/assignments.txt) |
| Daftar notifications | 1 | 1 | N/A | N/A | [PDF](../../../tmp/pdf-integrity/mysql/notifications.pdf) / [teks](../../../tmp/pdf-integrity/mysql/notifications.txt) |
| Daftar activity | 1 | 1 | N/A | N/A | [PDF](../../../tmp/pdf-integrity/mysql/activity.pdf) / [teks](../../../tmp/pdf-integrity/mysql/activity.txt) |
| Daftar announcements | 1 | 1 | N/A | N/A | [PDF](../../../tmp/pdf-integrity/mysql/announcements.pdf) / [teks](../../../tmp/pdf-integrity/mysql/announcements.txt) |
| Jadwal | 1 | 1 | N/A | N/A | [PDF](../../../tmp/pdf-integrity/mysql/schedules.pdf) / [teks](../../../tmp/pdf-integrity/mysql/schedules.txt) |
| Invoice resmi | 1 | 1 | 2.000.000 / paid 500.000 / remaining 1.500.000 | 2.000.000 / paid 500.000 / remaining 1.500.000 | [PDF](../../../tmp/pdf-integrity/mysql/invoices-pdf-detail.pdf) / [teks](../../../tmp/pdf-integrity/mysql/invoices-pdf-detail.txt) |
| Bukti pembayaran | 1 | 1 | 500.000 | 500.000 | [PDF](../../../tmp/pdf-integrity/mysql/payments-receipt-detail.pdf) / [teks](../../../tmp/pdf-integrity/mysql/payments-receipt-detail.txt) |
| Slip gaji | 1 | 1 | 3.000.000 + 200.000 - 100.000 = 3.100.000 | 3.000.000 + 200.000 - 100.000 = 3.100.000 | [PDF](../../../tmp/pdf-integrity/mysql/payrolls-pdf-detail.pdf) / [teks](../../../tmp/pdf-integrity/mysql/payrolls-pdf-detail.txt) |
| Persetujuan cuti | 1 | 1 | Durasi 2 hari | Durasi 2 hari | [PDF](../../../tmp/pdf-integrity/mysql/leaves-pdf-detail.pdf) / [teks](../../../tmp/pdf-integrity/mysql/leaves-pdf-detail.txt) |
| Persetujuan lembur | 1 | 1 | 2 jam x 25.000 = 50.000 | 2 jam x 25.000 = 50.000 | [PDF](../../../tmp/pdf-integrity/mysql/overtimes-pdf-detail.pdf) / [teks](../../../tmp/pdf-integrity/mysql/overtimes-pdf-detail.txt) |
| Invoice siswa | 1 | 1 | 2.000.000 / remaining 1.500.000 | 2.000.000 / remaining 1.500.000 | [PDF](../../../tmp/pdf-integrity/mysql/student-billing-invoice-pdf-detail.pdf) / [teks](../../../tmp/pdf-integrity/mysql/student-billing-invoice-pdf-detail.txt) |
| Bukti pembayaran siswa | 1 | 1 | 500.000 | 500.000 | [PDF](../../../tmp/pdf-integrity/mysql/student-billing-payment-receipt-detail.pdf) / [teks](../../../tmp/pdf-integrity/mysql/student-billing-payment-receipt-detail.txt) |
| Presensi Academic | 1 | 1 | Hadir 1 | Hadir 1 | [PDF](../../../tmp/pdf-integrity/mysql/attendances-export-pdf-detail.pdf) / [teks](../../../tmp/pdf-integrity/mysql/attendances-export-pdf-detail.txt) |
| Presensi Teacher | 1 | 1 | Hadir 1 | Hadir 1 | [PDF](../../../tmp/pdf-integrity/mysql/teacher-workspace-reports-attendances-pdf-detail.pdf) / [teks](../../../tmp/pdf-integrity/mysql/teacher-workspace-reports-attendances-pdf-detail.txt) |
| Presensi Student | 1 | 1 | Hadir 1 | Hadir 1 | [PDF](../../../tmp/pdf-integrity/mysql/student-export-attendances-pdf-detail.pdf) / [teks](../../../tmp/pdf-integrity/mysql/student-export-attendances-pdf-detail.txt) |
| Presensi harian | 1 | 1 | Hadir 1 | Hadir 1 | [PDF](../../../tmp/pdf-integrity/mysql/attendance-reports-pdf-harian.pdf) / [teks](../../../tmp/pdf-integrity/mysql/attendance-reports-pdf-harian.txt) |
| Presensi mingguan | 1 | 1 | Hadir 1 | Hadir 1 | [PDF](../../../tmp/pdf-integrity/mysql/attendance-reports-pdf-mingguan.pdf) / [teks](../../../tmp/pdf-integrity/mysql/attendance-reports-pdf-mingguan.txt) |
| Presensi bulanan | 1 | 1 | Hadir 1 | Hadir 1 | [PDF](../../../tmp/pdf-integrity/mysql/attendance-reports-pdf-bulanan.pdf) / [teks](../../../tmp/pdf-integrity/mysql/attendance-reports-pdf-bulanan.txt) |
| Presensi rentang | 1 | 1 | Hadir 1 | Hadir 1 | [PDF](../../../tmp/pdf-integrity/mysql/attendance-reports-pdf-rentang.pdf) / [teks](../../../tmp/pdf-integrity/mysql/attendance-reports-pdf-rentang.txt) |
| Nilai Teacher student | 1 | 1 | Nilai 88,5 | Nilai 88,5 | [PDF](../../../tmp/pdf-integrity/mysql/teacher-student.pdf) / [teks](../../../tmp/pdf-integrity/mysql/teacher-student.txt) |
| Nilai Teacher class | 1 | 1 | Nilai 88,5 | Nilai 88,5 | [PDF](../../../tmp/pdf-integrity/mysql/teacher-class.pdf) / [teks](../../../tmp/pdf-integrity/mysql/teacher-class.txt) |
| Nilai Student | 1 | 1 | Nilai 88,5 | Nilai 88,5 | [PDF](../../../tmp/pdf-integrity/mysql/student-scores.pdf) / [teks](../../../tmp/pdf-integrity/mysql/student-scores.txt) |
| Arus kas | 3 | 3 | Opening 100.000; income 1.750.000; expense 1.500.000; net 250.000; closing 350.000 | Opening 100.000; income 1.750.000; expense 1.500.000; net 250.000; closing 350.000 | [PDF](../../../tmp/pdf-integrity/mysql/cash-flow.pdf) / [teks](../../../tmp/pdf-integrity/mysql/cash-flow.txt) |
| Piutang | 1 | 1 | 2.000.000 - 500.000 = 1.500.000 | 2.000.000 - 500.000 = 1.500.000 | [PDF](../../../tmp/pdf-integrity/mysql/outstanding.pdf) / [teks](../../../tmp/pdf-integrity/mysql/outstanding.txt) |
| Alumni pilihan | 1 | 1 | N/A | N/A | [PDF](../../../tmp/pdf-integrity/mysql/alumni-selected.pdf) / [teks](../../../tmp/pdf-integrity/mysql/alumni-selected.txt) |
| Smart manual invoice | 1 | 1 | 500.000 | 500.000 | [PDF](../../../tmp/pdf-integrity/mysql/smart-manual-invoice.pdf) / [teks](../../../tmp/pdf-integrity/mysql/smart-manual-invoice.txt) |
| Smart manual kwitansi | 1 | 1 | 500.000 | 500.000 | [PDF](../../../tmp/pdf-integrity/mysql/smart-manual-kwitansi.pdf) / [teks](../../../tmp/pdf-integrity/mysql/smart-manual-kwitansi.txt) |
| Smart student invoice | 1 | 1 | 2.000.000 | 2.000.000 | [PDF](../../../tmp/pdf-integrity/mysql/smart-student-invoice.pdf) / [teks](../../../tmp/pdf-integrity/mysql/smart-student-invoice.txt) |
| Smart student kwitansi | 1 | 1 | 500.000 | 500.000 | [PDF](../../../tmp/pdf-integrity/mysql/smart-student-kwitansi.pdf) / [teks](../../../tmp/pdf-integrity/mysql/smart-student-kwitansi.txt) |
| QR presensi permanen | 1 | 1 | N/A | N/A | [PDF](../../../tmp/pdf-integrity/mysql/permanent-qr.pdf) / [teks](../../../tmp/pdf-integrity/mysql/permanent-qr.txt) |
| Arsip otomatis Certificate | 1 | 1 | N/A | N/A | [PDF](../../../tmp/pdf-integrity/mysql/automatic-Certificate.pdf) / [teks](../../../tmp/pdf-integrity/mysql/automatic-Certificate.txt) |
| Arsip otomatis AcademicReport | 1 | 1 | Nilai 88,5 | Nilai 88,5 | [PDF](../../../tmp/pdf-integrity/mysql/automatic-AcademicReport.pdf) / [teks](../../../tmp/pdf-integrity/mysql/automatic-AcademicReport.txt) |
| Arsip otomatis Payroll | 1 | 1 | Net 3.100.000 | Net 3.100.000 | [PDF](../../../tmp/pdf-integrity/mysql/automatic-Payroll.pdf) / [teks](../../../tmp/pdf-integrity/mysql/automatic-Payroll.txt) |
| Lampiran email invoice | 1 | 1 | 500.000 | 500.000 | [PDF](../../../tmp/pdf-integrity/mysql/email-invoice.pdf) / [teks](../../../tmp/pdf-integrity/mysql/email-invoice.txt) |
| Lampiran email kwitansi | 1 | 1 | 500.000 | 500.000 | [PDF](../../../tmp/pdf-integrity/mysql/email-kwitansi.pdf) / [teks](../../../tmp/pdf-integrity/mysql/email-kwitansi.txt) |

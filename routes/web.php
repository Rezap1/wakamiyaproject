<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Core\AuthController;
use App\Http\Controllers\Core\DashboardController;
use App\Http\Controllers\Core\UserController;
use App\Http\Controllers\Core\DepartmentController;
use App\Http\Controllers\Core\DeveloperPanelController;
use App\Http\Controllers\Core\PositionController;
use App\Http\Controllers\Core\EmployeeController;
use App\Http\Controllers\Core\TeacherController;
use App\Http\Controllers\Core\ProgramController;
use App\Http\Controllers\Core\BatchController;
use App\Http\Controllers\Core\ClassController;
use App\Http\Controllers\Core\StudentController;
use App\Http\Controllers\Core\CompanyController;
use App\Http\Controllers\Core\ModuleController;
use App\Http\Controllers\Core\PermissionController;
use App\Http\Controllers\Core\DocumentController;
use App\Http\Controllers\Core\StudentPortalController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/verify-receipt/{id}', [\App\Http\Controllers\Finance\PaymentController::class, 'verifyReceiptPublic'])->middleware('signed')->name('payments.verify-receipt-public');
Route::get('/verify-invoice/{id}', [\App\Http\Controllers\Finance\InvoiceController::class, 'verifyInvoicePublic'])->middleware('signed')->name('invoices.verify-public');
Route::get('/verify-payslip/{id}', [\App\Http\Controllers\Hr\PayrollController::class, 'verifyPayslipPublic'])->middleware('signed')->name('payrolls.verify-public');
Route::get('/verify-leave/{id}', [\App\Http\Controllers\Hr\LeaveController::class, 'verifyLeavePublic'])->middleware('signed')->name('leaves.verify-public');
Route::get('/verify-overtime/{id}', [\App\Http\Controllers\Hr\OvertimeController::class, 'verifyOvertimePublic'])->middleware('signed')->name('overtimes.verify-public');

// Requires authentication
Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/personal-payroll', [\App\Http\Controllers\Dashboard\PersonalPayrollController::class, 'index'])->name('dashboard.personal-payroll');
    Route::get('/dashboard/personal-payroll/{id}/proof', [\App\Http\Controllers\Dashboard\PersonalPayrollController::class, 'downloadProof'])->name('dashboard.personal-payroll.proof');

    // Role-specific Dashboards
    Route::middleware('role:HR,ADMINISTRATOR')->get('/dashboard/hr', [\App\Http\Controllers\Core\HrDashboardController::class, 'index'])->name('dashboard.hr');
    Route::middleware('role:ACADEMIC,ADMINISTRATOR')->get('/dashboard/academic', [\App\Http\Controllers\Core\AcademicDashboardController::class, 'index'])->name('dashboard.academic');
    Route::middleware('role:MARKETING,ADMINISTRATOR')->get('/dashboard/marketing', [\App\Http\Controllers\Core\MarketingDashboardController::class, 'index'])->name('dashboard.marketing');
    Route::middleware('role:FINANCE,ADMINISTRATOR')->get('/dashboard/finance', [\App\Http\Controllers\Core\FinanceDashboardController::class, 'index'])->name('dashboard.finance');
    Route::middleware('role:DIRECTOR,ADMINISTRATOR')->get('/dashboard/director', [\App\Http\Controllers\Core\DirectorDashboardController::class, 'index'])->name('dashboard.director');
    Route::middleware('role:TEACHER')->get('/dashboard/teacher', [\App\Http\Controllers\Core\TeacherDashboardController::class, 'index'])->name('dashboard.teacher');
    Route::middleware('role:STUDENT')->get('/dashboard/student', [\App\Http\Controllers\Core\StudentDashboardController::class, 'index'])->name('dashboard.student');
    Route::middleware('role:ADMINISTRATOR')->get('/dashboard/administrator', [\App\Http\Controllers\Core\DashboardController::class, 'index'])->name('dashboard.administrator');

    // Developer Panel
    Route::middleware('role:ADMINISTRATOR')->get('/developer-panel', [DeveloperPanelController::class, 'index'])->name('developer-panel.index');

    // User Management
    Route::prefix('users')->name('users.')->middleware('role:ADMINISTRATOR')->group(function () {
        Route::get('/preview-pdf', [UserController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [UserController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [UserController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [UserController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [UserController::class, 'print'])->name('print');
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/create', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [UserController::class, 'edit'])->name('edit');
        Route::put('/{id}', [UserController::class, 'update'])->name('update');
        Route::delete('/{id}', [UserController::class, 'destroy'])->name('destroy');
    });

    // Department Management
    Route::prefix('departments')->name('departments.')->middleware('role:ADMINISTRATOR,HR')->group(function () {
        Route::get('/preview-pdf', [DepartmentController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [DepartmentController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [DepartmentController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [DepartmentController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [DepartmentController::class, 'print'])->name('print');
        Route::get('/', [DepartmentController::class, 'index'])->name('index');
        Route::get('/create', [DepartmentController::class, 'create'])->name('create');
        Route::post('/', [DepartmentController::class, 'store'])->name('store');
        Route::get('/{id}', [DepartmentController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [DepartmentController::class, 'edit'])->name('edit');
        Route::put('/{id}', [DepartmentController::class, 'update'])->name('update');
        Route::delete('/{id}', [DepartmentController::class, 'destroy'])->name('destroy');
    });

    // Position Management
    Route::prefix('positions')->name('positions.')->middleware('role:ADMINISTRATOR,HR')->group(function () {
        Route::get('/preview-pdf', [PositionController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [PositionController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [PositionController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [PositionController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [PositionController::class, 'print'])->name('print');
        Route::get('/', [PositionController::class, 'index'])->name('index');
        Route::get('/create', [PositionController::class, 'create'])->name('create');
        Route::post('/', [PositionController::class, 'store'])->name('store');
        Route::get('/{id}', [PositionController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [PositionController::class, 'edit'])->name('edit');
        Route::put('/{id}', [PositionController::class, 'update'])->name('update');
        Route::delete('/{id}', [PositionController::class, 'destroy'])->name('destroy');
    });

    // Employee Management
    Route::prefix('employees')->name('employees.')->middleware('role:ADMINISTRATOR,HR')->group(function () {
        Route::get('/preview-pdf', [EmployeeController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [EmployeeController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [EmployeeController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [EmployeeController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [EmployeeController::class, 'print'])->name('print');
        Route::get('/', [EmployeeController::class, 'index'])->name('index');
        Route::get('/create', [EmployeeController::class, 'create'])->name('create');
        Route::post('/', [EmployeeController::class, 'store'])->name('store');
        Route::get('/{id}', [EmployeeController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [EmployeeController::class, 'edit'])->name('edit');
        Route::put('/{id}', [EmployeeController::class, 'update'])->name('update');
        Route::delete('/{id}', [EmployeeController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/send-email', [EmployeeController::class, 'sendEmail'])->name('send-email');
    });

    // Teacher Management
    Route::prefix('teachers')->name('teachers.')->middleware('role:ADMINISTRATOR,ACADEMIC,HR')->group(function () {
        Route::get('/preview-pdf', [TeacherController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [TeacherController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [TeacherController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [TeacherController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [TeacherController::class, 'print'])->name('print');
        Route::get('/', [TeacherController::class, 'index'])->name('index');
        Route::get('/create', [TeacherController::class, 'create'])->name('create');
        Route::post('/', [TeacherController::class, 'store'])->name('store');
        Route::get('/{id}', [TeacherController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [TeacherController::class, 'edit'])->name('edit');
        Route::put('/{id}', [TeacherController::class, 'update'])->name('update');
        Route::delete('/{id}', [TeacherController::class, 'destroy'])->name('destroy');
    });

    // Program Management
    Route::prefix('programs')->name('programs.')->middleware('role:ADMINISTRATOR,ACADEMIC')->group(function () {
        Route::get('/preview-pdf', [ProgramController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [ProgramController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [ProgramController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [ProgramController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [ProgramController::class, 'print'])->name('print');
        Route::get('/', [ProgramController::class, 'index'])->name('index');
        Route::get('/create', [ProgramController::class, 'create'])->name('create');
        Route::post('/', [ProgramController::class, 'store'])->name('store');
        Route::get('/{id}', [ProgramController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [ProgramController::class, 'edit'])->name('edit');
        Route::put('/{id}', [ProgramController::class, 'update'])->name('update');
        Route::delete('/{id}', [ProgramController::class, 'destroy'])->name('destroy');
    });

    // Student Management
    Route::prefix('students')->name('students.')->middleware('role:ADMINISTRATOR,ACADEMIC')->group(function () {
        Route::get('/preview-pdf', [StudentController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [StudentController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [StudentController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [StudentController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [StudentController::class, 'print'])->name('print');
        Route::get('/', [StudentController::class, 'index'])->name('index');
        Route::get('/create', [StudentController::class, 'create'])->name('create');
        Route::post('/', [StudentController::class, 'store'])->name('store');
        Route::get('/{id}', [StudentController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [StudentController::class, 'edit'])->name('edit');
        Route::post('/{id}/graduate', [StudentController::class, 'graduate'])->name('graduate');
        Route::put('/{id}', [StudentController::class, 'update'])->name('update');
        Route::delete('/{id}', [StudentController::class, 'destroy'])->name('destroy');
    });

    // Alumni Management
    Route::prefix('academic/alumni')->name('alumni.')->middleware('role:ADMINISTRATOR,ACADEMIC')->group(function () {
        Route::get('/', [\App\Http\Controllers\Academic\AlumniController::class, 'index'])->name('index');
        Route::get('/{id}', [\App\Http\Controllers\Academic\AlumniController::class, 'show'])->name('show');
    });

    // Company Management
    Route::prefix('companies')->name('companies.')->middleware('role:ADMINISTRATOR,MARKETING')->group(function () {
        Route::get('/preview-pdf', [CompanyController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [CompanyController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [CompanyController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [CompanyController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [CompanyController::class, 'print'])->name('print');
        Route::get('/', [CompanyController::class, 'index'])->name('index');
        Route::get('/create', [CompanyController::class, 'create'])->name('create');
        Route::post('/', [CompanyController::class, 'store'])->name('store');
        Route::get('/{id}', [CompanyController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [CompanyController::class, 'edit'])->name('edit');
        Route::put('/{id}', [CompanyController::class, 'update'])->name('update');
        Route::delete('/{id}', [CompanyController::class, 'destroy'])->name('destroy');
    });

    // Document Management
    Route::prefix('documents')->name('documents.')->middleware('role:ADMINISTRATOR')->group(function () {
        Route::get('/preview-pdf', [DocumentController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [DocumentController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [DocumentController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [DocumentController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [DocumentController::class, 'print'])->name('print');
        Route::get('/', [DocumentController::class, 'index'])->name('index');
        Route::get('/create', [DocumentController::class, 'create'])->name('create');
        Route::post('/', [DocumentController::class, 'store'])->name('store');
        Route::get('/{id}', [DocumentController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [DocumentController::class, 'edit'])->name('edit');
        Route::put('/{id}', [DocumentController::class, 'update'])->name('update');
        Route::delete('/{id}', [DocumentController::class, 'destroy'])->name('destroy');
    });

    // Module Management
    Route::prefix('modules')->name('modules.')->middleware('role:ADMINISTRATOR')->group(function () {
        Route::get('/preview-pdf', [ModuleController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [ModuleController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [ModuleController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [ModuleController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [ModuleController::class, 'print'])->name('print');
        Route::get('/', [ModuleController::class, 'index'])->name('index');
        Route::get('/create', [ModuleController::class, 'create'])->name('create');
        Route::post('/', [ModuleController::class, 'store'])->name('store');
        Route::get('/{id}', [ModuleController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [ModuleController::class, 'edit'])->name('edit');
        Route::put('/{id}', [ModuleController::class, 'update'])->name('update');
        Route::delete('/{id}', [ModuleController::class, 'destroy'])->name('destroy');
    });


    // Finance - Accounts
    Route::prefix('finance/accounts')->name('accounts.')->middleware('role:ADMINISTRATOR,FINANCE')->group(function () {
        Route::get('/preview-pdf', [\App\Http\Controllers\Finance\AccountController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [\App\Http\Controllers\Finance\AccountController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [\App\Http\Controllers\Finance\AccountController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [\App\Http\Controllers\Finance\AccountController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [\App\Http\Controllers\Finance\AccountController::class, 'print'])->name('print');
        Route::get('/', [\App\Http\Controllers\Finance\AccountController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Finance\AccountController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Finance\AccountController::class, 'store'])->name('store');
        Route::get('/{id}', [\App\Http\Controllers\Finance\AccountController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [\App\Http\Controllers\Finance\AccountController::class, 'edit'])->name('edit');
        Route::put('/{id}', [\App\Http\Controllers\Finance\AccountController::class, 'update'])->name('update');
        Route::delete('/{id}', [\App\Http\Controllers\Finance\AccountController::class, 'destroy'])->name('destroy');
    });

    // Finance - Transactions
    Route::prefix('finance/transactions')->name('transactions.')->middleware('role:ADMINISTRATOR,FINANCE,DIRECTOR')->group(function () {
        Route::get('/preview-pdf', [\App\Http\Controllers\Finance\TransactionController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [\App\Http\Controllers\Finance\TransactionController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [\App\Http\Controllers\Finance\TransactionController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [\App\Http\Controllers\Finance\TransactionController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [\App\Http\Controllers\Finance\TransactionController::class, 'print'])->name('print');
        Route::get('/', [\App\Http\Controllers\Finance\TransactionController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Finance\TransactionController::class, 'create'])->name('create')->middleware('role:ADMINISTRATOR,FINANCE');
        Route::post('/', [\App\Http\Controllers\Finance\TransactionController::class, 'store'])->name('store')->middleware('role:ADMINISTRATOR,FINANCE');
        Route::get('/{id}', [\App\Http\Controllers\Finance\TransactionController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [\App\Http\Controllers\Finance\TransactionController::class, 'edit'])->name('edit')->middleware('role:ADMINISTRATOR,FINANCE');
        Route::put('/{id}', [\App\Http\Controllers\Finance\TransactionController::class, 'update'])->name('update')->middleware('role:ADMINISTRATOR,FINANCE');
        Route::delete('/{id}', [\App\Http\Controllers\Finance\TransactionController::class, 'destroy'])->name('destroy')->middleware('role:ADMINISTRATOR,FINANCE');
    });

    // Smart Generator Invoice & Kwitansi Pro V3
    Route::prefix('finance/smart-generator')->name('finance.smart_generator.')->middleware('role:ADMINISTRATOR,FINANCE')->group(function () {
        Route::get('/', [\App\Http\Controllers\Finance\SmartGeneratorController::class, 'index'])->name('index');
        Route::get('/student-invoices/search', [\App\Http\Controllers\Finance\SmartGeneratorController::class, 'searchStudentInvoices'])->name('search_student_invoices');
        Route::post('/pdf', [\App\Http\Controllers\Finance\SmartGeneratorController::class, 'exportPdf'])->name('pdf');
        Route::post('/save', [\App\Http\Controllers\Finance\SmartGeneratorController::class, 'saveHistory'])->name('save');
        Route::get('/history-api', [\App\Http\Controllers\Finance\SmartGeneratorController::class, 'getHistoryApi'])->name('history_api');
        Route::delete('/history/{id}', [\App\Http\Controllers\Finance\SmartGeneratorController::class, 'deleteHistory'])->name('delete_history');
        Route::post('/send-email', [\App\Http\Controllers\Finance\SmartGeneratorController::class, 'sendEmail'])->name('send_email');
    });

    // Finance - Invoices
    Route::prefix('finance/invoices')->name('invoices.')->middleware('role:ADMINISTRATOR,FINANCE')->group(function () {
        Route::get('/preview-pdf', [\App\Http\Controllers\Finance\InvoiceController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [\App\Http\Controllers\Finance\InvoiceController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [\App\Http\Controllers\Finance\InvoiceController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [\App\Http\Controllers\Finance\InvoiceController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [\App\Http\Controllers\Finance\InvoiceController::class, 'print'])->name('print');
        Route::get('/', [\App\Http\Controllers\Finance\InvoiceController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Finance\InvoiceController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Finance\InvoiceController::class, 'store'])->name('store');
        Route::post('/{id}/publish', [\App\Http\Controllers\Finance\InvoiceController::class, 'publish'])->name('publish');
        Route::post('/{id}/cancel', [\App\Http\Controllers\Finance\InvoiceController::class, 'cancel'])->name('cancel');
        Route::post('/{id}/notify', [\App\Http\Controllers\Finance\InvoiceController::class, 'notify'])->name('notify');
        Route::get('/{id}/pdf', [\App\Http\Controllers\Finance\InvoiceController::class, 'downloadInvoicePdf'])->name('pdf');
        Route::get('/{id}', [\App\Http\Controllers\Finance\InvoiceController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [\App\Http\Controllers\Finance\InvoiceController::class, 'edit'])->name('edit');
        Route::put('/{id}', [\App\Http\Controllers\Finance\InvoiceController::class, 'update'])->name('update');
        Route::delete('/{id}', [\App\Http\Controllers\Finance\InvoiceController::class, 'destroy'])->name('destroy');
    });

    // Finance - Payments
    Route::prefix('finance/payments')->name('payments.')->middleware('role:ADMINISTRATOR,FINANCE')->group(function () {
        Route::get('/preview-pdf', [\App\Http\Controllers\Finance\PaymentController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [\App\Http\Controllers\Finance\PaymentController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [\App\Http\Controllers\Finance\PaymentController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [\App\Http\Controllers\Finance\PaymentController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [\App\Http\Controllers\Finance\PaymentController::class, 'print'])->name('print');
        Route::get('/', [\App\Http\Controllers\Finance\PaymentController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Finance\PaymentController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Finance\PaymentController::class, 'store'])->name('store');
        Route::post('/{id}/verify', [\App\Http\Controllers\Finance\PaymentController::class, 'verify'])->name('verify');
        Route::get('/{id}/receipt', [\App\Http\Controllers\Finance\PaymentController::class, 'downloadReceiptPdf'])->name('receipt');
        Route::get('/{id}/proof', [\App\Http\Controllers\Finance\PaymentController::class, 'downloadProof'])->name('proof');
        Route::get('/{id}', [\App\Http\Controllers\Finance\PaymentController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [\App\Http\Controllers\Finance\PaymentController::class, 'edit'])->name('edit');
        Route::put('/{id}', [\App\Http\Controllers\Finance\PaymentController::class, 'update'])->name('update');
        Route::delete('/{id}', [\App\Http\Controllers\Finance\PaymentController::class, 'destroy'])->name('destroy');
    });

    // Finance - Reports
    Route::prefix('finance/reports')->name('reports.finance.')->middleware('role:ADMINISTRATOR,FINANCE,DIRECTOR')->group(function () {
        Route::get('/preview-pdf', [\App\Http\Controllers\Finance\ReportController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [\App\Http\Controllers\Finance\ReportController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [\App\Http\Controllers\Finance\ReportController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [\App\Http\Controllers\Finance\ReportController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [\App\Http\Controllers\Finance\ReportController::class, 'print'])->name('print');
        Route::get('/', [\App\Http\Controllers\Finance\ReportController::class, 'index'])->name('index');
        Route::get('/cash-flow', [\App\Http\Controllers\Finance\ReportController::class, 'cashFlow'])->name('cash_flow');
        Route::get('/outstanding', [\App\Http\Controllers\Finance\ReportController::class, 'outstandingInvoices'])->name('outstanding');
    });

    // HR - Payrolls
    Route::prefix('hr/payrolls')->name('payrolls.')->middleware('role:ADMINISTRATOR,HR,FINANCE')->group(function () {
        Route::get('/preview-pdf', [\App\Http\Controllers\Hr\PayrollController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [\App\Http\Controllers\Hr\PayrollController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [\App\Http\Controllers\Hr\PayrollController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [\App\Http\Controllers\Hr\PayrollController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [\App\Http\Controllers\Hr\PayrollController::class, 'print'])->name('print');
        Route::get('/', [\App\Http\Controllers\Hr\PayrollController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Hr\PayrollController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Hr\PayrollController::class, 'store'])->name('store');
        Route::post('/batch-generate', [\App\Http\Controllers\Hr\PayrollController::class, 'batchGenerate'])->name('batch-generate');
        Route::get('/{id}/pdf', [\App\Http\Controllers\Hr\PayrollController::class, 'downloadPayslipPdf'])->name('pdf');
        Route::post('/{id}/submit', [\App\Http\Controllers\Hr\PayrollController::class, 'submit'])->name('submit');
        Route::post('/{id}/approve', [\App\Http\Controllers\Hr\PayrollController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [\App\Http\Controllers\Hr\PayrollController::class, 'reject'])->name('reject');
        Route::post('/{id}/pay', [\App\Http\Controllers\Hr\PayrollController::class, 'pay'])->name('pay');
        Route::get('/{id}/slip', [\App\Http\Controllers\Hr\PayrollController::class, 'downloadPayslipPdf'])->name('slip');
        Route::get('/{id}/proof', [\App\Http\Controllers\Hr\PayrollController::class, 'downloadProof'])->name('proof');
        Route::get('/{id}', [\App\Http\Controllers\Hr\PayrollController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [\App\Http\Controllers\Hr\PayrollController::class, 'edit'])->name('edit');
        Route::put('/{id}', [\App\Http\Controllers\Hr\PayrollController::class, 'update'])->name('update');
        Route::delete('/{id}', [\App\Http\Controllers\Hr\PayrollController::class, 'destroy'])->name('destroy');
    });

    // HR - Dynamic QR Attendance Engine
    Route::prefix('hr/attendance/qr')->name('hr.attendance.qr.')->middleware('role:ADMINISTRATOR,HR')->group(function () {
        Route::get('/', [\App\Http\Controllers\Hr\QRAttendanceController::class, 'index'])->name('index');
        Route::post('/session', [\App\Http\Controllers\Hr\QRAttendanceController::class, 'storeSession'])->name('session.store');
        Route::get('/session/{sessionId}/display', [\App\Http\Controllers\Hr\QRAttendanceController::class, 'displaySession'])->name('display');
        Route::get('/session/{sessionId}/token', [\App\Http\Controllers\Hr\QRAttendanceController::class, 'getDynamicToken'])->name('token');
        Route::post('/session/{sessionId}/close', [\App\Http\Controllers\Hr\QRAttendanceController::class, 'closeSession'])->name('close');
        Route::get('/session/{sessionId}/summary', [\App\Http\Controllers\Hr\QRAttendanceController::class, 'sessionSummary'])->name('summary');
    });
    Route::get('/hr/attendance/qr/scanner', [\App\Http\Controllers\Hr\QRAttendanceController::class, 'scanner'])->name('hr.attendance.qr.scanner');
    Route::post('/hr/attendance/qr/scan', [\App\Http\Controllers\Hr\QRAttendanceController::class, 'scan'])
        ->middleware('throttle:20,1')
        ->name('hr.attendance.qr.scan');

    // HR - Leave Management Engine
    Route::prefix('hr/leaves')->name('hr.leaves.')->middleware('role:ADMINISTRATOR,HR')->group(function () {
        Route::get('/preview-pdf', [\App\Http\Controllers\Hr\LeaveController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [\App\Http\Controllers\Hr\LeaveController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [\App\Http\Controllers\Hr\LeaveController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [\App\Http\Controllers\Hr\LeaveController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [\App\Http\Controllers\Hr\LeaveController::class, 'print'])->name('print');
        Route::get('/', [\App\Http\Controllers\Hr\LeaveController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Hr\LeaveController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Hr\LeaveController::class, 'store'])->name('store');
        Route::get('/{id}/pdf', [\App\Http\Controllers\Hr\LeaveController::class, 'downloadLeavePdf'])->name('pdf');
        Route::post('/{id}/approve', [\App\Http\Controllers\Hr\LeaveController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [\App\Http\Controllers\Hr\LeaveController::class, 'reject'])->name('reject');
        Route::post('/{id}/cancel', [\App\Http\Controllers\Hr\LeaveController::class, 'cancel'])->name('cancel');
        Route::get('/{id}', [\App\Http\Controllers\Hr\LeaveController::class, 'show'])->name('show');
    });
    Route::middleware('role:ADMINISTRATOR,HR')->get('/hr/leaves/{id}/pdf', [\App\Http\Controllers\Hr\LeaveController::class, 'downloadLeavePdf'])->name('leaves.pdf');

    // HR - Overtime Management Engine
    Route::prefix('hr/overtimes')->name('hr.overtimes.')->middleware('role:ADMINISTRATOR,HR')->group(function () {
        Route::get('/preview-pdf', [\App\Http\Controllers\Hr\OvertimeController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [\App\Http\Controllers\Hr\OvertimeController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [\App\Http\Controllers\Hr\OvertimeController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [\App\Http\Controllers\Hr\OvertimeController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [\App\Http\Controllers\Hr\OvertimeController::class, 'print'])->name('print');
        Route::get('/', [\App\Http\Controllers\Hr\OvertimeController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Hr\OvertimeController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Hr\OvertimeController::class, 'store'])->name('store');
        Route::get('/{id}/pdf', [\App\Http\Controllers\Hr\OvertimeController::class, 'downloadOvertimePdf'])->name('pdf');
        Route::post('/{id}/approve', [\App\Http\Controllers\Hr\OvertimeController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [\App\Http\Controllers\Hr\OvertimeController::class, 'reject'])->name('reject');
        Route::get('/{id}', [\App\Http\Controllers\Hr\OvertimeController::class, 'show'])->name('show');
    });
    Route::middleware('role:ADMINISTRATOR,HR')->get('/hr/overtimes/{id}/pdf', [\App\Http\Controllers\Hr\OvertimeController::class, 'downloadOvertimePdf'])->name('overtimes.pdf');

    // Permission Management (Under Construction)
    /* Route::prefix('permissions')->name('permissions.')->group(function () {
        Route::get('/', [PermissionController::class, 'index'])->name('index');
        Route::get('/create', [PermissionController::class, 'create'])->name('create');
        Route::post('/', [PermissionController::class, 'store'])->name('store');
        Route::get('/{id}', [PermissionController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [PermissionController::class, 'edit'])->name('edit');
        Route::put('/{id}', [PermissionController::class, 'update'])->name('update');
        Route::delete('/{id}', [PermissionController::class, 'destroy'])->name('destroy');
    }); */

    // Approval / Workflow Management
    Route::prefix('approvals')->name('approvals.')->middleware('role:ADMINISTRATOR,DIRECTOR')->group(function () {
        Route::get('/', [\App\Http\Controllers\Core\ApprovalController::class, 'index'])->name('index');
        Route::get('/{id}', [\App\Http\Controllers\Core\ApprovalController::class, 'show'])->name('show');
        Route::post('/{id}/approve', [\App\Http\Controllers\Core\ApprovalController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [\App\Http\Controllers\Core\ApprovalController::class, 'reject'])->name('reject');
    });

    // Audit Log Management
    Route::prefix('audit')->name('audit.')->middleware('role:ADMINISTRATOR')->group(function () {
        Route::get('/preview-pdf', [\App\Http\Controllers\Core\AuditLogController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [\App\Http\Controllers\Core\AuditLogController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [\App\Http\Controllers\Core\AuditLogController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [\App\Http\Controllers\Core\AuditLogController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [\App\Http\Controllers\Core\AuditLogController::class, 'print'])->name('print');
        Route::get('/', [\App\Http\Controllers\Core\AuditLogController::class, 'index'])->name('index');
        Route::get('/statistics', [\App\Http\Controllers\Core\AuditLogController::class, 'statistics'])->name('statistics');
        Route::get('/{id}', [\App\Http\Controllers\Core\AuditLogController::class, 'show'])->name('show');
    });

    // Student Portal
    Route::prefix('student/portal')->name('student.portal.')->middleware('role:STUDENT')->group(function () {
        Route::get('/assignments', [StudentPortalController::class, 'assignments'])->name('assignments');
        Route::get('/assignments/{id}', [StudentPortalController::class, 'showAssignment'])->name('assignments.show');
        Route::get('/materials', [StudentPortalController::class, 'materials'])->name('materials');
    });

    // Student Billing (Invoices)
    Route::prefix('student/billing')->name('student.billing.')->middleware('role:STUDENT')->group(function () {
        Route::get('/', [\App\Http\Controllers\Finance\StudentBillingController::class, 'index'])->name('index');
        Route::get('/payments/{paymentId}/proof', [\App\Http\Controllers\Finance\StudentBillingController::class, 'downloadPaymentProof'])->name('payment-proof');
        Route::get('/{id}/pdf', [\App\Http\Controllers\Finance\StudentBillingController::class, 'downloadInvoicePdf'])->name('invoice-pdf');
        Route::get('/{id}', [\App\Http\Controllers\Finance\StudentBillingController::class, 'show'])->name('show');
        Route::post('/{id}/pay', [\App\Http\Controllers\Finance\StudentBillingController::class, 'pay'])->name('pay');
        Route::get('/{id}/proof', [\App\Http\Controllers\Finance\StudentBillingController::class, 'downloadProof'])->name('proof');
    });

    // API routes for dynamic UI
    Route::prefix('api')->group(function () {
        Route::get('/classes/{id}/students', [\App\Http\Controllers\Core\ClassController::class, 'getStudents'])->middleware('role:ADMINISTRATOR,ACADEMIC,TEACHER');
        Route::get('/employees/{id}', [\App\Http\Controllers\Core\EmployeeController::class, 'lookup'])->middleware('role:ADMINISTRATOR,HR,FINANCE');
        Route::get('/students/{id}', [\App\Http\Controllers\Core\StudentController::class, 'lookup'])->middleware('role:ADMINISTRATOR,ACADEMIC');
    });

    // System Settings Management — ADMINISTRATOR ONLY
    Route::prefix('settings')->name('settings.')->middleware('role:ADMINISTRATOR')->group(function () {
        Route::get('/', [\App\Http\Controllers\Core\SystemSettingController::class, 'index'])->name('index');
        Route::post('/update', [\App\Http\Controllers\Core\SystemSettingController::class, 'update'])->name('update');
        Route::post('/test-email', [\App\Http\Controllers\Core\SystemSettingController::class, 'sendTestEmail'])->name('test_email');
        Route::post('/clear-cache', [\App\Http\Controllers\Core\SystemSettingController::class, 'clearCache'])->name('clear_cache');
        Route::post('/reset-branding', [\App\Http\Controllers\Core\SystemSettingController::class, 'resetBranding'])->name('reset_branding');

        // Email Delivery Connection Center (EPS Rev.4.1)
        Route::prefix('email')->name('email.')->group(function () {
            Route::get('/connect/{provider}', [\App\Http\Controllers\Core\EmailDeliveryController::class, 'connectProvider'])->name('connect');
            Route::get('/callback/{provider}', [\App\Http\Controllers\Core\EmailDeliveryController::class, 'oauthCallback'])->name('callback');
            Route::post('/confirm', [\App\Http\Controllers\Core\EmailDeliveryController::class, 'confirmConnection'])->name('confirm');
            Route::get('/cancel', [\App\Http\Controllers\Core\EmailDeliveryController::class, 'cancelPreview'])->name('cancel');
            Route::post('/smtp/connect', [\App\Http\Controllers\Core\EmailDeliveryController::class, 'connectSmtp'])->name('smtp_connect');
            Route::post('/disconnect', [\App\Http\Controllers\Core\EmailDeliveryController::class, 'disconnect'])->name('disconnect');
            Route::post('/reconnect', [\App\Http\Controllers\Core\EmailDeliveryController::class, 'reconnect'])->name('reconnect');
            Route::post('/sender', [\App\Http\Controllers\Core\EmailDeliveryController::class, 'updateSender'])->name('sender');
            Route::post('/test', [\App\Http\Controllers\Core\EmailDeliveryController::class, 'sendTestEmail'])->name('test');
        });
    });

    // Finance Module Settings — FINANCE + ADMINISTRATOR
    Route::prefix('finance/settings')->name('finance.settings.')->middleware('role:FINANCE,ADMINISTRATOR')->group(function () {
        Route::get('/', [\App\Http\Controllers\Finance\FinanceSettingsController::class, 'index'])->name('index');
        Route::post('/update', [\App\Http\Controllers\Finance\FinanceSettingsController::class, 'update'])->name('update');
    });

    // HR Module Settings — HR + ADMINISTRATOR
    Route::prefix('hr/settings')->name('hr.settings.')->middleware('role:HR,ADMINISTRATOR')->group(function () {
        Route::get('/', [\App\Http\Controllers\Hr\HrSettingsController::class, 'index'])->name('index');
        Route::post('/update', [\App\Http\Controllers\Hr\HrSettingsController::class, 'update'])->name('update');
        Route::get('/attendance', [\App\Http\Controllers\Hr\HrSettingsController::class, 'attendance'])->name('attendance');
    });

    // Academic Module Settings — ACADEMIC + ADMINISTRATOR
    Route::prefix('academic/settings')->name('academic.settings.')->middleware('role:ACADEMIC,ADMINISTRATOR')->group(function () {
        Route::get('/', [\App\Http\Controllers\Academic\AcademicSettingsController::class, 'index'])->name('index');
        Route::post('/update', [\App\Http\Controllers\Academic\AcademicSettingsController::class, 'update'])->name('update');
    });

        Route::prefix('academic')->middleware('role:ACADEMIC,ADMINISTRATOR')->group(function () {
        $academicControllers = [
            'batches' => \App\Http\Controllers\Academic\BatchController::class,
            'classes' => \App\Http\Controllers\Academic\ClassController::class,
            'announcements' => \App\Http\Controllers\Academic\AnnouncementController::class,
            'assessments' => \App\Http\Controllers\Academic\AssessmentController::class,
            'attendances' => \App\Http\Controllers\Academic\AttendanceController::class,
            'schedules' => \App\Http\Controllers\Academic\ScheduleController::class,
            'scores' => \App\Http\Controllers\Academic\ScoreController::class,
            'subjects' => \App\Http\Controllers\Academic\SubjectController::class,
        ];

        foreach ($academicControllers as $prefix => $controller) {
            Route::prefix($prefix)->name($prefix . '.')->group(function () use ($controller) {
                Route::get('/preview-pdf', [$controller, 'previewPdf'])->name('preview-pdf');
                Route::get('/export-pdf', [$controller, 'exportPdf'])->name('export-pdf');
                Route::get('/export-excel', [$controller, 'exportExcel'])->name('export-excel');
                Route::get('/export-csv', [$controller, 'exportCsv'])->name('export-csv');
                Route::get('/print', [$controller, 'print'])->name('print');

                Route::get('/', [$controller, 'index'])->name('index');
                Route::get('/create', [$controller, 'create'])->name('create');
                Route::post('/', [$controller, 'store'])->name('store');
                Route::get('/{id}', [$controller, 'show'])->name('show');
                Route::get('/{id}/edit', [$controller, 'edit'])->name('edit');
                Route::put('/{id}', [$controller, 'update'])->name('update');
                Route::delete('/{id}', [$controller, 'destroy'])->name('destroy');
            });
        }
    });

    // Assignments route that allows TEACHER access as well
    Route::prefix('academic')->middleware('role:ACADEMIC,ADMINISTRATOR,TEACHER')->group(function () {
        $assignmentController = \App\Http\Controllers\Academic\AssignmentController::class;
        Route::prefix('assignments')->name('assignments.')->group(function () use ($assignmentController) {
            Route::get('/preview-pdf', [$assignmentController, 'previewPdf'])->name('preview-pdf');
            Route::get('/export-pdf', [$assignmentController, 'exportPdf'])->name('export-pdf');
            Route::get('/export-excel', [$assignmentController, 'exportExcel'])->name('export-excel');
            Route::get('/export-csv', [$assignmentController, 'exportCsv'])->name('export-csv');
                Route::get('/print', [$assignmentController, 'print'])->name('print');

            Route::get('/', [$assignmentController, 'index'])->name('index');
            Route::get('/create', [$assignmentController, 'create'])->name('create');
            Route::post('/', [$assignmentController, 'store'])->name('store');
            Route::get('/{id}', [$assignmentController, 'show'])->name('show');
            Route::get('/{id}/edit', [$assignmentController, 'edit'])->name('edit');
            Route::put('/{id}', [$assignmentController, 'update'])->name('update');
            Route::delete('/{id}', [$assignmentController, 'destroy'])->name('destroy');
        });
    });

    // Teacher Academic Workspace
    Route::prefix('teacher/workspace')->name('teacher.workspace.')->middleware('role:TEACHER')->group(function () {
        Route::get('/schedule', [\App\Http\Controllers\Academic\TeacherWorkspaceController::class, 'schedule'])->name('schedule');
        Route::get('/classes', [\App\Http\Controllers\Academic\TeacherWorkspaceController::class, 'myClasses'])->name('classes');
        Route::get('/classes/{classId}/students', [\App\Http\Controllers\Academic\TeacherWorkspaceController::class, 'classStudents'])->name('classes.students');
        Route::get('/classes/{classId}/attendance', [\App\Http\Controllers\Academic\TeacherWorkspaceController::class, 'classAttendance'])->name('classes.attendance');

        // New Operational Routes
        Route::get('/students', [\App\Http\Controllers\Academic\TeacherWorkspaceController::class, 'students'])->name('students');
        Route::get('/attendances', [\App\Http\Controllers\Academic\TeacherWorkspaceController::class, 'attendances'])->name('attendances');
        Route::get('/attendance-requests', [\App\Http\Controllers\Academic\TeacherWorkspaceController::class, 'attendanceRequests'])->name('attendance-requests');
        Route::get('/scores', [\App\Http\Controllers\Academic\TeacherWorkspaceController::class, 'scores'])->name('scores');
        Route::get('/scores/create', [\App\Http\Controllers\Academic\TeacherWorkspaceController::class, 'scoresCreate'])->name('scores.create');
        Route::post('/scores', [\App\Http\Controllers\Academic\TeacherWorkspaceController::class, 'scoresStore'])->name('scores.store');
        Route::get('/assignments', [\App\Http\Controllers\Academic\TeacherWorkspaceController::class, 'assignments'])->name('assignments');

        Route::get('/calendar', [\App\Http\Controllers\Academic\TeacherWorkspaceController::class, 'calendar'])->name('calendar');
        Route::get('/reports', [\App\Http\Controllers\Academic\TeacherWorkspaceController::class, 'reports'])->name('reports');
        Route::get('/reports/attendances.csv', [\App\Http\Controllers\Academic\TeacherWorkspaceController::class, 'exportAttendancesCsv'])->name('reports.attendances-csv');
        Route::get('/reports/attendances.pdf', [\App\Http\Controllers\Academic\TeacherWorkspaceController::class, 'exportAttendancesPdf'])->name('reports.attendances-pdf');
        Route::get('/reports/attendances/print', [\App\Http\Controllers\Academic\TeacherWorkspaceController::class, 'printAttendances'])->name('reports.attendances-print');
        Route::get('/reports/scores.csv', [\App\Http\Controllers\Academic\TeacherWorkspaceController::class, 'exportScoresCsv'])->name('reports.scores-csv');
        Route::get('/reports/scores.pdf', [\App\Http\Controllers\Academic\TeacherWorkspaceController::class, 'exportScoresPdf'])->name('reports.scores-pdf');
        Route::get('/reports/scores/print', [\App\Http\Controllers\Academic\TeacherWorkspaceController::class, 'printScores'])->name('reports.scores-print');
    });

    // Student Academic Workspace (Progress, Schedule)
    Route::prefix('student')->name('student.')->middleware('role:STUDENT')->group(function () {
        Route::get('/schedule', [\App\Http\Controllers\Academic\StudentWorkspaceController::class, 'mySchedule'])->name('schedule');
        Route::get('/progress', [\App\Http\Controllers\Academic\StudentWorkspaceController::class, 'progress'])->name('progress');
        Route::get('/subjects', [\App\Http\Controllers\Academic\StudentWorkspaceController::class, 'mySubjects'])->name('subjects');
        Route::get('/calendar', [\App\Http\Controllers\Academic\StudentWorkspaceController::class, 'calendar'])->name('calendar');

        Route::get('/export-scores', [\App\Http\Controllers\Academic\StudentWorkspaceController::class, 'exportScoresCsv'])->name('export-scores');
        Route::get('/export-scores-pdf', [\App\Http\Controllers\Academic\StudentWorkspaceController::class, 'exportScoresPdf'])->name('export-scores-pdf');
        Route::get('/print-scores', [\App\Http\Controllers\Academic\StudentWorkspaceController::class, 'printScores'])->name('print-scores');
        Route::get('/export-attendances', [\App\Http\Controllers\Academic\StudentWorkspaceController::class, 'exportAttendancesCsv'])->name('export-attendances');
        Route::get('/export-attendances-pdf', [\App\Http\Controllers\Academic\StudentWorkspaceController::class, 'exportAttendancesPdf'])->name('export-attendances-pdf');
        Route::get('/print-attendances', [\App\Http\Controllers\Academic\StudentWorkspaceController::class, 'printAttendances'])->name('print-attendances');
    });

    // Student Geo-Fenced QR Attendance
    Route::prefix('attendance/student')->name('attendances.student.')->middleware('role:STUDENT')->group(function () {
        Route::get('/scanner', [\App\Http\Controllers\Academic\StudentQRAttendanceController::class, 'scanner'])->name('scanner');
        Route::post('/scan', [\App\Http\Controllers\Academic\StudentQRAttendanceController::class, 'scan'])
            ->middleware('throttle:20,1')
            ->name('scan');
    });
    Route::get('/attendance/student/token', [\App\Http\Controllers\Academic\StudentQRAttendanceController::class, 'getDynamicToken'])
        ->middleware('role:ACADEMIC,ADMINISTRATOR')
        ->name('attendances.student.token');

    // Profile Route
    Route::get('/profile', [\App\Http\Controllers\Core\ProfileController::class, 'index'])->name('profile.index');
    Route::post('/profile/password', [\App\Http\Controllers\Core\ProfileController::class, 'updatePassword'])->name('profile.password.update');

    // Notification Management
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/preview-pdf', [\App\Http\Controllers\Core\NotificationController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [\App\Http\Controllers\Core\NotificationController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [\App\Http\Controllers\Core\NotificationController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [\App\Http\Controllers\Core\NotificationController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [\App\Http\Controllers\Core\NotificationController::class, 'print'])->name('print');
        Route::get('/', [\App\Http\Controllers\Core\NotificationController::class, 'index'])->name('index');
        Route::post('/mark-all-read', [\App\Http\Controllers\Core\NotificationController::class, 'markAllRead'])->name('markAllRead');
        Route::post('/{id}/read', [\App\Http\Controllers\Core\NotificationController::class, 'readAndRedirect'])->name('read');
        Route::post('/{id}/mark-read', [\App\Http\Controllers\Core\NotificationController::class, 'markRead'])->name('markRead');
        Route::post('/{id}/archive', [\App\Http\Controllers\Core\NotificationController::class, 'archive'])->name('archive');
        Route::delete('/{id}', [\App\Http\Controllers\Core\NotificationController::class, 'destroy'])->name('destroy');
        Route::get('/{id}', [\App\Http\Controllers\Core\NotificationController::class, 'show'])->name('show');
    });

    Route::prefix('search')->name('search.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Core\GlobalSearchController::class, 'index'])->name('index');
        Route::get('/overlay', [\App\Http\Controllers\Core\GlobalSearchController::class, 'overlay'])->name('overlay');
        Route::post('/clear-history', [\App\Http\Controllers\Core\GlobalSearchController::class, 'clearHistory'])->name('clearHistory');
    });

    Route::prefix('activity')->name('activity.')->group(function () {
        Route::get('/preview-pdf', [\App\Http\Controllers\Core\ActivityController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [\App\Http\Controllers\Core\ActivityController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [\App\Http\Controllers\Core\ActivityController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [\App\Http\Controllers\Core\ActivityController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [\App\Http\Controllers\Core\ActivityController::class, 'print'])->name('print');
        Route::get('/', [\App\Http\Controllers\Core\ActivityController::class, 'index'])->name('index');
        Route::get('/export', [\App\Http\Controllers\Core\ActivityController::class, 'export'])->name('export');
    });

    Route::prefix('document/templates')->name('templates.')->middleware('role:ADMINISTRATOR')->group(function () {
        Route::get('/', [\App\Http\Controllers\Core\DocumentTemplateController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Core\DocumentTemplateController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Core\DocumentTemplateController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [\App\Http\Controllers\Core\DocumentTemplateController::class, 'edit'])->name('edit');
        Route::put('/{id}', [\App\Http\Controllers\Core\DocumentTemplateController::class, 'update'])->name('update');
    });

    Route::prefix('documents/{id}/pdf')->name('pdf.')->middleware('role:ADMINISTRATOR')->group(function () {
        Route::get('/preview', [\App\Http\Controllers\Core\PdfController::class, 'preview'])->name('preview');
        Route::get('/download', [\App\Http\Controllers\Core\PdfController::class, 'download'])->name('download');
        Route::post('/generate', [\App\Http\Controllers\Core\PdfController::class, 'generate'])->name('generate');
    });

    // HR Attendance Monitoring
    Route::get('/hr/attendance/monitoring', [\App\Http\Controllers\Hr\AttendanceMonitoringController::class, 'index'])
        ->name('hr.attendance.monitoring')
        ->middleware('role:HR,ADMINISTRATOR');

    // Student Attendance History
    Route::get('/attendance/my-history', [\App\Http\Controllers\Student\AttendanceHistoryController::class, 'index'])
        ->name('attendances.my-history')
        ->middleware('role:STUDENT');

    // Student Attendance Requests
    Route::prefix('student/attendance/requests')->name('student.attendance.requests.')->middleware('role:STUDENT')->group(function () {
        Route::get('/', [\App\Http\Controllers\Student\AttendanceRequestController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Student\AttendanceRequestController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Student\AttendanceRequestController::class, 'store'])->name('store');
        Route::get('/{id}/evidence', [\App\Http\Controllers\Student\AttendanceRequestController::class, 'downloadEvidence'])->name('evidence');
    });

    // Academic Attendance Requests
    Route::prefix('academic/attendance/requests')->name('academic.attendance.requests.')->middleware('role:ACADEMIC,ADMINISTRATOR')->group(function () {
        Route::get('/', [\App\Http\Controllers\Academic\AttendanceRequestController::class, 'index'])->name('index');
        Route::get('/{id}', [\App\Http\Controllers\Academic\AttendanceRequestController::class, 'show'])->name('show');
        Route::post('/{id}/approve', [\App\Http\Controllers\Academic\AttendanceRequestController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [\App\Http\Controllers\Academic\AttendanceRequestController::class, 'reject'])->name('reject');
        Route::get('/{id}/evidence', [\App\Http\Controllers\Academic\AttendanceRequestController::class, 'downloadEvidence'])->name('evidence');
    });

    // Permanent Attendance QR Management
    Route::prefix('attendance/qr')->name('attendance.qr.')->middleware('role:ADMINISTRATOR,HR,ACADEMIC,MASTER')->group(function () {
        Route::get('/', [\App\Http\Controllers\Core\PermanentQrController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Core\PermanentQrController::class, 'store'])->name('store');
        Route::get('/{id}/preview', [\App\Http\Controllers\Core\PermanentQrController::class, 'preview'])->name('preview');
        Route::get('/{id}/print', [\App\Http\Controllers\Core\PermanentQrController::class, 'printView'])->name('print');
        Route::get('/{id}/pdf', [\App\Http\Controllers\Core\PermanentQrController::class, 'downloadPdf'])->name('pdf');
        Route::post('/{id}/availability', [\App\Http\Controllers\Core\PermanentQrController::class, 'updateAvailability'])->name('availability');
        Route::post('/{id}/deactivate', [\App\Http\Controllers\Core\PermanentQrController::class, 'deactivate'])->name('deactivate');
        Route::delete('/{id}', [\App\Http\Controllers\Core\PermanentQrController::class, 'destroy'])->name('destroy');
    });

    // Permanent Attendance QR Scanning Flow
    Route::get('/attendance/scan/{type}/{identifier}', [\App\Http\Controllers\Core\PermanentQrController::class, 'scanEntry'])->name('attendance.scan.entry');
    Route::post('/attendance/scan/{type}/{identifier}/verify', [\App\Http\Controllers\Core\PermanentQrController::class, 'scanVerify'])
        ->middleware('throttle:20,1')
        ->name('attendance.scan.verify');

});



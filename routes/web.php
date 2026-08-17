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
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/verify-receipt/{id}', [\App\Http\Controllers\Finance\PaymentController::class, 'verifyReceiptPublic'])->name('payments.verify-receipt-public');
Route::get('/verify-invoice/{id}', [\App\Http\Controllers\Finance\InvoiceController::class, 'verifyInvoicePublic'])->name('invoices.verify-public');
Route::get('/verify-payslip/{id}', [\App\Http\Controllers\Hr\PayrollController::class, 'verifyPayslipPublic'])->name('payrolls.verify-public');
Route::get('/verify-leave/{id}', [\App\Http\Controllers\Hr\LeaveController::class, 'verifyLeavePublic'])->name('leaves.verify-public');
Route::get('/verify-overtime/{id}', [\App\Http\Controllers\Hr\OvertimeController::class, 'verifyOvertimePublic'])->name('overtimes.verify-public');

// Requires authentication
Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Role-specific Dashboards
    Route::get('/dashboard/hr', [\App\Http\Controllers\Core\HrDashboardController::class, 'index'])->name('dashboard.hr');
    Route::get('/dashboard/academic', [\App\Http\Controllers\Core\AcademicDashboardController::class, 'index'])->name('dashboard.academic');
    Route::get('/dashboard/marketing', [\App\Http\Controllers\Core\MarketingDashboardController::class, 'index'])->name('dashboard.marketing');
    Route::get('/dashboard/finance', [\App\Http\Controllers\Core\FinanceDashboardController::class, 'index'])->name('dashboard.finance');
    Route::get('/dashboard/director', [\App\Http\Controllers\Core\DirectorDashboardController::class, 'index'])->name('dashboard.director');
    Route::get('/dashboard/teacher', [\App\Http\Controllers\Core\TeacherDashboardController::class, 'index'])->name('dashboard.teacher');
    Route::get('/dashboard/student', [\App\Http\Controllers\Core\StudentDashboardController::class, 'index'])->name('dashboard.student');
    Route::get('/dashboard/administrator', [\App\Http\Controllers\Core\DashboardController::class, 'index'])->name('dashboard.administrator');
    
    // Developer Panel
    Route::get('/developer-panel', [DeveloperPanelController::class, 'index'])->name('developer-panel.index');

    // User Management
    Route::prefix('users')->name('users.')->group(function () {
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
    Route::prefix('departments')->name('departments.')->group(function () {
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
    Route::prefix('positions')->name('positions.')->group(function () {
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
    Route::prefix('employees')->name('employees.')->group(function () {
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
    Route::prefix('teachers')->name('teachers.')->group(function () {
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
    Route::prefix('programs')->name('programs.')->group(function () {
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

    // Batch Management
    Route::prefix('batches')->name('batches.')->group(function () {
        Route::get('/preview-pdf', [BatchController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [BatchController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [BatchController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [BatchController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [BatchController::class, 'print'])->name('print');
        Route::get('/', [BatchController::class, 'index'])->name('index');
        Route::get('/create', [BatchController::class, 'create'])->name('create');
        Route::post('/', [BatchController::class, 'store'])->name('store');
        Route::get('/{id}', [BatchController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [BatchController::class, 'edit'])->name('edit');
        Route::put('/{id}', [BatchController::class, 'update'])->name('update');
        Route::delete('/{id}', [BatchController::class, 'destroy'])->name('destroy');
    });

    // Class Management
    Route::prefix('classes')->name('classes.')->group(function () {
        Route::get('/preview-pdf', [ClassController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [ClassController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [ClassController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [ClassController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [ClassController::class, 'print'])->name('print');
        Route::get('/', [ClassController::class, 'index'])->name('index');
        Route::get('/create', [ClassController::class, 'create'])->name('create');
        Route::post('/', [ClassController::class, 'store'])->name('store');
        Route::get('/{id}', [ClassController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [ClassController::class, 'edit'])->name('edit');
        Route::put('/{id}', [ClassController::class, 'update'])->name('update');
        Route::delete('/{id}', [ClassController::class, 'destroy'])->name('destroy');
    });

    // Student Management
    Route::prefix('students')->name('students.')->group(function () {
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
    Route::prefix('academic/alumni')->name('alumni.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Academic\AlumniController::class, 'index'])->name('index');
        Route::get('/{id}', [\App\Http\Controllers\Academic\AlumniController::class, 'show'])->name('show');
    });

    // Company Management
    Route::prefix('companies')->name('companies.')->group(function () {
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
    Route::prefix('documents')->name('documents.')->group(function () {
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
    Route::prefix('modules')->name('modules.')->group(function () {
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
    Route::prefix('finance/accounts')->name('accounts.')->group(function () {
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
    Route::prefix('finance/transactions')->name('transactions.')->group(function () {
        Route::get('/preview-pdf', [\App\Http\Controllers\Finance\TransactionController::class, 'previewPdf'])->name('preview-pdf');
        Route::get('/export-pdf', [\App\Http\Controllers\Finance\TransactionController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [\App\Http\Controllers\Finance\TransactionController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [\App\Http\Controllers\Finance\TransactionController::class, 'exportCsv'])->name('export-csv');
        Route::get('/print', [\App\Http\Controllers\Finance\TransactionController::class, 'print'])->name('print');
        Route::get('/', [\App\Http\Controllers\Finance\TransactionController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Finance\TransactionController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Finance\TransactionController::class, 'store'])->name('store');
        Route::get('/{id}', [\App\Http\Controllers\Finance\TransactionController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [\App\Http\Controllers\Finance\TransactionController::class, 'edit'])->name('edit');
        Route::put('/{id}', [\App\Http\Controllers\Finance\TransactionController::class, 'update'])->name('update');
        Route::delete('/{id}', [\App\Http\Controllers\Finance\TransactionController::class, 'destroy'])->name('destroy');
    });

    // Smart Generator Invoice & Kwitansi Pro V3
    Route::prefix('finance/smart-generator')->name('finance.smart_generator.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Finance\SmartGeneratorController::class, 'index'])->name('index');
        Route::get('/student-invoices/search', [\App\Http\Controllers\Finance\SmartGeneratorController::class, 'searchStudentInvoices'])->name('search_student_invoices');
        Route::post('/pdf', [\App\Http\Controllers\Finance\SmartGeneratorController::class, 'exportPdf'])->name('pdf');
        Route::post('/save', [\App\Http\Controllers\Finance\SmartGeneratorController::class, 'saveHistory'])->name('save');
        Route::get('/history-api', [\App\Http\Controllers\Finance\SmartGeneratorController::class, 'getHistoryApi'])->name('history_api');
        Route::delete('/history/{id}', [\App\Http\Controllers\Finance\SmartGeneratorController::class, 'deleteHistory'])->name('delete_history');
        Route::post('/send-email', [\App\Http\Controllers\Finance\SmartGeneratorController::class, 'sendEmail'])->name('send_email');
    });

    // Finance - Invoices
    Route::prefix('finance/invoices')->name('invoices.')->group(function () {
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
        Route::get('/{id}/pdf', [\App\Http\Controllers\Finance\InvoiceController::class, 'downloadInvoicePdf'])->name('pdf');
        Route::get('/{id}', [\App\Http\Controllers\Finance\InvoiceController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [\App\Http\Controllers\Finance\InvoiceController::class, 'edit'])->name('edit');
        Route::put('/{id}', [\App\Http\Controllers\Finance\InvoiceController::class, 'update'])->name('update');
        Route::delete('/{id}', [\App\Http\Controllers\Finance\InvoiceController::class, 'destroy'])->name('destroy');
    });

    // Finance - Payments
    Route::prefix('finance/payments')->name('payments.')->group(function () {
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
        Route::get('/{id}', [\App\Http\Controllers\Finance\PaymentController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [\App\Http\Controllers\Finance\PaymentController::class, 'edit'])->name('edit');
        Route::put('/{id}', [\App\Http\Controllers\Finance\PaymentController::class, 'update'])->name('update');
        Route::delete('/{id}', [\App\Http\Controllers\Finance\PaymentController::class, 'destroy'])->name('destroy');
    });

    // Finance - Reports
    Route::prefix('finance/reports')->name('reports.finance.')->group(function () {
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
    Route::prefix('hr/payrolls')->name('payrolls.')->group(function () {
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
        Route::get('/{id}', [\App\Http\Controllers\Hr\PayrollController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [\App\Http\Controllers\Hr\PayrollController::class, 'edit'])->name('edit');
        Route::put('/{id}', [\App\Http\Controllers\Hr\PayrollController::class, 'update'])->name('update');
        Route::delete('/{id}', [\App\Http\Controllers\Hr\PayrollController::class, 'destroy'])->name('destroy');
    });

    // HR - Dynamic QR Attendance Engine
    Route::prefix('hr/attendance/qr')->name('hr.attendance.qr.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Hr\QRAttendanceController::class, 'index'])->name('index');
        Route::post('/session', [\App\Http\Controllers\Hr\QRAttendanceController::class, 'storeSession'])->name('session.store');
        Route::get('/session/{sessionId}/display', [\App\Http\Controllers\Hr\QRAttendanceController::class, 'displaySession'])->name('display');
        Route::get('/session/{sessionId}/token', [\App\Http\Controllers\Hr\QRAttendanceController::class, 'getDynamicToken'])->name('token');
        Route::post('/session/{sessionId}/close', [\App\Http\Controllers\Hr\QRAttendanceController::class, 'closeSession'])->name('close');
        Route::get('/session/{sessionId}/summary', [\App\Http\Controllers\Hr\QRAttendanceController::class, 'sessionSummary'])->name('summary');
        Route::get('/scanner', [\App\Http\Controllers\Hr\QRAttendanceController::class, 'scanner'])->name('scanner');
        Route::post('/scan', [\App\Http\Controllers\Hr\QRAttendanceController::class, 'scan'])->name('scan');
    });

    // HR - Leave Management Engine
    Route::prefix('hr/leaves')->name('hr.leaves.')->group(function () {
        Route::get('/export-pdf', [\App\Http\Controllers\Hr\LeaveController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [\App\Http\Controllers\Hr\LeaveController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [\App\Http\Controllers\Hr\LeaveController::class, 'exportCsv'])->name('export-csv');
        Route::get('/', [\App\Http\Controllers\Hr\LeaveController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Hr\LeaveController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Hr\LeaveController::class, 'store'])->name('store');
        Route::get('/{id}/pdf', [\App\Http\Controllers\Hr\LeaveController::class, 'downloadLeavePdf'])->name('pdf');
        Route::post('/{id}/approve', [\App\Http\Controllers\Hr\LeaveController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [\App\Http\Controllers\Hr\LeaveController::class, 'reject'])->name('reject');
        Route::post('/{id}/cancel', [\App\Http\Controllers\Hr\LeaveController::class, 'cancel'])->name('cancel');
        Route::get('/{id}', [\App\Http\Controllers\Hr\LeaveController::class, 'show'])->name('show');
    });
    Route::get('/hr/leaves/{id}/pdf', [\App\Http\Controllers\Hr\LeaveController::class, 'downloadLeavePdf'])->name('leaves.pdf');

    // HR - Overtime Management Engine
    Route::prefix('hr/overtimes')->name('hr.overtimes.')->group(function () {
        Route::get('/export-pdf', [\App\Http\Controllers\Hr\OvertimeController::class, 'exportPdf'])->name('export-pdf');
        Route::get('/export-excel', [\App\Http\Controllers\Hr\OvertimeController::class, 'exportExcel'])->name('export-excel');
        Route::get('/export-csv', [\App\Http\Controllers\Hr\OvertimeController::class, 'exportCsv'])->name('export-csv');
        Route::get('/', [\App\Http\Controllers\Hr\OvertimeController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Hr\OvertimeController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Hr\OvertimeController::class, 'store'])->name('store');
        Route::get('/{id}/pdf', [\App\Http\Controllers\Hr\OvertimeController::class, 'downloadOvertimePdf'])->name('pdf');
        Route::post('/{id}/approve', [\App\Http\Controllers\Hr\OvertimeController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [\App\Http\Controllers\Hr\OvertimeController::class, 'reject'])->name('reject');
        Route::get('/{id}', [\App\Http\Controllers\Hr\OvertimeController::class, 'show'])->name('show');
    });
    Route::get('/hr/overtimes/{id}/pdf', [\App\Http\Controllers\Hr\OvertimeController::class, 'downloadOvertimePdf'])->name('overtimes.pdf');

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
    Route::prefix('approvals')->name('approvals.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Core\ApprovalController::class, 'index'])->name('index');
        Route::get('/{id}', [\App\Http\Controllers\Core\ApprovalController::class, 'show'])->name('show');
        Route::post('/{id}/approve', [\App\Http\Controllers\Core\ApprovalController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [\App\Http\Controllers\Core\ApprovalController::class, 'reject'])->name('reject');
    });

    // Audit Log Management
    Route::prefix('audit')->name('audit.')->group(function () {
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
    Route::prefix('student/portal')->name('student.portal.')->group(function () {
        Route::get('/assignments', [StudentPortalController::class, 'assignments'])->name('assignments');
        Route::get('/assignments/{id}', [StudentPortalController::class, 'showAssignment'])->name('assignments.show');
        Route::post('/assignments/{id}/upload', [StudentPortalController::class, 'uploadSubmission'])->name('assignments.upload');
        Route::get('/materials', [StudentPortalController::class, 'materials'])->name('materials');
    });

    // Student Billing (Invoices)
    Route::prefix('student/billing')->name('student.billing.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Finance\StudentBillingController::class, 'index'])->name('index');
        Route::get('/{id}', [\App\Http\Controllers\Finance\StudentBillingController::class, 'show'])->name('show');
        Route::post('/{id}/pay', [\App\Http\Controllers\Finance\StudentBillingController::class, 'pay'])->name('pay');
    });

    // API routes for dynamic UI
    Route::prefix('api')->group(function () {
        Route::get('/classes/{id}/students', [\App\Http\Controllers\Core\ClassController::class, 'getStudents']);
        Route::get('/employees/{id}', [\App\Http\Controllers\Core\EmployeeController::class, 'lookup']);
        Route::get('/students/{id}', [\App\Http\Controllers\Core\StudentController::class, 'lookup']);
    });

    // System Settings Management
    Route::prefix('settings')->name('settings.')->middleware('role:ADMINISTRATOR,DIRECTOR')->group(function () {
        Route::get('/', [\App\Http\Controllers\Core\SystemSettingController::class, 'index'])->name('index');
        Route::post('/update', [\App\Http\Controllers\Core\SystemSettingController::class, 'update'])->name('update');
        Route::post('/test-email', [\App\Http\Controllers\Core\SystemSettingController::class, 'sendTestEmail'])->name('test_email');
    });

        Route::prefix('academic')->group(function () {
        $academicControllers = [
            'announcements' => \App\Http\Controllers\Academic\AnnouncementController::class,
            'assessments' => \App\Http\Controllers\Academic\AssessmentController::class,
            'assignments' => \App\Http\Controllers\Academic\AssignmentController::class,
            'attendances' => \App\Http\Controllers\Academic\AttendanceController::class,
            'schedules' => \App\Http\Controllers\Academic\ScheduleController::class,
            'scores' => \App\Http\Controllers\Academic\ScoreController::class,
            'subjects' => \App\Http\Controllers\Academic\SubjectController::class,
            'submissions' => \App\Http\Controllers\Academic\SubmissionController::class,
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

    // Teacher Academic Workspace
    Route::prefix('teacher/workspace')->name('teacher.workspace.')->group(function () {
        Route::get('/classes', [\App\Http\Controllers\Academic\TeacherWorkspaceController::class, 'myClasses'])->name('classes');
        Route::get('/calendar', [\App\Http\Controllers\Academic\TeacherWorkspaceController::class, 'calendar'])->name('calendar');
        Route::get('/reports', [\App\Http\Controllers\Academic\TeacherWorkspaceController::class, 'reports'])->name('reports');
    });

    // Student Academic Workspace (Progress, Schedule)
    Route::prefix('student')->name('student.')->group(function () {
        Route::get('/schedule', [\App\Http\Controllers\Academic\StudentWorkspaceController::class, 'mySchedule'])->name('schedule');
        Route::get('/progress', [\App\Http\Controllers\Academic\StudentWorkspaceController::class, 'progress'])->name('progress');
        Route::get('/subjects', [\App\Http\Controllers\Academic\StudentWorkspaceController::class, 'mySubjects'])->name('subjects');
        Route::get('/calendar', [\App\Http\Controllers\Academic\StudentWorkspaceController::class, 'calendar'])->name('calendar');
    });

    // Student Geo-Fenced QR Attendance
    Route::prefix('attendance/student')->name('attendances.student.')->group(function () {
        Route::get('/scanner', [\App\Http\Controllers\Academic\StudentQRAttendanceController::class, 'scanner'])->name('scanner');
        Route::post('/scan', [\App\Http\Controllers\Academic\StudentQRAttendanceController::class, 'scan'])->name('scan');
        Route::get('/token', [\App\Http\Controllers\Academic\StudentQRAttendanceController::class, 'getDynamicToken'])->name('token');
    });

    // Profile Route
    Route::get('/profile', [\App\Http\Controllers\Core\ProfileController::class, 'index'])->name('profile.index');

    // --- DUMMY ROUTES FOR SCAFFOLDED VIEWS TO PREVENT ROUTE NOT FOUND EXCEPTIONS ---
    $dummyRoutes = [
        
        'activity' => ['index', 'export'],
        'notifications' => ['markRead', 'index', 'show', 'markAllRead', 'archive', 'destroy'],
        'search' => ['index', 'overlay', 'clearHistory'],
        'templates' => ['index', 'create', 'edit'],
        'pdf' => ['preview', 'download', 'generate'],
        
        ];

    foreach ($dummyRoutes as $prefix => $actions) {
        foreach ($actions as $action) {
            $routeName = $prefix . '.' . $action;
            if (!Route::has($routeName)) {
                Route::any('/under-construction/' . str_replace('.', '/', $routeName), function() {
                    return back()->with('warning', 'Fitur ini masih dalam tahap pengembangan (Under Construction).');
                })->name($routeName);
            }
        }
    }
});

// Dummy register route for welcome page
if (!Route::has('register')) {
    Route::get('/register', function() {
        return redirect()->route('login')->with('warning', 'Pendaftaran mandiri tidak tersedia.');
    })->name('register');
}

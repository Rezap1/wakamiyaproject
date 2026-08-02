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
use App\Http\Controllers\Core\JobOrderController;
use App\Http\Controllers\Core\InterviewController;
use App\Http\Controllers\Core\MatchingController;
use App\Http\Controllers\Core\ApplicationController;
use App\Http\Controllers\Core\DocumentController;
use App\Http\Controllers\Core\CoeController;
use App\Http\Controllers\Core\VisaController;

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
        Route::put('/{id}', [StudentController::class, 'update'])->name('update');
        Route::delete('/{id}', [StudentController::class, 'destroy'])->name('destroy');
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

    // Job Order Management (Under Construction)
    /* Route::prefix('job-orders')->name('job-orders.')->group(function () {
        Route::get('/', [JobOrderController::class, 'index'])->name('index');
        Route::get('/create', [JobOrderController::class, 'create'])->name('create');
        Route::post('/', [JobOrderController::class, 'store'])->name('store');
        Route::get('/{id}', [JobOrderController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [JobOrderController::class, 'edit'])->name('edit');
        Route::put('/{id}', [JobOrderController::class, 'update'])->name('update');
        Route::delete('/{id}', [JobOrderController::class, 'destroy'])->name('destroy');
    }); */

    // Interview Management (Under Construction)
    /* Route::prefix('interviews')->name('interviews.')->group(function () {
        Route::get('/', [InterviewController::class, 'index'])->name('index');
        Route::get('/create', [InterviewController::class, 'create'])->name('create');
        Route::post('/', [InterviewController::class, 'store'])->name('store');
        Route::get('/{id}', [InterviewController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [InterviewController::class, 'edit'])->name('edit');
        Route::put('/{id}', [InterviewController::class, 'update'])->name('update');
        Route::delete('/{id}', [InterviewController::class, 'destroy'])->name('destroy');
    }); */

    // Matching Management (Under Construction)
    /* Route::prefix('matchings')->name('matchings.')->group(function () {
        Route::get('/', [MatchingController::class, 'index'])->name('index');
        Route::get('/create', [MatchingController::class, 'create'])->name('create');
        Route::post('/', [MatchingController::class, 'store'])->name('store');
        Route::get('/{id}', [MatchingController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [MatchingController::class, 'edit'])->name('edit');
        Route::put('/{id}', [MatchingController::class, 'update'])->name('update');
        Route::delete('/{id}', [MatchingController::class, 'destroy'])->name('destroy');
    }); */

    // Application Management (Under Construction)
    /* Route::prefix('applications')->name('applications.')->group(function () {
        Route::get('/', [ApplicationController::class, 'index'])->name('index');
        Route::get('/create', [ApplicationController::class, 'create'])->name('create');
        Route::post('/', [ApplicationController::class, 'store'])->name('store');
        Route::get('/{id}', [ApplicationController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [ApplicationController::class, 'edit'])->name('edit');
        Route::put('/{id}', [ApplicationController::class, 'update'])->name('update');
        Route::delete('/{id}', [ApplicationController::class, 'destroy'])->name('destroy');
    }); */

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

    // COE Management (Under Construction)
    /* Route::prefix('coes')->name('coes.')->group(function () {
        Route::get('/', [CoeController::class, 'index'])->name('index');
        Route::get('/create', [CoeController::class, 'create'])->name('create');
        Route::post('/', [CoeController::class, 'store'])->name('store');
        Route::get('/{id}', [CoeController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [CoeController::class, 'edit'])->name('edit');
        Route::put('/{id}', [CoeController::class, 'update'])->name('update');
        Route::delete('/{id}', [CoeController::class, 'destroy'])->name('destroy');
    }); */

    // Visa Management (Under Construction)
    /* Route::prefix('visas')->name('visas.')->group(function () {
        Route::get('/', [VisaController::class, 'index'])->name('index');
        Route::get('/create', [VisaController::class, 'create'])->name('create');
        Route::post('/', [VisaController::class, 'store'])->name('store');
        Route::get('/{id}', [VisaController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [VisaController::class, 'edit'])->name('edit');
        Route::put('/{id}', [VisaController::class, 'update'])->name('update');
        Route::delete('/{id}', [VisaController::class, 'destroy'])->name('destroy');
    }); */

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
        Route::post('/{id}/pay', [\App\Http\Controllers\Hr\PayrollController::class, 'pay'])->name('pay');
        Route::get('/{id}/slip', [\App\Http\Controllers\Hr\PayrollController::class, 'slip'])->name('slip');
        Route::get('/{id}', [\App\Http\Controllers\Hr\PayrollController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [\App\Http\Controllers\Hr\PayrollController::class, 'edit'])->name('edit');
        Route::put('/{id}', [\App\Http\Controllers\Hr\PayrollController::class, 'update'])->name('update');
        Route::delete('/{id}', [\App\Http\Controllers\Hr\PayrollController::class, 'destroy'])->name('destroy');
    });

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

    // API routes for dynamic UI
    Route::prefix('api')->group(function () {
        Route::get('/classes/{id}/students', [\App\Http\Controllers\Core\ClassController::class, 'getStudents']);
    });

    // System Settings Management
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Core\SystemSettingController::class, 'index'])->name('index');
        Route::post('/update', [\App\Http\Controllers\Core\SystemSettingController::class, 'update'])->name('update');
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

    // --- DUMMY ROUTES FOR SCAFFOLDED VIEWS TO PREVENT ROUTE NOT FOUND EXCEPTIONS ---
    $dummyRoutes = [
        
        'activity' => ['index', 'export'],
        'notifications' => ['markRead', 'index', 'show', 'markAllRead', 'archive', 'destroy'],
        'academic' => ['calendar'],
        'student.billing' => ['index', 'show', 'pay'],
        'search' => ['index', 'overlay', 'clearHistory'],
        'profile' => ['index'],
        'student' => ['schedule', 'progress'],
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

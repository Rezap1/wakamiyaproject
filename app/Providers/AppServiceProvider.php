<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use App\Providers\GoogleSheetsUserProvider;
use App\Services\Core\UserService;

use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use App\Repositories\GoogleSheets\UserRepository;
use App\Interfaces\GoogleSheets\RoleRepositoryInterface;
use App\Repositories\GoogleSheets\RoleRepository;
use App\Interfaces\GoogleSheets\ActivityLogRepositoryInterface;
use App\Repositories\GoogleSheets\ActivityLogRepository;
use App\Interfaces\GoogleSheets\DepartmentRepositoryInterface;
use App\Repositories\GoogleSheets\DepartmentRepository;
use App\Interfaces\GoogleSheets\PositionRepositoryInterface;
use App\Repositories\GoogleSheets\PositionRepository;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Repositories\GoogleSheets\EmployeeRepository;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Repositories\GoogleSheets\TeacherRepository;
use App\Interfaces\GoogleSheets\ProgramRepositoryInterface;
use App\Repositories\GoogleSheets\ProgramRepository;
use App\Interfaces\GoogleSheets\BatchRepositoryInterface;
use App\Repositories\GoogleSheets\BatchRepository;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Repositories\GoogleSheets\ClassRepository;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Repositories\GoogleSheets\StudentRepository;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Repositories\GoogleSheets\CompanyRepository;
use App\Interfaces\GoogleSheets\ModuleRepositoryInterface;
use App\Repositories\GoogleSheets\ModuleRepository;
use App\Interfaces\GoogleSheets\DocumentRepositoryInterface;
use App\Repositories\GoogleSheets\DocumentRepository;

use App\Interfaces\GoogleSheets\AcademicYearRepositoryInterface;
use App\Repositories\GoogleSheets\AcademicYearRepository;
use App\Interfaces\GoogleSheets\ClassEnrollmentRepositoryInterface;
use App\Repositories\GoogleSheets\ClassEnrollmentRepository;
use App\Interfaces\GoogleSheets\SubjectRepositoryInterface;
use App\Repositories\GoogleSheets\SubjectRepository;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Repositories\GoogleSheets\ScheduleRepository;
use App\Interfaces\GoogleSheets\AttendanceRepositoryInterface;
use App\Repositories\GoogleSheets\AttendanceRepository;
use App\Interfaces\GoogleSheets\ScoreRepositoryInterface;
use App\Repositories\GoogleSheets\ScoreRepository;
use App\Interfaces\GoogleSheets\AssignmentRepositoryInterface;
use App\Repositories\GoogleSheets\AssignmentRepository;
use App\Interfaces\GoogleSheets\SubmissionRepositoryInterface;
use App\Repositories\GoogleSheets\SubmissionRepository;
use App\Interfaces\GoogleSheets\AnnouncementRepositoryInterface;
use App\Repositories\GoogleSheets\AnnouncementRepository;
use App\Interfaces\GoogleSheets\NotificationRepositoryInterface;
use App\Repositories\GoogleSheets\NotificationRepository;
use App\Interfaces\GoogleSheets\AssessmentRepositoryInterface;
use App\Repositories\GoogleSheets\AssessmentRepository;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Repositories\GoogleSheets\InvoiceRepository;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\AccountRepositoryInterface;
use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Repositories\GoogleSheets\PaymentRepository;
use App\Repositories\GoogleSheets\AccountRepository;
use App\Repositories\GoogleSheets\TransactionRepository;

// Phase 9.3 & 9.4 Bindings
use App\Interfaces\GoogleSheets\PayrollRepositoryInterface;
use App\Repositories\GoogleSheets\PayrollRepository;
use App\Interfaces\GoogleSheets\SalaryComponentRepositoryInterface;
use App\Repositories\GoogleSheets\SalaryComponentRepository;
use App\Interfaces\GoogleSheets\DocumentTemplateRepositoryInterface;
use App\Repositories\GoogleSheets\DocumentTemplateRepository;

use App\Interfaces\GoogleSheets\WorkflowRepositoryInterface;
use App\Repositories\GoogleSheets\WorkflowRepository;
use App\Interfaces\GoogleSheets\ApprovalRepositoryInterface;
use App\Repositories\GoogleSheets\ApprovalRepository;
use App\Interfaces\GoogleSheets\ApprovalHistoryRepositoryInterface;
use App\Repositories\GoogleSheets\ApprovalHistoryRepository;

use App\Interfaces\GoogleSheets\AuditLogRepositoryInterface;
use App\Repositories\GoogleSheets\AuditLogRepository;

use App\Interfaces\GoogleSheets\SystemSettingRepositoryInterface;
use App\Repositories\GoogleSheets\SystemSettingRepository;
use App\Interfaces\GoogleSheets\SystemParameterRepositoryInterface;
use App\Repositories\GoogleSheets\SystemParameterRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);
        $this->app->bind(ActivityLogRepositoryInterface::class, ActivityLogRepository::class);
        $this->app->bind(DepartmentRepositoryInterface::class, DepartmentRepository::class);
        $this->app->bind(PositionRepositoryInterface::class, PositionRepository::class);
        $this->app->bind(EmployeeRepositoryInterface::class, EmployeeRepository::class);
        $this->app->bind(TeacherRepositoryInterface::class, TeacherRepository::class);
        $this->app->bind(ProgramRepositoryInterface::class, ProgramRepository::class);
        $this->app->bind(BatchRepositoryInterface::class, BatchRepository::class);
        $this->app->bind(ClassRepositoryInterface::class, ClassRepository::class);
        $this->app->bind(StudentRepositoryInterface::class, StudentRepository::class);
        $this->app->bind(CompanyRepositoryInterface::class, CompanyRepository::class);
        $this->app->bind(ModuleRepositoryInterface::class, ModuleRepository::class);
        $this->app->bind(DocumentRepositoryInterface::class, DocumentRepository::class);
        $this->app->bind(AcademicYearRepositoryInterface::class, AcademicYearRepository::class);
        $this->app->bind(ClassEnrollmentRepositoryInterface::class, ClassEnrollmentRepository::class);
        $this->app->bind(SubjectRepositoryInterface::class, SubjectRepository::class);
        $this->app->bind(ScheduleRepositoryInterface::class, ScheduleRepository::class);
        $this->app->bind(AttendanceRepositoryInterface::class, AttendanceRepository::class);
        $this->app->bind(ScoreRepositoryInterface::class, ScoreRepository::class);
        $this->app->bind(AssignmentRepositoryInterface::class, AssignmentRepository::class);
        $this->app->bind(SubmissionRepositoryInterface::class, SubmissionRepository::class);
        $this->app->bind(AnnouncementRepositoryInterface::class, AnnouncementRepository::class);
        $this->app->bind(NotificationRepositoryInterface::class, NotificationRepository::class);
        $this->app->bind(AssessmentRepositoryInterface::class, AssessmentRepository::class);
        $this->app->bind(InvoiceRepositoryInterface::class, InvoiceRepository::class);
        $this->app->bind(PaymentRepositoryInterface::class, PaymentRepository::class);
        $this->app->bind(AccountRepositoryInterface::class, AccountRepository::class);
        $this->app->bind(TransactionRepositoryInterface::class, TransactionRepository::class);
        $this->app->bind(PayrollRepositoryInterface::class, PayrollRepository::class);
        $this->app->bind(SalaryComponentRepositoryInterface::class, SalaryComponentRepository::class);
        $this->app->bind(DocumentTemplateRepositoryInterface::class, DocumentTemplateRepository::class);
    $this->app->bind(WorkflowRepositoryInterface::class, WorkflowRepository::class);
        $this->app->bind(ApprovalRepositoryInterface::class, ApprovalRepository::class);
        $this->app->bind(ApprovalHistoryRepositoryInterface::class, ApprovalHistoryRepository::class);
            $this->app->bind(AuditLogRepositoryInterface::class, AuditLogRepository::class);
            $this->app->bind(SystemSettingRepositoryInterface::class, SystemSettingRepository::class);
        $this->app->bind(SystemParameterRepositoryInterface::class, SystemParameterRepository::class);
            $this->app->singleton(\App\Services\Core\EnterpriseAutomationService::class, function ($app) { return new \App\Services\Core\EnterpriseAutomationService(); });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Auth::provider('google_sheets', function ($app, array $config) {
            return new GoogleSheetsUserProvider($app->make(UserService::class));
        });


    }
}

<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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
use App\Interfaces\GoogleSheets\PermissionRepositoryInterface;
use App\Repositories\GoogleSheets\PermissionRepository;
use App\Interfaces\GoogleSheets\JobOrderRepositoryInterface;
use App\Repositories\GoogleSheets\JobOrderRepository;
use App\Interfaces\GoogleSheets\InterviewRepositoryInterface;
use App\Repositories\GoogleSheets\InterviewRepository;
use App\Interfaces\GoogleSheets\MatchingRepositoryInterface;
use App\Repositories\GoogleSheets\MatchingRepository;
use App\Interfaces\GoogleSheets\ApplicationRepositoryInterface;
use App\Repositories\GoogleSheets\ApplicationRepository;
use App\Interfaces\GoogleSheets\DocumentRepositoryInterface;
use App\Repositories\GoogleSheets\DocumentRepository;
use App\Interfaces\GoogleSheets\CoeRepositoryInterface;
use App\Repositories\GoogleSheets\CoeRepository;
use App\Interfaces\GoogleSheets\VisaRepositoryInterface;
use App\Repositories\GoogleSheets\VisaRepository;

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
        $this->app->bind(PermissionRepositoryInterface::class, PermissionRepository::class);
        $this->app->bind(JobOrderRepositoryInterface::class, JobOrderRepository::class);
        $this->app->bind(InterviewRepositoryInterface::class, InterviewRepository::class);
        $this->app->bind(MatchingRepositoryInterface::class, MatchingRepository::class);
        $this->app->bind(ApplicationRepositoryInterface::class, ApplicationRepository::class);
        $this->app->bind(DocumentRepositoryInterface::class, DocumentRepository::class);
        $this->app->bind(CoeRepositoryInterface::class, CoeRepository::class);
        $this->app->bind(VisaRepositoryInterface::class, VisaRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('google_sheets', function ($app, array $config) {
            return new GoogleSheetsUserProvider($app->make(UserService::class));
        });

        Gate::define('permission', function ($user, $moduleCode, $action = 'view') {
            if (!isset($user->Role_ID)) return false;

            $moduleRepo = app(ModuleRepositoryInterface::class);
            $permissionRepo = app(PermissionRepositoryInterface::class);

            $module = $moduleRepo->fetchAll()->firstWhere('Module_Code', $moduleCode);
            if (!$module) return false;

            $permission = $permissionRepo->findByRoleAndModule($user->Role_ID, $module['Module_ID']);
            if (!$permission || ($permission['Is_Active'] ?? 'TRUE') === 'FALSE') return false;

            $actionMap = [
                'view' => 'Can_View',
                'create' => 'Can_Create',
                'edit' => 'Can_Edit',
                'delete' => 'Can_Delete',
                'print' => 'Can_Print',
                'export' => 'Can_Export_PDF',
            ];

            $column = $actionMap[$action] ?? 'Can_View';

            return ($permission[$column] ?? 'FALSE') === 'TRUE';
        });
    }
}

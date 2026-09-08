<?php

namespace Tests\Feature;

use App\Http\Middleware\ProtectMassAssignment;
use App\Http\Requests\StoreAssignmentRequest;
use App\Http\Requests\UpdateAssignmentRequest;
use App\Interfaces\GoogleSheets\AssessmentRepositoryInterface;
use App\Interfaces\GoogleSheets\AssignmentRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassEnrollmentRepositoryInterface;
use App\Interfaces\GoogleSheets\ClassRepositoryInterface;
use App\Interfaces\GoogleSheets\ScheduleRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\TeacherRepositoryInterface;
use App\Repositories\GoogleSheets\ClassRepository;
use App\Repositories\GoogleSheets\TeacherRepository;
use App\Services\Core\AssignmentService;
use App\Services\Core\EnterpriseEventService;
use App\Services\Core\RoleService;
use App\Support\Academic\AssignmentStatus;
use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class AssignmentLiveStatusSubmissionTest extends TestCase
{
    public function test_authenticated_teacher_can_submit_default_published_assignment_through_actual_route(): void
    {
        $this->bindTeacherRouteDependencies();

        $repository = new AssignmentLiveMemoryRepository();
        $events = Mockery::mock(EnterpriseEventService::class);
        $events->shouldReceive('dispatch')->once();
        $assignmentService = new AssignmentService($repository, $events);
        $this->app->instance(AssignmentService::class, $assignmentService);

        $this->actingAs(new GenericUser([
            'id' => 'USR-1',
            'User_ID' => 'USR-1',
            'Role_ID' => 'ROLE-TEACHER',
            'Role' => 'TEACHER',
        ]));

        $this->post(route('assignments.store'), [
            'Title' => 'Tugas Bahasa',
            'Class_ID' => 'CLS-A',
            // A forged teacher value must be replaced by the authenticated profile.
            'Teacher_ID' => 'TCH-FORGED',
            'Deadline' => '2026-09-09T17:40',
            // This is exactly what the rendered default option submits.
            'Status' => AssignmentStatus::PUBLISHED,
            'Description' => 'Instruksi',
        ])->assertRedirect(route('teacher.workspace.assignments'));

        $persisted = $repository->findByIdFresh('ASN000001');
        $this->assertSame(AssignmentStatus::PUBLISHED, $persisted['Status']);
        $this->assertSame('TCH-1', $persisted['Teacher_ID']);
        $this->assertSame('CLS-A', $persisted['Class_ID']);
        $this->assertSame('2026-09-09T17:40', $persisted['Deadline']);
    }

    public function test_assignment_status_is_not_removed_by_mass_assignment_guard(): void
    {
        foreach ([
            'assignments.store',
            'assignments.update',
            'announcements.update',
            'assessments.update',
            'attendances.update',
        ] as $routeName) {
            $request = Request::create('/academic/assignments', 'POST', [
                'Status' => AssignmentStatus::PUBLISHED,
            ]);
            $route = (new Route('POST', '/academic/assignments', fn () => response('ok')))->name($routeName);
            $request->setRouteResolver(fn () => $route);

            $captured = null;
            (new ProtectMassAssignment())->handle($request, function (Request $nextRequest) use (&$captured) {
                $captured = $nextRequest->all();
                return response('ok');
            });

            $this->assertSame(AssignmentStatus::PUBLISHED, $captured['Status'] ?? null, $routeName);
        }
    }

    public function test_unrelated_routes_still_strip_protected_status_field(): void
    {
        $request = Request::create('/somewhere', 'POST', ['Status' => AssignmentStatus::PUBLISHED]);
        $route = (new Route('POST', '/somewhere', fn () => response('ok')))->name('somewhere.store');
        $request->setRouteResolver(fn () => $route);

        $captured = null;
        (new ProtectMassAssignment())->handle($request, function (Request $nextRequest) use (&$captured) {
            $captured = $nextRequest->all();
            return response('ok');
        });

        $this->assertArrayNotHasKey('Status', $captured);
    }

    public function test_rendered_status_control_keeps_indonesian_labels_and_canonical_values(): void
    {
        View::share('errors', new ViewErrorBag());
        $html = Blade::render(
            '<x-select name="Status" label="Status Publikasi" required>'
            . '<option value="PUBLISHED" selected>Terpublikasi</option>'
            . '<option value="DRAFT">Draf</option>'
            . '</x-select>',
            ['errors' => new ViewErrorBag()]
        );

        $this->assertMatchesRegularExpression('/<select[^>]+name="Status"[^>]*>/', $html);
        $this->assertStringContainsString('<option value="PUBLISHED" selected>Terpublikasi</option>', $html);
        $this->assertStringContainsString('<option value="DRAFT">Draf</option>', $html);
        $this->assertDoesNotMatchRegularExpression('/\sdisabled(?:\s|=|>)/i', $html);
    }

    public function test_actual_assignment_create_route_renders_default_published_control(): void
    {
        $this->bindTeacherRouteDependencies();
        $this->actingAs(new GenericUser([
            'id' => 'USR-1',
            'User_ID' => 'USR-1',
            'Role_ID' => 'ROLE-TEACHER',
            'Role' => 'TEACHER',
        ]));

        $response = $this->get(route('assignments.create'));

        $response->assertOk();
        $response->assertSee('name="Status"', false);
        $response->assertSee('value="PUBLISHED" selected', false);
        $response->assertSee('>Terpublikasi</option>', false);
        $response->assertSee('value="DRAFT"', false);
        $response->assertSee('>Draf</option>', false);

        $oldInputResponse = $this->withSession(['_old_input' => ['Status' => AssignmentStatus::DRAFT]])
            ->get(route('assignments.create'));
        $oldInputResponse->assertOk();
        $oldInputResponse->assertSee('value="DRAFT" selected', false);
    }

    public function test_store_request_requires_valid_canonical_status_and_accepts_indonesian_aliases(): void
    {
        $base = [
            'Title' => 'Tugas',
            'Class_ID' => 'CLS-A',
            'Teacher_ID' => 'TCH-1',
            'Deadline' => '2026-09-09T17:40',
        ];

        $published = $this->validatedStore(array_merge($base, ['Status' => AssignmentStatus::PUBLISHED]));
        $this->assertSame(AssignmentStatus::PUBLISHED, $published['Status']);

        $draft = $this->validatedStore(array_merge($base, ['Status' => AssignmentStatus::DRAFT]));
        $this->assertSame(AssignmentStatus::DRAFT, $draft['Status']);

        $indonesianPublished = $this->validatedStore(array_merge($base, ['Status' => 'Terpublikasi']));
        $this->assertSame(AssignmentStatus::PUBLISHED, $indonesianPublished['Status']);

        $indonesianDraft = $this->validatedStore(array_merge($base, ['Status' => 'Draf']));
        $this->assertSame(AssignmentStatus::DRAFT, $indonesianDraft['Status']);

        $lowercaseStatus = $this->validatedStore(array_merge($base, ['status' => AssignmentStatus::PUBLISHED]));
        $this->assertSame(AssignmentStatus::PUBLISHED, $lowercaseStatus['Status']);

        $missing = $this->makeRequest(StoreAssignmentRequest::class, $base);
        $this->invokePrepareForValidation($missing);
        $missingValidator = Validator::make($missing->all(), $missing->rules());
        $this->assertFalse($missingValidator->passes());
        $this->assertSame('Isian Status Publikasi wajib diisi.', $missingValidator->errors()->first('Status'));

        $invalid = $this->makeRequest(StoreAssignmentRequest::class, array_merge($base, ['Status' => 'UNKNOWN']));
        $this->invokePrepareForValidation($invalid);
        $this->assertFalse(Validator::make($invalid->all(), $invalid->rules())->passes());
    }

    public function test_validation_failure_preserves_old_status_as_canonical_value(): void
    {
        $request = $this->makeRequest(StoreAssignmentRequest::class, [
            'Title' => '',
            'Class_ID' => 'CLS-A',
            'Teacher_ID' => 'TCH-1',
            'Deadline' => '2026-09-09T17:40',
            'Status' => 'Draf',
        ]);
        $this->invokePrepareForValidation($request);

        $this->assertSame(AssignmentStatus::DRAFT, $request->input('Status'));
        $this->assertFalse(Validator::make($request->all(), $request->rules())->passes());
    }

    public function test_edit_request_preserves_published_and_supports_both_status_transitions(): void
    {
        $base = [
            'Title' => 'Tugas',
            'Class_ID' => 'CLS-A',
            'Teacher_ID' => 'TCH-1',
            'Deadline' => '2026-09-09T17:40',
        ];

        foreach ([AssignmentStatus::PUBLISHED, AssignmentStatus::DRAFT] as $status) {
            $request = $this->makeRequest(UpdateAssignmentRequest::class, array_merge($base, ['Status' => $status]));
            $this->invokePrepareForValidation($request);
            $validated = $this->validatedRequest($request);
            $this->assertSame($status, $validated['Status']);
        }
    }

    public function test_assignment_service_persists_published_and_draft_edit_transitions(): void
    {
        $repository = new AssignmentLiveMemoryRepository();
        $events = Mockery::mock(EnterpriseEventService::class);
        $events->shouldReceive('dispatch')->zeroOrMoreTimes();
        $service = new AssignmentService($repository, $events);

        $service->create([
            'Title' => 'Tugas Terpublikasi',
            'Class_ID' => 'CLS-A',
            'Teacher_ID' => 'TCH-1',
            'Deadline' => '2026-09-09T17:40',
            'Status' => AssignmentStatus::PUBLISHED,
            'Description' => 'Instruksi',
        ]);
        $service->create([
            'Assignment_ID' => 'ASN-DRAFT',
            'Title' => 'Tugas Draf',
            'Class_ID' => 'CLS-A',
            'Teacher_ID' => 'TCH-1',
            'Deadline' => '2026-09-10T17:40',
            'Status' => AssignmentStatus::DRAFT,
            'Description' => 'Instruksi',
        ]);

        $this->assertSame(AssignmentStatus::PUBLISHED, $repository->findByIdFresh('ASN000001')['Status']);
        $this->assertSame(AssignmentStatus::DRAFT, $repository->findByIdFresh('ASN-DRAFT')['Status']);

        $service->update('ASN000001', [
            'Title' => 'Tugas Tetap Terpublikasi',
            'Class_ID' => 'CLS-A',
            'Teacher_ID' => 'TCH-1',
            'Deadline' => '2026-09-09T17:40',
            'Status' => AssignmentStatus::PUBLISHED,
            'Description' => 'Instruksi',
        ]);
        $this->assertSame(AssignmentStatus::PUBLISHED, $repository->findByIdFresh('ASN000001')['Status']);

        $service->update('ASN000001', [
            'Title' => 'Tugas Menjadi Draf',
            'Class_ID' => 'CLS-A',
            'Teacher_ID' => 'TCH-1',
            'Deadline' => '2026-09-09T17:40',
            'Status' => AssignmentStatus::DRAFT,
            'Description' => 'Instruksi',
        ]);
        $this->assertSame(AssignmentStatus::DRAFT, $repository->findByIdFresh('ASN000001')['Status']);

        $service->update('ASN-DRAFT', [
            'Title' => 'Tugas Kembali Terpublikasi',
            'Class_ID' => 'CLS-A',
            'Teacher_ID' => 'TCH-1',
            'Deadline' => '2026-09-10T17:40',
            'Status' => AssignmentStatus::PUBLISHED,
            'Description' => 'Instruksi',
        ]);
        $this->assertSame(AssignmentStatus::PUBLISHED, $repository->findByIdFresh('ASN-DRAFT')['Status']);
    }

    public function test_assignment_service_rejects_missing_status_instead_of_defaulting_it(): void
    {
        $repository = new AssignmentLiveMemoryRepository();
        $events = Mockery::mock(EnterpriseEventService::class);
        $events->shouldNotReceive('dispatch');
        $service = new AssignmentService($repository, $events);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Status tugas wajib diisi');
        $service->create([
            'Assignment_ID' => 'ASN-MISSING-STATUS',
            'Title' => 'Tugas Tanpa Status',
            'Class_ID' => 'CLS-A',
            'Teacher_ID' => 'TCH-1',
            'Deadline' => '2026-09-09T17:40',
        ]);
    }

    public function test_teacher_cannot_forge_class_outside_active_teaching_scope(): void
    {
        $this->bindTeacherRouteDependencies();
        $assignmentService = Mockery::mock(AssignmentService::class);
        $assignmentService->shouldNotReceive('create');
        $this->app->instance(AssignmentService::class, $assignmentService);
        $this->actingAs(new GenericUser([
            'id' => 'USR-1',
            'User_ID' => 'USR-1',
            'Role_ID' => 'ROLE-TEACHER',
            'Role' => 'TEACHER',
        ]));

        $this->post(route('assignments.store'), [
            'Title' => 'Tugas Tidak Sah',
            'Class_ID' => 'CLS-OUTSIDE',
            'Teacher_ID' => 'TCH-1',
            'Deadline' => '2026-09-09T17:40',
            'Status' => AssignmentStatus::PUBLISHED,
        ])->assertForbidden();
    }

    public function test_authenticated_teacher_can_edit_published_assignment_without_touching_status_and_then_change_it(): void
    {
        $this->bindTeacherRouteDependencies();
        $repository = new AssignmentLiveMemoryRepository();
        $repository->seed([
            'Assignment_ID' => 'ASN-EDIT',
            'Title' => 'Tugas Lama',
            'Class_ID' => 'CLS-A',
            'Teacher_ID' => 'TCH-1',
            'Deadline' => '2026-09-09T17:40',
            'Status' => AssignmentStatus::PUBLISHED,
            'Description' => 'Instruksi',
        ]);
        $events = Mockery::mock(EnterpriseEventService::class);
        $events->shouldReceive('dispatch')->twice();
        $this->app->instance(AssignmentService::class, new AssignmentService($repository, $events));
        $this->actingAs(new GenericUser([
            'id' => 'USR-1',
            'User_ID' => 'USR-1',
            'Role_ID' => 'ROLE-TEACHER',
            'Role' => 'TEACHER',
        ]));

        $payload = [
            'Title' => 'Tugas Diperbarui',
            'Class_ID' => 'CLS-A',
            'Teacher_ID' => 'TCH-FORGED',
            'Deadline' => '2026-09-09T17:40',
            'Description' => 'Instruksi baru',
        ];
        $this->put(route('assignments.update', 'ASN-EDIT'), array_merge($payload, [
            'Status' => AssignmentStatus::PUBLISHED,
        ]))->assertRedirect(route('teacher.workspace.assignments'));
        $this->assertSame(AssignmentStatus::PUBLISHED, $repository->findByIdFresh('ASN-EDIT')['Status']);
        $this->assertSame('TCH-1', $repository->findByIdFresh('ASN-EDIT')['Teacher_ID']);

        $this->put(route('assignments.update', 'ASN-EDIT'), array_merge($payload, [
            'Status' => AssignmentStatus::DRAFT,
        ]))->assertRedirect(route('teacher.workspace.assignments'));
        $this->assertSame(AssignmentStatus::DRAFT, $repository->findByIdFresh('ASN-EDIT')['Status']);
    }

    private function validatedStore(array $payload): array
    {
        $request = $this->makeRequest(StoreAssignmentRequest::class, $payload);
        $this->invokePrepareForValidation($request);

        return $this->validatedRequest($request);
    }

    private function validatedRequest(FormRequest $request): array
    {
        $validator = Validator::make($request->all(), $request->rules());
        $this->assertTrue($validator->passes(), var_export($validator->errors()->toArray(), true));

        return $validator->validated();
    }

    private function makeRequest(string $requestClass, array $payload): FormRequest
    {
        /** @var FormRequest $request */
        $request = $requestClass::create('/academic/assignments', 'POST', $payload);
        $request->setContainer($this->app);

        return $request;
    }

    private function invokePrepareForValidation(FormRequest $request): void
    {
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);
    }

    private function bindTeacherRouteDependencies(): void
    {
        $roleService = Mockery::mock(RoleService::class);
        $roleService->shouldReceive('getRoleById')->zeroOrMoreTimes()->andReturn([
            'Role_ID' => 'ROLE-TEACHER',
            'Role_Name' => 'TEACHER',
            'Is_Active' => 'TRUE',
        ]);
        $this->app->instance(RoleService::class, $roleService);

        $teacherRows = collect([
            ['Teacher_ID' => 'TCH-1', 'User_ID' => 'USR-1', 'Full_Name' => 'Guru Satu'],
        ]);
        $classRows = collect([
            ['Class_ID' => 'CLS-A', 'Class_Name' => 'Kelas A', 'Is_Active' => 'TRUE'],
        ]);

        $teacherRepository = Mockery::mock(TeacherRepository::class);
        $teacherRepository->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn($teacherRows);
        $this->app->instance(TeacherRepository::class, $teacherRepository);

        $classRepository = Mockery::mock(ClassRepository::class);
        $classRepository->shouldReceive('fetchAll')->zeroOrMoreTimes()->andReturn($classRows);
        $this->app->instance(ClassRepository::class, $classRepository);

        $this->bindRepository(TeacherRepositoryInterface::class, $teacherRows, 'fetchAll');
        $this->bindRepository(ClassRepositoryInterface::class, $classRows, 'fetchAll');
        $this->bindRepository(ScheduleRepositoryInterface::class, collect([
            ['Schedule_ID' => 'SCH-1', 'Teacher_ID' => 'TCH-1', 'Class_ID' => 'CLS-A', 'Subject_ID' => 'SUB-1', 'Is_Active' => 'TRUE'],
        ]), 'fetchAll');
        $this->bindRepository(StudentRepositoryInterface::class, collect([]), 'fetchAll');
        $this->bindRepository(ClassEnrollmentRepositoryInterface::class, collect([]), 'fetchAll');

        $assessmentRepository = Mockery::mock(AssessmentRepositoryInterface::class);
        $assessmentRepository->shouldReceive('getAll')->zeroOrMoreTimes()->andReturn(collect([]));
        $this->app->instance(AssessmentRepositoryInterface::class, $assessmentRepository);
    }

    private function bindRepository(string $interface, $rows, string $method): void
    {
        $repository = Mockery::mock($interface);
        $repository->shouldReceive($method)->zeroOrMoreTimes()->andReturn($rows);
        $this->app->instance($interface, $repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

final class AssignmentLiveMemoryRepository implements AssignmentRepositoryInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $rows = [];

    public function fetchAll()
    {
        return collect(array_values($this->rows));
    }

    public function seed(array $row): void
    {
        $this->rows[(string) $row['Assignment_ID']] = $row;
    }

    public function findById(string $id)
    {
        return $this->rows[$id] ?? null;
    }

    public function findByIdFresh($id)
    {
        return $this->findById((string) $id);
    }

    public function generateNewId(string $prefix, int $padding = 6): string
    {
        return 'ASN000001';
    }

    public function create(array $data)
    {
        $this->rows[(string) $data['Assignment_ID']] = $data;
        return true;
    }

    public function update(string $id, array $data)
    {
        $this->rows[$id] = array_merge($this->rows[$id] ?? [], $data);
        return true;
    }

    public function softDelete(string $id)
    {
        unset($this->rows[$id]);
        return true;
    }

    public function clearCache(): void
    {
    }
}

<?php

namespace Tests\Feature;

use App\Interfaces\GoogleSheets\RoleRepositoryInterface;
use App\Services\Quiz\QuizMediaService;
use App\Services\Quiz\QuizScopeService;
use App\Services\Quiz\QuizService;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class H877QuizRoleAuthorizationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_quiz_rate_limits_have_distinct_operation_namespaces(): void
    {
        $expected = [
            'teacher.quizzes.media.upload' => 'throttle:30,1,quiz-media-upload',
            'teacher.quizzes.media.staging' => 'throttle:120,1,quiz-media-staging',
            'teacher.quizzes.media' => 'throttle:240,1,quiz-teacher-media',
            'teacher.quizzes.reset-results' => 'throttle:10,1,quiz-reset-results',
            'student.quizzes.start' => 'throttle:20,1,quiz-start',
            'student.quizzes.attempt-media' => 'throttle:600,1,quiz-attempt-media',
            'student.quizzes.autosave' => 'throttle:120,1,quiz-autosave',
            'student.quizzes.submit' => 'throttle:10,1,quiz-submit',
            'student.quizzes.result-media' => 'throttle:600,1,quiz-result-media',
        ];

        foreach ($expected as $name => $middleware) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Route {$name} must exist.");
            $this->assertContains($middleware, $route->gatherMiddleware(), "Route {$name} must not share another quiz operation's throttle counter.");
        }

        $this->assertCount(count($expected), array_unique(array_values($expected)));
    }

    #[DataProvider('nonTeacherRoles')]
    public function test_non_teachers_cannot_access_teacher_quiz_crud(string $role): void
    {
        $this->actingAsRole($role);
        $this->get(route('teacher.quizzes.index'))->assertForbidden();
        $this->post(route('teacher.quizzes.store'), [])->assertForbidden();
        $this->get(route('teacher.quizzes.class', 'FORGED'))->assertForbidden();
        $this->get(route('teacher.quizzes.class.create', 'FORGED'))->assertForbidden();
        $this->post(route('teacher.quizzes.class.store', 'FORGED'), [])->assertForbidden();
        $this->get(route('teacher.quizzes.results'))->assertForbidden();
        $this->get(route('teacher.quizzes.result', 'FORGED'))->assertForbidden();
        $this->get(route('teacher.quizzes.leaderboard', 'FORGED'))->assertForbidden();
        $this->put(route('teacher.quizzes.update', 'FORGED'), [])->assertForbidden();
        $this->post(route('teacher.quizzes.reset-results', 'FORGED'))->assertForbidden();
        $this->delete(route('teacher.quizzes.destroy', 'FORGED'))->assertForbidden();
        $this->post(route('teacher.quizzes.media.upload'))->assertForbidden();
        $this->get(route('teacher.quizzes.media', ['FORGED', 'FORGED', 'image']))->assertForbidden();
    }

    #[DataProvider('nonStudentRoles')]
    public function test_non_students_cannot_access_student_quiz_flow(string $role): void
    {
        $this->actingAsRole($role);
        $this->get(route('student.quizzes.index'))->assertForbidden();
        $this->post(route('student.quizzes.start', 'FORGED'))->assertForbidden();
        $this->get(route('student.quizzes.play', 'FORGED'))->assertForbidden();
        $this->postJson(route('student.quizzes.autosave', 'FORGED'), ['version' => 1, 'answers' => []])->assertForbidden();
        $this->post(route('student.quizzes.submit', 'FORGED'))->assertForbidden();
        $this->get(route('student.quizzes.attempt-media', ['FORGED', 'FORGED', 'audio']))->assertForbidden();
    }

    public function test_student_autosave_endpoint_returns_confirmed_server_version(): void
    {
        $this->actingAsRole('STUDENT');
        $scope = Mockery::mock(QuizScopeService::class);
        $scope->shouldReceive('studentForUser')->once()->andReturn(['Student_ID' => 'S1', 'Class_ID' => 'C1']);
        $quizzes = Mockery::mock(QuizService::class);
        $quizzes->shouldReceive('autosave')->once()->with('A1', ['Student_ID' => 'S1', 'Class_ID' => 'C1'], ['Q1' => 'A'], 4)->andReturn([
            'saved' => true,
            'idempotent' => false,
            'version' => 4,
            'answers' => ['Q1' => 'A'],
            'saved_at' => '2026-11-10 20:55:00',
        ]);
        $this->app->instance(QuizScopeService::class, $scope);
        $this->app->instance(QuizService::class, $quizzes);

        $this->postJson(route('student.quizzes.autosave', 'A1'), [
            'version' => 4,
            'answers' => ['Q1' => 'A'],
        ])->assertOk()->assertJsonPath('saved', true)->assertJsonPath('version', 4);
    }

    public function test_authenticated_media_endpoint_supports_full_and_range_responses(): void
    {
        $this->actingAsRole('STUDENT');
        $scope = Mockery::mock(QuizScopeService::class);
        $scope->shouldReceive('studentForUser')->twice()->andReturn(['Student_ID' => 'S1', 'Class_ID' => 'C1']);
        $quizzes = Mockery::mock(QuizService::class);
        $quizzes->shouldReceive('attemptMedia')->twice()->andReturn(['path' => 'quiz-media/Q1/Q1/audio.mp3', 'mime' => 'audio/mpeg']);
        $media = Mockery::mock(QuizMediaService::class);
        $path = tempnam(sys_get_temp_dir(), 'quiz-range-');
        file_put_contents($path, '0123456789');
        $this->beforeApplicationDestroyed(static fn () => @unlink($path));
        $media->shouldReceive('absolutePath')->twice()->andReturn($path);
        $this->app->instance(QuizScopeService::class, $scope);
        $this->app->instance(QuizService::class, $quizzes);
        $this->app->instance(QuizMediaService::class, $media);

        $url = route('student.quizzes.attempt-media', ['A1', 'Q1', 'audio']);
        $this->get($url)->assertOk()
            ->assertHeader('Content-Type', 'audio/mpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->withHeader('Range', 'bytes=2-5')->get($url)
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 2-5/10')
            ->assertHeader('Accept-Ranges', 'bytes');
    }

    public function test_guest_cannot_access_media_routes(): void
    {
        $this->get(route('student.quizzes.attempt-media', ['A1', 'Q1', 'audio']))->assertRedirect(route('login'));
        $this->get(route('teacher.quizzes.media', ['Q1', 'Q1', 'image']))->assertRedirect(route('login'));
    }

    public function test_teacher_quiz_form_allows_same_origin_microphone_capture(): void
    {
        $this->actingAsRole('TEACHER');
        $scope = Mockery::mock(QuizScopeService::class);
        $scope->shouldReceive('teacherForUser')->once()->andReturn(['Teacher_ID' => 'T1']);
        $scope->shouldReceive('classesForTeacher')->once()->with('T1')->andReturn(collect([
            ['Class_ID' => 'C1', 'Class_Name' => 'Kelas A'],
        ]));
        $this->app->instance(QuizScopeService::class, $scope);

        $this->get(route('teacher.quizzes.create'))
            ->assertOk()
            ->assertHeader('Permissions-Policy', 'microphone=(self)');
    }

    public function test_direct_teacher_delete_displays_domain_guard_message(): void
    {
        $this->actingAsRole('TEACHER');
        $scope = Mockery::mock(QuizScopeService::class);
        $scope->shouldReceive('teacherForUser')->once()->andReturn(['Teacher_ID' => 'T1']);
        $scope->shouldReceive('classesForTeacher')->once()->with('T1')->andReturn(collect([['Class_ID' => 'C1']]));
        $quizzes = Mockery::mock(QuizService::class);
        $quizzes->shouldReceive('deleteQuiz')->once()->andThrow(new \DomainException('Kuis bersejarah tidak dapat dihapus.'));
        $this->app->instance(QuizScopeService::class, $scope);
        $this->app->instance(QuizService::class, $quizzes);

        $this->from(route('teacher.quizzes.show', 'Q1'))
            ->delete(route('teacher.quizzes.destroy', 'Q1'))
            ->assertRedirect(route('teacher.quizzes.show', 'Q1'))
            ->assertSessionHas('error', 'Kuis bersejarah tidak dapat dihapus.');
    }

    public static function nonTeacherRoles(): array
    {
        return [['STUDENT'], ['HR'], ['FINANCE'], ['MARKETING'], ['ACADEMIC'], ['DIRECTOR']];
    }

    public static function nonStudentRoles(): array
    {
        return [['TEACHER'], ['HR'], ['FINANCE'], ['MARKETING'], ['ACADEMIC'], ['DIRECTOR']];
    }

    public function test_teacher_results_reject_forged_class_before_result_reads(): void
    {
        $this->actingAsRole('TEACHER');
        $scope = Mockery::mock(QuizScopeService::class);
        $scope->shouldReceive('teacherForUser')->once()->andReturn(['Teacher_ID' => 'T1']);
        $scope->shouldReceive('classesForTeacher')->once()->with('T1')->andReturn(collect([
            ['Class_ID' => 'C1', 'Class_Name' => 'Kelas A'],
            ['Class_ID' => 'C2', 'Class_Name' => 'Kelas B'],
        ]));
        $quizzes = Mockery::mock(QuizService::class);
        $quizzes->shouldNotReceive('teacherResults');
        $this->app->instance(QuizScopeService::class, $scope);
        $this->app->instance(QuizService::class, $quizzes);

        $this->get(route('teacher.quizzes.results', ['class' => 'C3']))->assertForbidden();
    }

    public function test_teacher_class_hub_rejects_forged_class_before_quiz_reads(): void
    {
        $this->actingAsRole('TEACHER');
        $scope = Mockery::mock(QuizScopeService::class);
        $scope->shouldReceive('teacherForUser')->once()->andReturn(['Teacher_ID' => 'T1']);
        $scope->shouldReceive('classesForTeacher')->once()->with('T1')->andReturn(collect([
            ['Class_ID' => 'C1', 'Class_Name' => 'Kelas A'],
            ['Class_ID' => 'C2', 'Class_Name' => 'Kelas B'],
        ]));
        $scope->shouldNotReceive('studentsForClass');
        $quizzes = Mockery::mock(QuizService::class);
        $quizzes->shouldNotReceive('teacherClassHub');
        $this->app->instance(QuizScopeService::class, $scope);
        $this->app->instance(QuizService::class, $quizzes);

        $this->get(route('teacher.quizzes.class', 'C3'))->assertForbidden();
    }

    public function test_class_context_store_overrides_forged_client_class_id(): void
    {
        $this->actingAsRole('TEACHER');
        $scope = Mockery::mock(QuizScopeService::class);
        $scope->shouldReceive('teacherForUser')->once()->andReturn(['Teacher_ID' => 'T1']);
        $scope->shouldReceive('classesForTeacher')->once()->with('T1')->andReturn(collect([
            ['Class_ID' => 'C1', 'Class_Name' => 'Kelas A'],
        ]));
        $quizzes = Mockery::mock(QuizService::class);
        $quizzes->shouldReceive('create')->once()->withArgs(function (array $data, array $teacher, string $userId) {
            return $data['Class_ID'] === 'C1'
                && $data['Class_IDs'] === ['C1']
                && $teacher['Teacher_ID'] === 'T1'
                && $userId === 'U-TEACHER';
        })->andReturn('Q1');
        $this->app->instance(QuizScopeService::class, $scope);
        $this->app->instance(QuizService::class, $quizzes);

        $this->post(route('teacher.quizzes.class.store', 'C1'), [
            'Idempotency_Key' => '10000000-0000-4000-8000-000000000877',
            'Title' => 'Kuis Aman',
            'Class_ID' => 'C3',
            'Start_At' => '2026-10-10 08:00:00',
            'End_At' => '2026-10-10 09:00:00',
            'Duration_Minutes' => 30,
            'intent' => 'draft',
            'question_count' => 1,
            'questions' => [[
                'Question_Text' => '',
                'Option_A' => '',
                'Option_B' => '',
                'Option_C' => '',
                'Option_D' => '',
                'Correct_Option' => 'A',
                'Point' => 10,
            ]],
        ])->assertRedirect(route('teacher.quizzes.show', 'Q1'));
    }

    public function test_mixed_authorized_and_unauthorized_audience_is_rejected_atomically(): void
    {
        $this->actingAsRole('TEACHER');
        $scope = Mockery::mock(QuizScopeService::class);
        $scope->shouldReceive('teacherForUser')->once()->andReturn(['Teacher_ID' => 'T1']);
        $scope->shouldReceive('assertTeacherClass')->once()->with('T1', 'C1');
        $scope->shouldReceive('assertTeacherClass')->once()->with('T1', 'C9')
            ->andThrow(new HttpException(403, 'Kelas di luar penugasan.'));
        $quizzes = Mockery::mock(QuizService::class);
        $quizzes->shouldNotReceive('create');
        $this->app->instance(QuizScopeService::class, $scope);
        $this->app->instance(QuizService::class, $quizzes);

        $this->post(route('teacher.quizzes.store'), [
            'Idempotency_Key' => '20000000-0000-4000-8000-000000000877',
            'Title' => 'Kuis Multi Kelas',
            'Class_IDs' => ['C1', 'C9'],
            'Start_At' => '2026-10-10 08:00:00',
            'End_At' => '2026-10-10 09:00:00',
            'Duration_Minutes' => 30,
            'intent' => 'draft',
            'question_count' => 1,
            'questions' => [[
                'Question_Text' => '', 'Option_A' => '', 'Option_B' => '',
                'Option_C' => '', 'Option_D' => '', 'Correct_Option' => 'A', 'Point' => 10,
            ]],
        ])->assertForbidden();
    }

    public function test_quiz_upload_endpoint_accepts_exact_seven_megabytes_without_changing_global_limit(): void
    {
        Storage::fake('local');
        $this->actingAsRole('TEACHER');
        $scope = Mockery::mock(QuizScopeService::class);
        $scope->shouldReceive('teacherForUser')->once()->andReturn(['Teacher_ID' => 'T1']);
        $this->app->instance(QuizScopeService::class, $scope);
        $file = $this->exactImageUpload(7 * 1024 * 1024);

        $this->post(route('teacher.quizzes.media.upload'), [
            'media_type' => 'image',
            'file' => $file,
        ])->assertCreated()->assertJsonPath('size', 7 * 1024 * 1024);

        $this->assertSame(5120, config('upload.max_kb'));
        $this->assertSame(7168, config('quiz.media.image_max_kb'));
    }

    private function actingAsRole(string $role): void
    {
        $roleId = 'ROLE-'.$role;
        $this->app->instance(RoleRepositoryInterface::class, new H877RoleRepository([['Role_ID' => $roleId, 'Role_Name' => $role, 'Is_Active' => 'TRUE']]));
        $this->actingAs(new GenericUser(['id' => 'U-'.$role, 'User_ID' => 'U-'.$role, 'Role_ID' => $roleId]));
    }

    private function exactImageUpload(int $bytes): UploadedFile
    {
        $resource = imagecreatetruecolor(20, 20);
        $path = tempnam(sys_get_temp_dir(), 'quiz-upload-');
        imagejpeg($resource, $path, 85);
        imagedestroy($resource);
        file_put_contents($path, str_repeat("\0", $bytes - filesize($path)), FILE_APPEND);
        clearstatcache(true, $path);
        $this->beforeApplicationDestroyed(static fn () => @unlink($path));

        return new UploadedFile($path, 'exact-7mb.jpg', 'image/jpeg', UPLOAD_ERR_OK, true);
    }
}

class H877RoleRepository implements RoleRepositoryInterface
{
    public function __construct(private array $rows) {}

    public function fetchAll()
    {
        return collect($this->rows);
    }

    public function findById(string $id)
    {
        return collect($this->rows)->firstWhere('Role_ID', $id);
    }

    public function create(array $data)
    {
        return $data;
    }

    public function update(string $id, array $data)
    {
        return true;
    }
}

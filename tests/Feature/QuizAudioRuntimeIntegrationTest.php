<?php

namespace Tests\Feature;

use App\Interfaces\GoogleSheets\RoleRepositoryInterface;
use App\Services\Quiz\QuizAudioInspector;
use App\Services\Quiz\QuizMediaService;
use App\Services\Quiz\QuizScopeService;
use App\Services\Quiz\QuizService;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuizAudioRuntimeIntegrationTest extends TestCase
{
    private string $fixtures;

    private QuizAudioInspector $inspector;

    protected function setUp(): void
    {
        parent::setUp();
        $ffmpeg = (string) getenv('QUIZ_FFMPEG_PATH');
        $ffprobe = (string) getenv('QUIZ_FFPROBE_PATH');
        $this->fixtures = (string) getenv('QUIZ_AUDIO_FIXTURE_DIR');
        if (! is_file($ffmpeg) || ! is_file($ffprobe) || ! is_dir($this->fixtures)) {
            $this->markTestSkipped('Actual FFmpeg integration requires QUIZ_FFMPEG_PATH, QUIZ_FFPROBE_PATH, and QUIZ_AUDIO_FIXTURE_DIR.');
        }
        config([
            'quiz.media.ffmpeg_path' => $ffmpeg,
            'quiz.media.ffprobe_path' => $ffprobe,
        ]);
        $this->inspector = $this->app->make(QuizAudioInspector::class);
    }

    #[DataProvider('durationFixtures')]
    public function test_actual_encoded_duration_boundary(
        string $prefix,
        string $label,
        string $extension,
        string $mime,
        int $expectedMilliseconds,
        bool $accepted,
    ): void {
        $path = $this->fixture("{$prefix}_{$label}.{$extension}");
        if (! $accepted) {
            try {
                $this->inspector->inspect($path, $extension, $mime);
                $this->fail('Effective audio over 90 seconds must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertSame('Durasi audio maksimal 1 menit 30 detik.', $exception->errors()['file'][0]);
            }

            return;
        }

        $result = $this->inspector->inspect($path, $extension, $mime);
        $this->assertSame($expectedMilliseconds, $result['duration_ms']);
    }

    public static function durationFixtures(): array
    {
        $durations = [
            ['30', 30000, true],
            ['60', 60000, true],
            ['89', 89000, true],
            ['90', 90000, true],
            ['90_001', 90001, false],
            ['90_1', 90100, false],
            ['91', 91000, false],
            ['120', 120000, false],
        ];
        $formats = [
            ['cbr', 'mp3', 'audio/mpeg'],
            ['vbr', 'mp3', 'audio/mpeg'],
            ['aac', 'm4a', 'audio/mp4'],
        ];
        $cases = [];
        foreach ($formats as [$prefix, $extension, $mime]) {
            foreach ($durations as [$label, $milliseconds, $accepted]) {
                $cases["{$prefix}-{$label}"] = [$prefix, $label, $extension, $mime, $milliseconds, $accepted];
            }
        }

        return $cases;
    }

    #[DataProvider('invalidContentFixtures')]
    public function test_actual_corrupt_or_unsafe_content_is_rejected(string $file, string $extension, string $mime): void
    {
        $this->expectException(ValidationException::class);
        $this->inspector->inspect($this->fixture($file), $extension, $mime);
    }

    public static function invalidContentFixtures(): array
    {
        return [
            'truncated' => ['truncated.mp3', 'mp3', 'audio/mpeg'],
            'corrupt packets' => ['corrupt.mp3', 'mp3', 'audio/mpeg'],
            'video stream' => ['with_video.m4a', 'm4a', 'audio/mp4'],
            'multiple audio streams' => ['multiple_audio.m4a', 'm4a', 'audio/mp4'],
            'timestamp gap' => ['timestamp_gap.m4a', 'm4a', 'audio/mp4'],
        ];
    }

    public function test_misleading_tlen_metadata_does_not_override_effective_decode_duration(): void
    {
        $result = $this->inspector->inspect($this->fixture('misleading_tlen.mp3'), 'mp3', 'audio/mpeg');

        $this->assertSame(30000, $result['duration_ms']);
    }

    public function test_mime_and_extension_mismatch_fail_closed(): void
    {
        foreach ([
            ['cbr_30.mp3', 'm4a', 'audio/mpeg'],
            ['aac_30.m4a', 'mp3', 'audio/mp4'],
            ['cbr_30.mp3', 'mp3', 'audio/mp4'],
        ] as [$file, $extension, $mime]) {
            try {
                $this->inspector->inspect($this->fixture($file), $extension, $mime);
                $this->fail('MIME or extension mismatch must be rejected.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_actual_exact_five_megabyte_audio_is_staged_and_one_byte_over_is_rejected(): void
    {
        Storage::fake('local');
        $exact = $this->uploadedCopy('exact_5mb.m4a', 'exact-5mb.m4a', 'audio/mp4');
        $result = $this->app->make(QuizMediaService::class)->stage($exact, 'audio', 'U-AUDIO');

        $this->assertSame(5 * 1024 * 1024, $result['size']);
        $this->assertSame(30000, $result['duration_ms']);

        $overPath = tempnam(sys_get_temp_dir(), 'quiz-audio-over-');
        copy($this->fixture('exact_5mb.m4a'), $overPath);
        file_put_contents($overPath, "\0", FILE_APPEND);
        $this->beforeApplicationDestroyed(static fn () => @unlink($overPath));

        $this->expectException(ValidationException::class);
        $this->app->make(QuizMediaService::class)->stage(
            new UploadedFile($overPath, 'over-5mb.m4a', 'audio/mp4', UPLOAD_ERR_OK, true),
            'audio',
            'U-AUDIO',
        );
    }

    public function test_actual_upload_endpoint_persists_effective_duration_metadata(): void
    {
        Storage::fake('local');
        $roleId = 'ROLE-TEACHER';
        $this->app->instance(RoleRepositoryInterface::class, new QuizAudioRoleRepository([
            ['Role_ID' => $roleId, 'Role_Name' => 'TEACHER', 'Is_Active' => 'TRUE'],
        ]));
        $this->actingAs(new GenericUser(['id' => 'U-AUDIO', 'User_ID' => 'U-AUDIO', 'Role_ID' => $roleId]));
        $scope = Mockery::mock(QuizScopeService::class);
        $scope->shouldReceive('teacherForUser')->once()->andReturn(['Teacher_ID' => 'T1']);
        $this->app->instance(QuizScopeService::class, $scope);

        $response = $this->post(route('teacher.quizzes.media.upload'), [
            'media_type' => 'audio',
            'file' => $this->uploadedCopy('cbr_30.mp3', 'valid-30.mp3', 'audio/mpeg'),
        ]);

        $response->assertCreated()->assertJsonPath('duration_ms', 30000);
        $token = (string) $response->json('token');
        $record = $this->app->make(QuizMediaService::class)->staged($token, 'U-AUDIO', 'audio');
        $this->assertSame(30000, $record['duration_ms']);
    }

    public function test_actual_quiz_create_persists_audio_duration_milliseconds(): void
    {
        Storage::fake('local');
        (require database_path('migrations/wms_mysql/2026_10_02_000001_create_wms_business_schema.php'))->up();
        (require database_path('migrations/wms_mysql/2026_11_20_000001_add_quiz_question_media_fields.php'))->up();
        (require database_path('migrations/wms_mysql/2026_11_21_000001_create_quiz_media_idempotency_tables.php'))->up();

        $media = $this->app->make(QuizMediaService::class);
        $staged = $media->stage($this->uploadedCopy('cbr_30.mp3', 'valid-30.mp3', 'audio/mpeg'), 'audio', 'U-AUDIO');
        $quizId = $this->app->make(QuizService::class)->create([
            'Idempotency_Key' => 'a0000000-0000-4000-8000-000000000001',
            'Title' => 'Kuis Audio Aktual',
            'Class_ID' => 'C1',
            'Start_At' => '2026-12-01 08:00:00',
            'End_At' => '2026-12-01 09:00:00',
            'Duration_Minutes' => 30,
            'intent' => 'draft',
            'questions' => [[
                'Question_Text' => 'Dengarkan audio',
                'Option_A' => 'A',
                'Option_B' => 'B',
                'Option_C' => 'C',
                'Option_D' => 'D',
                'Correct_Option' => 'A',
                'Point' => 10,
                'Audio_Token' => $staged['token'],
            ]],
        ], ['Teacher_ID' => 'T1'], 'U-AUDIO');

        $question = (array) DB::table('QUIZ_QUESTION')->where('Quiz_ID', $quizId)->first();
        $this->assertSame(30000, (int) $question['Audio_Duration_Milliseconds']);
        $this->assertTrue(Storage::disk('local')->exists((string) $question['Audio_Path']));
        $this->assertSame(1, DB::table('QUIZ_MEDIA_TOKEN_USE')->count());
    }

    public function test_decode_timeout_and_output_limit_fail_closed(): void
    {
        config(['quiz.media.ffmpeg_timeout_seconds' => 0.001]);
        try {
            $this->inspector->inspect($this->fixture('cbr_30.mp3'), 'mp3', 'audio/mpeg');
            $this->fail('Decode timeout must fail closed.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        config([
            'quiz.media.ffmpeg_timeout_seconds' => 20,
            'quiz.media.ffmpeg_max_output_bytes' => 1,
        ]);
        $this->expectException(ValidationException::class);
        $this->inspector->inspect($this->fixture('cbr_30.mp3'), 'mp3', 'audio/mpeg');
    }

    public function test_malformed_decoder_progress_output_fails_closed(): void
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            $this->markTestSkipped('The portable Windows runtime test uses a temporary cmd shim.');
        }
        $shim = tempnam(sys_get_temp_dir(), 'quiz-malformed-').'.cmd';
        file_put_contents($shim, "@echo off\r\necho out_time_us=bogus\r\necho progress=end\r\nexit /b 0\r\n");
        $this->beforeApplicationDestroyed(static fn () => @unlink($shim));
        config(['quiz.media.ffmpeg_path' => $shim]);

        $this->expectException(ValidationException::class);
        $this->inspector->inspect($this->fixture('cbr_30.mp3'), 'mp3', 'audio/mpeg');
    }

    private function fixture(string $name): string
    {
        $path = $this->fixtures.DIRECTORY_SEPARATOR.$name;
        $this->assertFileExists($path);

        return $path;
    }

    private function uploadedCopy(string $fixture, string $name, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'quiz-audio-upload-');
        copy($this->fixture($fixture), $path);
        $this->beforeApplicationDestroyed(static fn () => @unlink($path));

        return new UploadedFile($path, $name, $mime, UPLOAD_ERR_OK, true);
    }
}

class QuizAudioRoleRepository implements RoleRepositoryInterface
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

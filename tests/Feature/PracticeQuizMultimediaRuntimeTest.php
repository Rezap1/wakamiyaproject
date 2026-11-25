<?php

namespace Tests\Feature;

use App\Services\PracticeQuiz\PracticeQuizMediaService;
use App\Services\Quiz\QuizMediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PracticeQuizMultimediaRuntimeTest extends TestCase
{
    private string $fixtures;

    private string $directory;

    private PracticeQuizMediaService $practice;

    protected function setUp(): void
    {
        parent::setUp();
        $ffmpeg = (string) getenv('QUIZ_FFMPEG_PATH');
        $ffprobe = (string) getenv('QUIZ_FFPROBE_PATH');
        $this->fixtures = (string) getenv('QUIZ_AUDIO_FIXTURE_DIR');
        if (! is_file($ffmpeg) || ! is_file($ffprobe) || ! is_dir($this->fixtures)) {
            $this->markTestSkipped('Practice multimedia runtime requires the verified FFmpeg fixture environment.');
        }
        config(['quiz.media.ffmpeg_path' => $ffmpeg, 'quiz.media.ffprobe_path' => $ffprobe]);
        Storage::fake('local');
        $this->directory = storage_path('framework/testing/practice-media-'.bin2hex(random_bytes(5)));
        mkdir($this->directory, 0777, true);
        $this->practice = $this->app->make(PracticeQuizMediaService::class);
    }

    protected function tearDown(): void
    {
        if (isset($this->directory) && is_dir($this->directory)) {
            foreach (glob($this->directory.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->directory);
        }
        parent::tearDown();
    }

    public function test_real_mp3_m4a_image_and_recording_use_isolated_practice_transport_and_storage(): void
    {
        $mp3 = $this->practice->stage($this->copyFixture('cbr_30.mp3', '聴解.mp3', 'audio/mpeg'), 'audio', 'U-PRACTICE', 'upload');
        $m4a = $this->practice->stage($this->copyFixture('aac_30.m4a', 'latihan.m4a', 'audio/mp4'), 'audio', 'U-PRACTICE', 'upload');
        $image = $this->practice->stage(UploadedFile::fake()->image('問題.png', 320, 240), 'image', 'U-PRACTICE', 'upload');
        $this->assertSame(30000, $mp3['duration_ms']);
        $this->assertSame(30000, $m4a['duration_ms']);
        $this->assertSame('image', $image['type']);

        try {
            $this->app->make(QuizMediaService::class)->staged($mp3['token'], 'U-PRACTICE', 'audio');
            $this->fail('Practice token crossed into the regular quiz namespace.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }

        $webm = $this->directory.DIRECTORY_SEPARATOR.'recording.webm';
        $process = new Process([
            (string) config('quiz.media.ffmpeg_path'), '-nostdin', '-v', 'error', '-f', 'lavfi',
            '-i', 'sine=frequency=700:sample_rate=48000:duration=2', '-ac', '1', '-c:a', 'libopus', '-f', 'webm', $webm,
        ]);
        $process->setTimeout(20);
        $process->mustRun();
        $recording = $this->practice->stage(new UploadedFile($webm, 'rekaman.webm', 'audio/webm', UPLOAD_ERR_OK, true), 'audio', 'U-PRACTICE', 'recording');
        $this->assertSame('audio/mp4', $recording['mime']);
        $this->assertGreaterThanOrEqual(1900, $recording['duration_ms']);
        $this->assertLessThanOrEqual(2100, $recording['duration_ms']);

        $questions = [
            ['Question_Text' => 'MP3', 'Option_A' => 'A', 'Option_B' => 'B', 'Option_C' => 'C', 'Option_D' => 'D', 'Correct_Option' => 'A', 'Point' => 10, 'Audio_Token' => $mp3['token'], 'Image_Token' => $image['token']],
            ['Question_Text' => 'M4A', 'Option_A' => 'A', 'Option_B' => 'B', 'Option_C' => 'C', 'Option_D' => 'D', 'Correct_Option' => 'A', 'Point' => 10, 'Audio_Token' => $m4a['token']],
            ['Question_Text' => 'Recording', 'Option_A' => 'A', 'Option_B' => 'B', 'Option_C' => 'C', 'Option_D' => 'D', 'Correct_Option' => 'A', 'Point' => 10, 'Audio_Token' => $recording['token']],
        ];
        $plan = $this->practice->prepare('PQ-RUNTIME', 'REV-RUNTIME', $questions, [], 'U-PRACTICE');
        foreach ($plan['questions'] as $question) {
            if ($question['Audio_Path'] ?? null) {
                $this->assertStringStartsWith('practice-quiz-media/PQ-RUNTIME/REV-RUNTIME/', $question['Audio_Path']);
                $this->assertTrue(Storage::disk('local')->exists($question['Audio_Path']));
            }
        }
        $this->assertTrue(Storage::disk('local')->exists($plan['questions'][0]['Image_Path']));
        $this->practice->commit($plan);
    }

    private function copyFixture(string $fixture, string $name, string $mime): UploadedFile
    {
        $target = $this->directory.DIRECTORY_SEPARATOR.bin2hex(random_bytes(4)).'-'.pathinfo($fixture, PATHINFO_BASENAME);
        copy($this->fixtures.DIRECTORY_SEPARATOR.$fixture, $target);

        return new UploadedFile($target, $name, $mime, UPLOAD_ERR_OK, true);
    }
}

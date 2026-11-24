<?php

namespace Tests\Feature;

use App\Services\Quiz\QuizAudioInspector;
use App\Services\Quiz\QuizBoundedProcessRunner;
use App\Services\Quiz\QuizRecordingNormalizer;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class QuizRecordingRuntimeIntegrationTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $ffmpeg = realpath((string) config('quiz.media.ffmpeg_path'));
        $ffprobe = realpath((string) config('quiz.media.ffprobe_path'));
        if ($ffmpeg === false || $ffprobe === false) {
            $this->markTestSkipped('Real recording normalization requires explicit FFmpeg and FFprobe binaries.');
        }
        $this->directory = storage_path('framework/testing/quiz-recording-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($this->directory);
    }

    protected function tearDown(): void
    {
        if (isset($this->directory)) {
            File::deleteDirectory($this->directory);
        }
        parent::tearDown();
    }

    #[DataProvider('browserContainers')]
    public function test_real_browser_recording_container_is_normalized_and_reinspected(
        string $extension,
        string $mime,
        array $encoder,
    ): void {
        $input = $this->directory.DIRECTORY_SEPARATOR.'recording.'.$extension;
        $output = $this->directory.DIRECTORY_SEPARATOR.'normalized-'.$extension.'.m4a';
        $this->generate($input, $encoder);

        $result = (new QuizRecordingNormalizer(
            new QuizAudioInspector,
            new QuizBoundedProcessRunner,
        ))->normalize($input, $output, $extension, $mime);

        $this->assertFileExists($output);
        $this->assertSame('aac', $result['codec']);
        $this->assertSame('m4a', $result['extension']);
        $this->assertGreaterThanOrEqual(1900, $result['duration_ms']);
        $this->assertLessThanOrEqual(2100, $result['duration_ms']);
        $this->assertLessThanOrEqual((int) config('quiz.media.audio_max_bytes'), filesize($output));
    }

    public static function browserContainers(): array
    {
        return [
            'Chrome and Edge WebM Opus' => ['webm', 'audio/webm', ['-c:a', 'libopus', '-f', 'webm']],
            'Firefox Ogg Opus' => ['ogg', 'audio/ogg', ['-c:a', 'libopus', '-f', 'ogg']],
            'Safari MP4 AAC' => ['m4a', 'audio/mp4', ['-c:a', 'aac', '-f', 'ipod']],
        ];
    }

    public function test_real_mp3_with_id3_attached_cover_art_is_accepted_but_video_is_not(): void
    {
        $plain = $this->directory.DIRECTORY_SEPARATOR.'plain.mp3';
        $cover = $this->directory.DIRECTORY_SEPARATOR.'cover.png';
        $withCover = $this->directory.DIRECTORY_SEPARATOR.'with-cover.mp3';
        $this->generate($plain, ['-c:a', 'libmp3lame', '-b:a', '128k', '-metadata', 'title=Fixture ID3', '-f', 'mp3']);
        $image = imagecreatetruecolor(32, 32);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 120, 220));
        imagepng($image, $cover);
        imagedestroy($image);
        $mux = new Process([
            (string) realpath((string) config('quiz.media.ffmpeg_path')),
            '-nostdin', '-v', 'error', '-i', $plain, '-i', $cover,
            '-map', '0:a:0', '-map', '1:v:0', '-c:a', 'copy', '-c:v', 'mjpeg',
            '-id3v2_version', '3', '-metadata:s:v', 'title=Album cover', '-disposition:v', 'attached_pic',
            '-f', 'mp3', $withCover,
        ]);
        $mux->setTimeout(20);
        $mux->mustRun();

        $result = (new QuizAudioInspector)->inspect($withCover, 'mp3', 'audio/mpeg');

        $this->assertSame('mp3', $result['codec']);
        $this->assertGreaterThanOrEqual(1900, $result['duration_ms']);
    }

    public function test_real_ten_second_mp3_upload_is_accepted(): void
    {
        $path = $this->directory.DIRECTORY_SEPARATOR.'ten-seconds.mp3';
        $this->generate($path, ['-c:a', 'libmp3lame', '-b:a', '128k', '-f', 'mp3'], 10);

        $result = (new QuizAudioInspector)->inspect($path, 'mp3', 'audio/mpeg');

        $this->assertSame(10000, $result['duration_ms']);
    }

    public function test_real_ten_second_webm_recording_is_normalized(): void
    {
        $input = $this->directory.DIRECTORY_SEPARATOR.'ten-seconds.webm';
        $output = $this->directory.DIRECTORY_SEPARATOR.'ten-seconds.m4a';
        $this->generate($input, ['-c:a', 'libopus', '-f', 'webm'], 10);

        $result = (new QuizRecordingNormalizer(
            new QuizAudioInspector,
            new QuizBoundedProcessRunner,
        ))->normalize($input, $output, 'webm', 'audio/webm');

        $this->assertGreaterThanOrEqual(10000, $result['duration_ms']);
        $this->assertLessThanOrEqual(10020, $result['duration_ms']);
        $this->assertSame('audio/mp4', $result['mime']);
    }

    private function generate(string $output, array $encoder, int $durationSeconds = 2): void
    {
        $process = new Process(array_merge([
            (string) realpath((string) config('quiz.media.ffmpeg_path')),
            '-nostdin', '-v', 'error', '-f', 'lavfi', '-i', "sine=frequency=880:sample_rate=48000:duration={$durationSeconds}",
            '-ac', '1',
        ], $encoder, [$output]));
        $process->setTimeout(20);
        $process->mustRun();
    }
}

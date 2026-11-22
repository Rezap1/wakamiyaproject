<?php

namespace Tests\Feature;

use App\Services\Quiz\QuizAudioInspector;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuizAudioSampleRateIntegrationTest extends TestCase
{
    private string $fixtures;

    private QuizAudioInspector $inspector;

    protected function setUp(): void
    {
        parent::setUp();
        $ffmpeg = (string) getenv('QUIZ_FFMPEG_PATH');
        $ffprobe = (string) getenv('QUIZ_FFPROBE_PATH');
        $this->fixtures = (string) getenv('QUIZ_AUDIO_MATRIX_DIR');
        if (! is_file($ffmpeg) || ! is_file($ffprobe) || ! is_dir($this->fixtures)) {
            $this->markTestSkipped('Actual sample-rate matrix requires FFmpeg, FFprobe, and QUIZ_AUDIO_MATRIX_DIR.');
        }
        config([
            'quiz.media.ffmpeg_path' => $ffmpeg,
            'quiz.media.ffprobe_path' => $ffprobe,
        ]);
        $this->inspector = app(QuizAudioInspector::class);
    }

    #[DataProvider('matrix')]
    public function test_effective_sample_boundary_across_rate_channels_and_codecs(
        string $format,
        int $sampleRate,
        int $channels,
        string $boundary,
    ): void {
        $extension = $format === 'aac' ? 'm4a' : 'mp3';
        $mime = $extension === 'm4a' ? 'audio/mp4' : 'audio/mpeg';
        $path = $this->fixtures.DIRECTORY_SEPARATOR."{$format}_{$sampleRate}hz_{$channels}ch_{$boundary}.{$extension}";
        $this->assertFileExists($path);

        if ($boundary === 'over') {
            try {
                $this->inspector->inspect($path, $extension, $mime);
                $this->fail('Effective decoded audio over 90 seconds must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertSame('Durasi audio maksimal 1 menit 30 detik.', $exception->errors()['file'][0]);
            }

            return;
        }

        $result = $this->inspector->inspect($path, $extension, $mime);
        $this->assertSame(90000, $result['duration_ms']);
    }

    public static function matrix(): array
    {
        $cases = [];
        foreach (['cbr', 'vbr', 'aac'] as $format) {
            foreach ([8000, 16000, 44100, 48000] as $sampleRate) {
                foreach ([1, 2] as $channels) {
                    foreach (['exact', 'over'] as $boundary) {
                        $cases["{$format}-{$sampleRate}-{$channels}ch-{$boundary}"] = [
                            $format, $sampleRate, $channels, $boundary,
                        ];
                    }
                }
            }
        }

        return $cases;
    }
}

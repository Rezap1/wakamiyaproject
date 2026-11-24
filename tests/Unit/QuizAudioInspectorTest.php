<?php

namespace Tests\Unit;

use App\Services\Quiz\QuizAudioInspector;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuizAudioInspectorTest extends TestCase
{
    #[DataProvider('acceptedDurations')]
    public function test_supported_audio_durations_are_accepted(string $duration, int $milliseconds): void
    {
        $result = $this->inspector($this->payload('mp3', $duration))->inspect($this->localFile(), 'mp3', 'audio/mpeg');

        $this->assertSame($milliseconds, $result['duration_ms']);
    }

    public static function acceptedDurations(): array
    {
        return [['30.000000000', 30000], ['60.000000000', 60000], ['89.000000000', 89000], ['90.000000000', 90000]];
    }

    #[DataProvider('rejectedDurations')]
    public function test_unsupported_audio_durations_are_rejected(string $duration): void
    {
        $this->expectException(ValidationException::class);
        $this->inspector($this->payload('mp3', $duration))->inspect($this->localFile(), 'mp3', 'audio/mpeg');
    }

    public static function rejectedDurations(): array
    {
        return [['90.100000000'], ['91.000000000'], ['120.000000000']];
    }

    public function test_packet_timeline_accepts_exactly_ninety_seconds_without_rounding_down(): void
    {
        $inspector = $this->inspector($this->payload('mp3', '90.000000000'));
        $result = $inspector->inspect($this->localFile(), 'mp3', 'audio/mpeg');

        $this->assertSame(90000, $result['duration_ms']);
        $this->assertSame('mp3', $result['codec']);
    }

    public function test_any_fraction_over_ninety_seconds_is_rejected(): void
    {
        $inspector = $this->inspector($this->payload('mp3', '90.000020834'));

        try {
            $inspector->inspect($this->localFile(), 'mp3', 'audio/mpeg');
            $this->fail('Audio over 90 seconds must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame('Durasi audio maksimal 1 menit 30 detik.', $exception->errors()['file'][0]);
        }
    }

    public function test_m4a_requires_aac_and_rejects_video_stream(): void
    {
        $payload = $this->payload('aac', '30.000000000');
        $payload['streams'][] = ['index' => 1, 'codec_type' => 'video', 'codec_name' => 'h264'];

        $this->expectException(ValidationException::class);
        $this->inspector($payload)->inspect($this->localFile(), 'm4a', 'audio/mp4');
    }

    public function test_mp3_allows_safe_attached_cover_art_but_not_disguised_video(): void
    {
        $payload = $this->payload('mp3', '10.000000000');
        $payload['streams'][] = [
            'index' => 1,
            'codec_type' => 'video',
            'codec_name' => 'mjpeg',
            'disposition' => ['attached_pic' => 1],
        ];

        $result = $this->inspector($payload)->inspect($this->localFile(), 'mp3', 'audio/x-mp3');
        $this->assertSame(10000, $result['duration_ms']);

        $payload['streams'][1]['codec_name'] = 'h264';
        $this->expectException(ValidationException::class);
        $this->inspector($payload)->inspect($this->localFile(), 'mp3', 'audio/mpeg');
    }

    public function test_legitimate_platform_mime_aliases_still_require_matching_codec(): void
    {
        $mp3 = $this->inspector($this->payload('mp3', '10.000000000'))
            ->inspect($this->localFile(), 'mp3', 'audio/x-mpeg');
        $m4a = $this->inspector($this->payload('aac', '10.000000000'))
            ->inspect($this->localFile(), 'm4a', 'video/mp4');

        $this->assertSame('mp3', $mp3['codec']);
        $this->assertSame('aac', $m4a['codec']);
    }

    public function test_missing_ffprobe_fails_closed_with_safe_message(): void
    {
        config(['quiz.media.ffprobe_path' => base_path('definitely-missing-ffprobe-binary')]);

        try {
            (new QuizAudioInspector)->inspect($this->localFile(), 'mp3', 'audio/mpeg');
            $this->fail('Missing probe must fail closed.');
        } catch (ValidationException $exception) {
            $this->assertSame('Pemeriksaan durasi audio tidak tersedia di server. Hubungi administrator.', $exception->errors()['file'][0]);
            $this->assertStringNotContainsString(base_path(), $exception->errors()['file'][0]);
        }
    }

    public function test_missing_ffmpeg_fails_closed_with_safe_message(): void
    {
        config(['quiz.media.ffmpeg_path' => base_path('definitely-missing-ffmpeg-binary')]);
        $inspector = new class($this->payload('mp3', '30.000000000')) extends QuizAudioInspector
        {
            public function __construct(private array $payload) {}

            protected function probe(string $realPath): array
            {
                return $this->payload;
            }
        };

        try {
            $inspector->inspect($this->localFile(), 'mp3', 'audio/mpeg');
            $this->fail('Missing decoder must fail closed.');
        } catch (ValidationException $exception) {
            $this->assertSame('Pemeriksaan durasi audio tidak tersedia di server. Hubungi administrator.', $exception->errors()['file'][0]);
        }
    }

    public function test_packet_timestamp_discontinuity_is_rejected(): void
    {
        $payload = $this->payload('mp3', '2.000000000');
        $payload['packets'] = [
            ['stream_index' => 0, 'pts' => 0, 'duration' => 1_000_000_000],
            ['stream_index' => 0, 'pts' => 1_000_000_001, 'duration' => 999_999_999],
        ];

        $this->expectException(ValidationException::class);
        $this->inspector($payload)->inspect($this->localFile(), 'mp3', 'audio/mpeg');
    }

    #[DataProvider('invalidSkipDiscardMetadata')]
    public function test_invalid_skip_or_discard_metadata_is_rejected(array $sideData): void
    {
        $payload = $this->payload('mp3', '30.000000000');
        $payload['packets'][0]['side_data_list'] = [$sideData + ['side_data_type' => 'Skip Samples']];

        $this->expectException(ValidationException::class);
        $this->inspector($payload)->inspect($this->localFile(), 'mp3', 'audio/mpeg');
    }

    public static function invalidSkipDiscardMetadata(): array
    {
        return [
            'negative skip' => [['skip_samples' => -1, 'discard_padding' => 0, 'skip_reason' => 0, 'discard_reason' => 0]],
            'negative discard' => [['skip_samples' => 0, 'discard_padding' => -1, 'skip_reason' => 0, 'discard_reason' => 0]],
            'unknown skip reason' => [['skip_samples' => 1, 'discard_padding' => 0, 'skip_reason' => 1, 'discard_reason' => 0]],
            'unknown discard reason' => [['skip_samples' => 0, 'discard_padding' => 1, 'skip_reason' => 0, 'discard_reason' => 1]],
        ];
    }

    private function inspector(array $payload): QuizAudioInspector
    {
        return new class($payload) extends QuizAudioInspector
        {
            public function __construct(private array $payload) {}

            protected function probe(string $realPath): array
            {
                return $this->payload;
            }

            protected function decodeEffectiveSamples(string $realPath): int
            {
                $durationNanoseconds = array_sum(array_map(
                    fn (array $packet): int => (int) $packet['duration'],
                    $this->payload['packets'],
                ));

                return (int) round(($durationNanoseconds / 1_000_000_000) * 48000);
            }
        };
    }

    private function payload(string $codec, string $duration): array
    {
        [$seconds, $fraction] = array_pad(explode('.', $duration, 2), 2, '');
        $durationNanoseconds = ((int) $seconds * 1_000_000_000)
            + (int) str_pad(substr($fraction, 0, 9), 9, '0');

        return [
            'streams' => [[
                'index' => 0,
                'codec_type' => 'audio',
                'codec_name' => $codec,
                'sample_rate' => '48000',
                'time_base' => '1/1000000000',
                'duration_ts' => $durationNanoseconds,
            ]],
            'packets' => [['stream_index' => 0, 'pts' => 0, 'duration' => $durationNanoseconds]],
        ];
    }

    private function localFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'quiz-audio-test-');
        file_put_contents($path, 'local-fixture');
        $this->beforeApplicationDestroyed(static fn () => @unlink($path));

        return $path;
    }
}

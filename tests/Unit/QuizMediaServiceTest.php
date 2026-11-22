<?php

namespace Tests\Unit;

use App\Interfaces\GoogleSheets\QuizQuestionRepositoryInterface;
use App\Services\Quiz\QuizAudioInspector;
use App\Services\Quiz\QuizMediaIdempotencyService;
use App\Services\Quiz\QuizMediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class QuizMediaServiceTest extends TestCase
{
    private QuizMediaService $media;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $questions = Mockery::mock(QuizQuestionRepositoryInterface::class);
        $questions->shouldReceive('fetchAllFresh')->zeroOrMoreTimes()->andReturn(collect());
        $this->media = new QuizMediaService(new QuizAudioInspector, $questions);
    }

    public function test_valid_jpeg_png_and_webp_are_staged_with_server_metadata(): void
    {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
            if ($extension === 'webp' && ! function_exists('imagewebp')) {
                $this->markTestSkipped('GD WebP support is unavailable.');
            }
            $result = $this->media->stage($this->image($extension, 32, 24), 'image', 'U1');
            $this->assertSame('image', $result['type']);
            $this->assertGreaterThan(0, $result['size']);
            $this->assertNotEmpty($result['token']);
        }
    }

    public function test_exactly_seven_megabyte_image_is_accepted_and_one_byte_over_is_rejected(): void
    {
        $accepted = $this->media->stage($this->image('jpg', 32, 24, 7 * 1024 * 1024), 'image', 'U1');
        $this->assertSame(7 * 1024 * 1024, $accepted['size']);

        $this->expectException(ValidationException::class);
        $this->media->stage($this->image('jpg', 32, 24, (7 * 1024 * 1024) + 1), 'image', 'U1');
    }

    public function test_dimension_limit_corruption_and_mime_extension_mismatch_are_rejected(): void
    {
        foreach ([
            $this->image('png', 4097, 1),
            UploadedFile::fake()->createWithContent('broken.png', 'not-an-image'),
            $this->image('png', 10, 10, null, 'wrong.jpg'),
        ] as $file) {
            try {
                $this->media->stage($file, 'image', 'U1');
                $this->fail('Invalid image must be rejected.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_truncated_jpeg_png_and_webp_with_valid_headers_are_rejected(): void
    {
        foreach (['jpg', 'png', 'webp'] as $extension) {
            if ($extension === 'webp' && ! function_exists('imagewebp')) {
                continue;
            }
            try {
                $this->media->stage($this->truncatedImage($extension), 'image', 'U1');
                $this->fail("Truncated {$extension} must be rejected.");
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_token_is_owner_bound_tamper_resistant_and_consumed_after_commit(): void
    {
        $staged = $this->media->stage($this->image('jpg', 20, 20), 'image', 'U1');
        $this->assertSame('U1', $this->media->staged($staged['token'], 'U1')['user_id']);

        try {
            $this->media->staged($staged['token'], 'U2');
            $this->fail('Another user must not access the token.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $plan = $this->media->prepare('Q1', [[
            'Image_Token' => $staged['token'],
            'Question_Text' => '',
        ]], [], 'U1');
        $finalPath = $plan['questions'][0]['Image_Path'];
        $this->assertTrue(Storage::disk('local')->exists($finalPath));
        $this->media->commit($plan);
        $this->assertTrue(Storage::disk('local')->exists($finalPath));

        $this->expectException(HttpException::class);
        $this->media->staged($staged['token'], 'U1');
    }

    public function test_durable_consumption_blocks_replay_even_when_staging_cache_and_file_remain(): void
    {
        (require database_path('migrations/wms_mysql/2026_11_21_000001_create_quiz_media_idempotency_tables.php'))->up();
        $idempotency = new QuizMediaIdempotencyService;
        $questions = Mockery::mock(QuizQuestionRepositoryInterface::class);
        $questions->shouldReceive('fetchAllFresh')->zeroOrMoreTimes()->andReturn(collect());
        $media = new QuizMediaService(new QuizAudioInspector, $questions, $idempotency);
        $staged = $media->stage($this->image('jpg', 20, 20), 'image', 'U1');
        $record = $media->staged($staged['token'], 'U1');
        $idempotency->recordCommitted(
            '80000000-0000-4000-8000-000000000088',
            'U1',
            'create',
            null,
            str_repeat('a', 64),
            'Q-OLD',
            [[
                'token_hash' => $idempotency->tokenHash($staged['token']),
                'question_id' => 'QQ-OLD',
                'media_type' => 'image',
                'final_path' => 'quiz-media/Q-OLD/QQ-OLD/a.jpg',
            ]],
            '2026-11-21 08:00:00.000000',
        );

        try {
            $media->prepare('Q-NEW', [['Image_Token' => $staged['token']]], [], 'U1');
            $this->fail('A committed token must not create another final attachment.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertTrue(Storage::disk('local')->exists($record['path']), 'Failed cache cleanup may leave staging file, but it must be unusable.');
        $this->assertCount(1, Storage::disk('local')->allFiles('quiz-media'));
    }

    public function test_aggregate_cap_rolls_back_final_copy_but_keeps_staging_for_retry(): void
    {
        config(['quiz.media.aggregate_max_bytes' => 1]);
        $staged = $this->media->stage($this->image('jpg', 20, 20), 'image', 'U1');
        $record = $this->media->staged($staged['token'], 'U1');

        try {
            $this->media->prepare('Q1', [['Image_Token' => $staged['token']]], [], 'U1');
            $this->fail('Aggregate cap must be authoritative.');
        } catch (ValidationException) {
            $this->assertTrue(Storage::disk('local')->exists($record['path']));
            $this->assertCount(1, Storage::disk('local')->allFiles('quiz-media'));
        }
    }

    public function test_retain_remove_and_replace_preserve_old_media_until_commit(): void
    {
        $old = 'quiz-media/Q1/QQNOLD/old.jpg';
        Storage::disk('local')->put($old, 'old-image');
        $existing = [[
            'Question_ID' => 'QQN'.strtoupper(substr(hash('sha256', 'Q1|0'), 0, 20)),
            'Image_Path' => $old,
            'Image_Mime' => 'image/jpeg',
            'Image_Size_Bytes' => 9,
        ]];

        $retained = $this->media->prepare('Q1', [['Question_Text' => 'Retain']], $existing, 'U1');
        $this->assertSame($old, $retained['questions'][0]['Image_Path']);
        $this->assertSame([], $retained['old_paths']);

        $removed = $this->media->prepare('Q1', [['Remove_Image' => true]], $existing, 'U1');
        $this->assertTrue(Storage::disk('local')->exists($old), 'Old media must exist before database commit.');
        $this->media->commit($removed);
        $this->assertFalse(Storage::disk('local')->exists($old));

        Storage::disk('local')->put($old, 'old-image');
        $staged = $this->media->stage($this->image('jpg', 20, 20), 'image', 'U1');
        $replacement = $this->media->prepare('Q1', [['Image_Token' => $staged['token']]], $existing, 'U1');
        $new = $replacement['questions'][0]['Image_Path'];
        $this->assertTrue(Storage::disk('local')->exists($old));
        $this->assertTrue(Storage::disk('local')->exists($new));
        $this->media->commit($replacement);
        $this->assertFalse(Storage::disk('local')->exists($old));
        $this->assertTrue(Storage::disk('local')->exists($new));
    }

    public function test_staging_quota_is_enforced_under_user_lock(): void
    {
        config(['quiz.media.staging_quota_bytes' => 1]);

        $this->expectException(ValidationException::class);
        $this->media->stage($this->image('jpg', 20, 20), 'image', 'U1');
    }

    private function image(string $extension, int $width, int $height, ?int $targetSize = null, ?string $name = null): UploadedFile
    {
        $resource = imagecreatetruecolor($width, $height);
        $path = tempnam(sys_get_temp_dir(), 'quiz-image-');
        match ($extension) {
            'jpg', 'jpeg' => imagejpeg($resource, $path, 85),
            'png' => imagepng($resource, $path),
            'webp' => imagewebp($resource, $path, 85),
        };
        imagedestroy($resource);
        if ($targetSize !== null && filesize($path) < $targetSize) {
            file_put_contents($path, str_repeat("\0", $targetSize - filesize($path)), FILE_APPEND);
            clearstatcache(true, $path);
        }
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'
        };
        $upload = new UploadedFile($path, $name ?? "valid.{$extension}", $mime, UPLOAD_ERR_OK, true);
        $this->beforeApplicationDestroyed(static fn () => @unlink($path));

        return $upload;
    }

    private function truncatedImage(string $extension): UploadedFile
    {
        $source = $this->image($extension, 64, 64);
        $contents = file_get_contents($source->getPathname());
        $remove = match ($extension) {
            'jpg', 'jpeg' => 2,
            'png' => 12,
            'webp' => 8,
        };
        $path = tempnam(sys_get_temp_dir(), 'quiz-truncated-image-');
        file_put_contents($path, substr((string) $contents, 0, -$remove));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        };
        $upload = new UploadedFile($path, "truncated.{$extension}", $mime, UPLOAD_ERR_OK, true);
        $this->beforeApplicationDestroyed(static fn () => @unlink($path));

        return $upload;
    }
}

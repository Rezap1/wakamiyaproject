<?php

namespace Tests\Unit;

use App\Services\PracticeQuiz\PracticeQuizMediaService;
use App\Services\Quiz\QuizMediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PracticeQuizMediaIsolationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_practice_alias_token_is_distinct_and_promotes_only_into_practice_namespace(): void
    {
        Storage::fake('local');
        $rawToken = '10000000-0000-4000-8000-000000000001';
        $source = 'quiz-media/staging/u/audio.mp3';
        Storage::disk('local')->put($source, 'valid-audio-bytes');
        $checksum = hash_file('sha256', Storage::disk('local')->path($source));

        $regular = Mockery::mock(QuizMediaService::class);
        $regular->shouldReceive('stage')->once()->andReturn([
            'token' => $rawToken, 'type' => 'audio', 'size' => 17, 'mime' => 'audio/mpeg', 'duration_ms' => 30000,
        ]);
        $regular->shouldReceive('staged')->once()->with($rawToken, 'U1', 'audio')->andReturn([
            'user_id' => 'U1', 'type' => 'audio', 'path' => $source, 'size' => 17,
            'mime' => 'audio/mpeg', 'extension' => 'mp3', 'duration_ms' => 30000,
            'checksum' => $checksum, 'expires_at' => now()->addHour()->timestamp,
        ]);
        $regular->shouldReceive('absolutePath')->once()->andReturn(Storage::disk('local')->path($source));

        $service = new PracticeQuizMediaService($regular);
        $staged = $service->stage(UploadedFile::fake()->create('日本語.mp3', 1, 'audio/mpeg'), 'audio', 'U1', 'upload');
        $this->assertNotSame($rawToken, $staged['token']);

        try {
            $service->staged($rawToken, 'U1', 'audio');
            $this->fail('A regular quiz token was accepted by the practice token namespace.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }

        $plan = $service->prepare('PQ1', 'REV1', [[
            'Question_Text' => 'Audio', 'Option_A' => 'A', 'Option_B' => 'B', 'Option_C' => 'C', 'Option_D' => 'D',
            'Correct_Option' => 'A', 'Point' => 10, 'Audio_Token' => $staged['token'],
        ]], [], 'U1');
        $path = $plan['questions'][0]['Audio_Path'];
        $this->assertStringStartsWith('practice-quiz-media/PQ1/REV1/', $path);
        $this->assertTrue(Storage::disk('local')->exists($path));
        $service->commit($plan);
        $this->assertFalse(Storage::disk('local')->exists($source));
        $this->assertTrue(Storage::disk('local')->exists($path));
    }

    public function test_staging_token_is_bound_to_owner(): void
    {
        $regular = Mockery::mock(QuizMediaService::class);
        $regular->shouldReceive('stage')->once()->andReturn([
            'token' => '20000000-0000-4000-8000-000000000001', 'type' => 'image', 'size' => 10, 'mime' => 'image/png', 'duration_ms' => null,
        ]);
        $service = new PracticeQuizMediaService($regular);
        $token = $service->stage(UploadedFile::fake()->create('gambar.png', 1, 'image/png'), 'image', 'OWNER', 'upload')['token'];

        try {
            $service->staged($token, 'OTHER', 'image');
            $this->fail('Cross-user staging access was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}

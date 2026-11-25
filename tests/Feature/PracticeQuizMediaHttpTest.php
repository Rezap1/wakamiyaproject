<?php

namespace Tests\Feature;

use App\Services\PracticeQuiz\PracticeQuizMediaService;
use App\Services\PracticeQuiz\PracticeQuizService;
use App\Services\Quiz\QuizScopeService;
use Illuminate\Auth\GenericUser;
use Mockery;
use Tests\TestCase;

class PracticeQuizMediaHttpTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_private_audio_response_honors_http_range_and_nosniff_headers(): void
    {
        $file = storage_path('framework/testing/practice-range-audio.mp3');
        if (! is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, '0123456789');

        $scope = Mockery::mock(QuizScopeService::class);
        $scope->shouldReceive('teacherForUser')->once()->andReturn(['Teacher_ID' => 'T1']);
        $practices = Mockery::mock(PracticeQuizService::class);
        $practices->shouldReceive('mediaForTeacher')->once()->with('P1', 'Q1', 'audio', 'T1')->andReturn(['path' => 'practice-quiz-media/P1/R1/audio.mp3', 'mime' => 'audio/mpeg']);
        $media = Mockery::mock(PracticeQuizMediaService::class);
        $media->shouldReceive('absolutePath')->once()->andReturn($file);
        $this->app->instance(QuizScopeService::class, $scope);
        $this->app->instance(PracticeQuizService::class, $practices);
        $this->app->instance(PracticeQuizMediaService::class, $media);
        $this->actingAs(new GenericUser(['id' => 'U1', 'User_ID' => 'U1', 'password' => 'unused']));

        try {
            $response = $this->withoutMiddleware()->withHeader('Range', 'bytes=2-5')
                ->get('/teacher/practice-quizzes/P1/questions/Q1/media/audio');

            $response->assertStatus(206)
                ->assertHeader('Content-Type', 'audio/mpeg')
                ->assertHeader('Content-Range', 'bytes 2-5/10')
                ->assertHeader('X-Content-Type-Options', 'nosniff');
            $cacheControl = (string) $response->headers->get('Cache-Control');
            $this->assertStringContainsString('private', $cacheControl);
            $this->assertStringContainsString('must-revalidate', $cacheControl);
            $this->assertStringContainsString('no-transform', $cacheControl);
            $this->assertStringNotContainsString('public', $cacheControl);
        } finally {
            @unlink($file);
        }
    }
}

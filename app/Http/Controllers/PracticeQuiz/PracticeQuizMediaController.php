<?php

namespace App\Http\Controllers\PracticeQuiz;

use App\Http\Controllers\Controller;
use App\Services\PracticeQuiz\PracticeQuizMediaService;
use App\Services\PracticeQuiz\PracticeQuizService;
use App\Services\Quiz\QuizScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PracticeQuizMediaController extends Controller
{
    public function __construct(private PracticeQuizMediaService $media, private PracticeQuizService $practices, private QuizScopeService $scope) {}

    public function upload(Request $request)
    {
        $this->scope->teacherForUser($request->user());
        $data = $request->validate([
            'media_type' => ['required', 'in:image,audio'], 'source' => ['sometimes', 'in:upload,recording'],
            'file' => ['required', 'file', 'max:'.config('quiz.media.image_max_kb', 7168)],
        ]);
        $requestId = Str::isUuid((string) $request->header('X-Request-ID')) ? (string) $request->header('X-Request-ID') : (string) Str::uuid();
        $staged = $this->media->stage($request->file('file'), $data['media_type'], (string) $request->user()->User_ID, (string) ($data['source'] ?? 'upload'), $requestId);

        return response()->json($staged + ['preview_url' => route('teacher.practice-quizzes.media.staging', $staged['token']), 'request_id' => $requestId], 201)->header('X-Request-ID', $requestId);
    }

    public function staging(Request $request, string $token)
    {
        $this->scope->teacherForUser($request->user());
        $record = $this->media->staged($token, (string) $request->user()->User_ID);

        return $this->file($this->media->absolutePath($record), (string) $record['mime']);
    }

    public function teacher(Request $request, string $practice, string $question, string $type)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $media = $this->practices->mediaForTeacher($practice, $question, $type, (string) $teacher['Teacher_ID']);

        return $this->file($this->media->absolutePath($media), $media['mime']);
    }

    public function attempt(Request $request, string $attempt, string $question, string $type)
    {
        $student = $this->scope->studentForUser($request->user());
        $media = $this->practices->mediaForAttempt($attempt, $question, $type, $student);

        return $this->file($this->media->absolutePath($media), $media['mime']);
    }

    public function result(Request $request, string $result, string $question, string $type)
    {
        $student = $this->scope->studentForUser($request->user());
        $media = $this->practices->mediaForResult($result, $question, $type, $student);

        return $this->file($this->media->absolutePath($media), $media['mime']);
    }

    private function file(string $path, string $mime)
    {
        $response = response()->file($path, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('must-revalidate');
        $response->headers->addCacheControlDirective('no-transform');

        return $response;
    }
}

<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Services\Quiz\QuizMediaService;
use App\Services\Quiz\QuizScopeService;
use App\Services\Quiz\QuizService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class QuizMediaController extends Controller
{
    public function __construct(
        private QuizMediaService $media,
        private QuizService $quizzes,
        private QuizScopeService $scope,
    ) {}

    public function upload(Request $request)
    {
        $this->scope->teacherForUser($request->user());
        $requestId = trim((string) $request->header('X-Request-ID'));
        if (! Str::isUuid($requestId)) {
            $requestId = (string) Str::uuid();
        }
        try {
            $data = $request->validate([
                'media_type' => ['required', 'in:image,audio'],
                'source' => ['sometimes', 'in:upload,recording'],
                // The endpoint-specific 7 MB ceiling intentionally does not alter
                // the shared 5 MB WMS upload configuration used by other modules.
                'file' => ['required', 'file', 'max:'.config('quiz.media.image_max_kb', 7168)],
            ]);
            $staged = $this->media->stage(
                $request->file('file'),
                $data['media_type'],
                (string) $request->user()->User_ID,
                (string) ($data['source'] ?? 'upload'),
                $requestId,
            );
        } catch (\Throwable $exception) {
            if (strtolower((string) $request->input('media_type')) === 'audio') {
                $file = $request->file('file');
                if (! $file instanceof UploadedFile) {
                    $file = null;
                }
                Log::warning('Quiz audio upload request failed.', [
                    'request_id' => $requestId,
                    'stage' => 'request_or_staging',
                    'media_type' => 'audio',
                    'source' => strtolower((string) $request->input('source', 'upload')),
                    'extension' => $file?->getClientOriginalExtension(),
                    'detected_mime' => $file?->getMimeType(),
                    'file_size' => $file?->getSize(),
                    'codec' => null,
                    'duration_ms' => null,
                    'failure_reason_code' => strtolower(class_basename($exception)),
                    'process_exit_code' => null,
                    'timeout' => $exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 504,
                ]);
            }
            throw $exception;
        }

        return response()->json($staged + [
            'preview_url' => route('teacher.quizzes.media.staging', $staged['token']),
            'request_id' => $requestId,
        ], 201)->header('X-Request-ID', $requestId);
    }

    public function staging(Request $request, string $token)
    {
        $this->scope->teacherForUser($request->user());
        $record = $this->media->staged($token, (string) $request->user()->User_ID);

        return $this->fileResponse($this->media->absolutePath($record), (string) $record['mime']);
    }

    public function teacher(Request $request, string $quiz, string $question, string $type)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $classes = $this->scope->classesForTeacher($teacher['Teacher_ID'])->pluck('Class_ID')->all();
        $media = $this->quizzes->teacherMedia($quiz, $question, $type, $teacher['Teacher_ID'], $classes);

        return $this->fileResponse($this->media->absolutePath($media), $media['mime']);
    }

    public function attempt(Request $request, string $attempt, string $question, string $type)
    {
        $student = $this->scope->studentForUser($request->user());
        $media = $this->quizzes->attemptMedia($attempt, $question, $type, $student);

        return $this->fileResponse($this->media->absolutePath($media), $media['mime']);
    }

    public function result(Request $request, string $result, string $question, string $type)
    {
        $student = $this->scope->studentForUser($request->user());
        $media = $this->quizzes->resultMedia($result, $question, $type, $student);

        return $this->fileResponse($this->media->absolutePath($media), $media['mime']);
    }

    private function fileResponse(string $path, string $mime)
    {
        return response()->file($path, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, must-revalidate, no-transform',
        ]);
    }
}

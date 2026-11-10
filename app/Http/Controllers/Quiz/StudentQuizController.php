<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Services\Quiz\QuizScopeService;
use App\Services\Quiz\QuizService;
use Illuminate\Http\Request;

class StudentQuizController extends Controller
{
    public function __construct(private QuizService $quizzes, private QuizScopeService $scope) {}

    public function index(Request $request)
    {
        $student = $this->scope->studentForUser($request->user());

        return view('quiz.student.index', $this->quizzes->studentIndex($student));
    }

    public function start(Request $request, string $quiz)
    {
        $student = $this->scope->studentForUser($request->user());
        $payload = $this->quizzes->start($quiz, $student);

        return redirect()->route('student.quizzes.play', $payload['attempt']['Attempt_ID']);
    }

    public function play(Request $request, string $attempt)
    {
        $student = $this->scope->studentForUser($request->user());
        $payload = $this->quizzes->resume($attempt, $student);
        if (! empty($payload['finalized_result']['Result_ID'])) {
            return redirect()->route('student.quizzes.result', $payload['finalized_result']['Result_ID']);
        }

        return view('quiz.student.player', $payload);
    }

    public function autosave(Request $request, string $attempt)
    {
        $student = $this->scope->studentForUser($request->user());
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'answers' => ['required', 'array', 'max:'.config('quiz.max_questions', 100)],
            'answers.*' => ['required', 'in:A,B,C,D'],
        ]);
        $saved = $this->quizzes->autosave($attempt, $student, $data['answers'], (int) $data['version']);

        return response()->json($saved, ! empty($saved['conflict']) ? 409 : 200);
    }

    public function submit(Request $request, string $attempt)
    {
        $student = $this->scope->studentForUser($request->user());
        $data = $request->validate([
            'answers' => ['sometimes', 'array', 'max:'.config('quiz.max_questions', 100)],
            'answers.*' => ['nullable', 'in:A,B,C,D'],
            'answer_version' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);
        $result = $this->quizzes->submit(
            $attempt,
            $student,
            $data['answers'] ?? [],
            isset($data['answer_version']) ? (int) $data['answer_version'] : null,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'finalized' => true,
                'result_id' => $result['Result_ID'],
                'result_url' => route('student.quizzes.result', $result['Result_ID']),
            ]);
        }

        return redirect()->route('student.quizzes.result', $result['Result_ID']);
    }

    public function result(Request $request, string $result)
    {
        $student = $this->scope->studentForUser($request->user());
        $review = $this->quizzes->review($result, $student);
        $leaderboard = $this->quizzes->leaderboard($student['Class_ID']);

        return view('quiz.student.result', $review + $leaderboard + ['currentStudentId' => $student['Student_ID']]);
    }

    public function leaderboard(Request $request)
    {
        $student = $this->scope->studentForUser($request->user());

        return view('quiz.leaderboard', $this->quizzes->leaderboard($student['Class_ID']) + [
            'currentStudentId' => $student['Student_ID'], 'backRoute' => route('student.quizzes.index'),
        ]);
    }
}

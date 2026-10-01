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

        return view('quiz.student.player', $this->quizzes->resume($attempt, $student));
    }

    public function submit(Request $request, string $attempt)
    {
        $student = $this->scope->studentForUser($request->user());
        $data = $request->validate(['answers' => ['sometimes', 'array'], 'answers.*' => ['nullable', 'in:A,B,C,D']]);
        $result = $this->quizzes->submit($attempt, $student, $data['answers'] ?? []);

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

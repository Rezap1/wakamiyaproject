<?php

namespace App\Http\Controllers\PracticeQuiz;

use App\Http\Controllers\Controller;
use App\Services\PracticeQuiz\PracticeQuizService;
use App\Services\Quiz\QuizScopeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class StudentPracticeQuizController extends Controller
{
    public function __construct(private PracticeQuizService $practices, private QuizScopeService $scope) {}

    public function index(Request $request)
    {
        $student = $this->scope->studentForUser($request->user());

        return view('practice-quiz.student.index', [
            'practices' => $this->practices->studentCatalog($student, (string) $request->query('q')),
        ]);
    }

    public function start(Request $request, string $practice)
    {
        $student = $this->scope->studentForUser($request->user());
        $attempt = $this->practices->start($practice, $student);

        return redirect()->route('student.practice-quizzes.play', $attempt['Attempt_ID']);
    }

    public function play(Request $request, string $attempt)
    {
        $student = $this->scope->studentForUser($request->user());
        $payload = $this->practices->resume($attempt, $student);
        if (! empty($payload['finalized_result']['Result_ID'])) {
            return redirect()->route('student.practice-quizzes.result', $payload['finalized_result']['Result_ID']);
        }

        $payload['attempt'] = (array) $payload['attempt'];
        $payload['revision'] = (array) $payload['revision'];
        $payload['questions'] = collect($payload['questions'])->map(function (array $question) use ($attempt): array {
            foreach (['image', 'audio'] as $type) {
                $prefix = ucfirst($type);
                $question[$prefix.'_URL'] = empty($question[$prefix.'_Path']) ? null : route('student.practice-quizzes.attempt-media', [$attempt, $question['Question_ID'], $type]);
                unset($question[$prefix.'_Path']);
            }

            return $question;
        })->values()->all();
        $payload['savedAnswers'] = $payload['answers']->all();
        $payload['answerVersion'] = (int) $payload['attempt']['Answer_Version'];

        return view('practice-quiz.student.player', $payload);
    }

    public function autosave(Request $request, string $attempt)
    {
        $student = $this->scope->studentForUser($request->user());
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'answers' => ['required', 'array', 'max:'.config('quiz.max_questions', 100)],
            'answers.*' => ['required', 'in:A,B,C,D'],
        ]);
        $saved = $this->practices->autosave($attempt, $student, $data['answers'], (int) $data['version']);

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
        $result = $this->practices->submit($attempt, $student, $data['answers'] ?? [], isset($data['answer_version']) ? (int) $data['answer_version'] : null);
        if ($request->expectsJson()) {
            return response()->json(['finalized' => true, 'result_id' => $result['Result_ID'], 'result_url' => route('student.practice-quizzes.result', $result['Result_ID'])]);
        }

        return redirect()->route('student.practice-quizzes.result', $result['Result_ID']);
    }

    public function result(Request $request, string $result)
    {
        $student = $this->scope->studentForUser($request->user());

        return view('practice-quiz.student.result', $this->practices->review($result, $student));
    }

    public function history(Request $request, ?string $practice = null)
    {
        $student = $this->scope->studentForUser($request->user());
        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);

        return view('practice-quiz.student.history', $this->practices->history($student, $filters, $practice));
    }

    public function pdf(Request $request, ?string $practice = null)
    {
        $student = $this->scope->studentForUser($request->user());
        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $data = $this->practices->pdfData($student, $filters, $practice);
        $filename = $practice ? 'histori-kuis-latihan.pdf' : 'rekap-kuis-latihan.pdf';

        return Pdf::loadView('practice-quiz.pdf.history', $data)->setPaper('a4', 'portrait')->download($filename);
    }
}

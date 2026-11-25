<?php

namespace App\Http\Controllers\PracticeQuiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\PracticeQuiz\SavePracticeQuizRequest;
use App\Models\Practice\PracticeQuiz;
use App\Services\PracticeQuiz\PracticeQuizService;
use App\Services\Quiz\QuizScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TeacherPracticeQuizController extends Controller
{
    public function __construct(private PracticeQuizService $practices, private QuizScopeService $scope) {}

    public function index(Request $request)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        Gate::authorize('viewAny', [PracticeQuiz::class, (string) $teacher['Teacher_ID']]);

        return view('practice-quiz.teacher.index', [
            'practices' => $this->practices->teacherIndex((string) $teacher['Teacher_ID'], (string) $request->query('q')),
        ]);
    }

    public function create(Request $request)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        Gate::authorize('create', [PracticeQuiz::class, (string) $teacher['Teacher_ID']]);

        return response()->view('practice-quiz.teacher.form', ['quiz' => null, 'revision' => null, 'questions' => collect()])
            ->header('Permissions-Policy', 'microphone=(self)');
    }

    public function store(SavePracticeQuizRequest $request)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        Gate::authorize('create', [PracticeQuiz::class, (string) $teacher['Teacher_ID']]);
        $id = $this->practices->save(null, $request->payload(), (string) $teacher['Teacher_ID'], (string) $request->user()->User_ID);

        return redirect()->route('teacher.practice-quizzes.show', $id)->with('success', 'Kuis latihan berhasil disimpan.');
    }

    public function show(Request $request, string $practice)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        Gate::authorize('view', [$this->practice($practice), (string) $teacher['Teacher_ID']]);
        $payload = $this->withTeacherMedia($this->practices->teacherQuiz($practice, (string) $teacher['Teacher_ID']));

        return view('practice-quiz.teacher.show', $payload);
    }

    public function edit(Request $request, string $practice)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        Gate::authorize('update', [$this->practice($practice), (string) $teacher['Teacher_ID']]);

        return response()->view('practice-quiz.teacher.form', $this->withTeacherMedia($this->practices->teacherQuiz($practice, (string) $teacher['Teacher_ID'])))
            ->header('Permissions-Policy', 'microphone=(self)');
    }

    public function update(SavePracticeQuizRequest $request, string $practice)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        Gate::authorize('update', [$this->practice($practice), (string) $teacher['Teacher_ID']]);
        $this->practices->save($practice, $request->payload(), (string) $teacher['Teacher_ID'], (string) $request->user()->User_ID);

        return redirect()->route('teacher.practice-quizzes.show', $practice)->with('success', 'Kuis latihan berhasil diperbarui.');
    }

    public function destroy(Request $request, string $practice)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        Gate::authorize('delete', [$this->practice($practice), (string) $teacher['Teacher_ID']]);
        $this->practices->archive($practice, (string) $teacher['Teacher_ID'], (string) $request->user()->User_ID);

        return redirect()->route('teacher.practice-quizzes.index')->with('success', 'Kuis latihan diarsipkan. Histori siswa tetap dipertahankan.');
    }

    private function withTeacherMedia(array $payload): array
    {
        $quizId = $payload['quiz']['Practice_Quiz_ID'];
        $payload['questions'] = collect($payload['questions'])->map(function ($question) use ($quizId): array {
            $row = (array) $question;
            foreach (['image', 'audio'] as $type) {
                $prefix = ucfirst($type);
                $row[$prefix.'_URL'] = empty($row[$prefix.'_Path']) ? null : route('teacher.practice-quizzes.media', [$quizId, $row['Question_ID'], $type]);
            }

            return $row;
        });

        return $payload;
    }

    private function practice(string $id): PracticeQuiz
    {
        return PracticeQuiz::query()->findOrFail(trim($id));
    }
}

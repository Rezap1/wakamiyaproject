<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Services\Quiz\QuizScopeService;
use App\Services\Quiz\QuizService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TeacherQuizController extends Controller
{
    public function __construct(private QuizService $quizzes, private QuizScopeService $scope) {}

    public function index(Request $request)
    {
        $teacher = $this->scope->teacherForUser($request->user());

        return view('quiz.teacher.index', ['groups' => $this->quizzes->teacherIndex($teacher['Teacher_ID'])]);
    }

    public function create(Request $request)
    {
        $teacher = $this->scope->teacherForUser($request->user());

        return view('quiz.teacher.form', ['quiz' => null, 'questions' => collect(), 'classes' => $this->scope->classesForTeacher($teacher['Teacher_ID'])]);
    }

    public function store(Request $request)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $data = $this->validated($request);
        $this->scope->assertTeacherClass($teacher['Teacher_ID'], $data['Class_ID']);
        $id = $this->quizzes->create($data, $teacher, (string) $request->user()->User_ID);

        return redirect()->route('teacher.quizzes.show', $id)->with('success', 'Kuis berhasil disimpan.');
    }

    public function show(Request $request, string $quiz)
    {
        $teacher = $this->scope->teacherForUser($request->user());

        return view('quiz.teacher.show', ['quiz' => $this->quizzes->teacherQuiz($quiz, $teacher['Teacher_ID'])]);
    }

    public function edit(Request $request, string $quiz)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $row = $this->quizzes->teacherQuiz($quiz, $teacher['Teacher_ID']);
        abort_unless($row['Editable'], 409, 'Kuis tidak lagi dapat diedit.');

        return view('quiz.teacher.form', ['quiz' => $row, 'questions' => $row['questions'], 'classes' => $this->scope->classesForTeacher($teacher['Teacher_ID'])]);
    }

    public function update(Request $request, string $quiz)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $data = $this->validated($request);
        $this->scope->assertTeacherClass($teacher['Teacher_ID'], $data['Class_ID']);
        $this->quizzes->update($quiz, $data, $teacher, (string) $request->user()->User_ID);

        return redirect()->route('teacher.quizzes.show', $quiz)->with('success', 'Kuis berhasil diperbarui.');
    }

    public function destroy(Request $request, string $quiz)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $this->quizzes->deleteDraft($quiz, $teacher['Teacher_ID']);

        return redirect()->route('teacher.quizzes.index')->with('success', 'Draft kuis dihapus.');
    }

    public function results(Request $request)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $classId = trim((string) $request->query('class'));
        if ($classId !== '') {
            $this->scope->assertTeacherClass($teacher['Teacher_ID'], $classId);
        }

        return view('quiz.teacher.results', [
            'results' => $this->quizzes->teacherResults($teacher['Teacher_ID'], $classId ?: null),
            'classes' => $this->scope->classesForTeacher($teacher['Teacher_ID']),
            'selectedClass' => $classId,
        ]);
    }

    public function leaderboard(Request $request, string $class)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $this->scope->assertTeacherClass($teacher['Teacher_ID'], $class);

        return view('quiz.leaderboard', $this->quizzes->leaderboard($class) + ['currentStudentId' => null, 'backRoute' => route('teacher.quizzes.index')]);
    }

    private function validated(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'Title' => ['required', 'string', 'max:160'],
            'Class_ID' => ['required', 'string', 'max:80'],
            'Start_At' => ['required', 'date'],
            'End_At' => ['required', 'date', 'after:Start_At'],
            'Duration_Minutes' => ['required', 'integer', 'min:1', 'max:'.config('quiz.max_duration_minutes', 480)],
            'intent' => ['required', 'in:draft,publish'],
            'questions' => ['array'],
            'questions.*.Question_Text' => ['required', 'string', 'max:2000'],
            'questions.*.Option_A' => ['required', 'string', 'max:1000'],
            'questions.*.Option_B' => ['required', 'string', 'max:1000'],
            'questions.*.Option_C' => ['required', 'string', 'max:1000'],
            'questions.*.Option_D' => ['required', 'string', 'max:1000'],
            'questions.*.Correct_Option' => ['required', 'in:A,B,C,D'],
            'questions.*.Point' => ['required', 'numeric', 'gt:0', 'max:10000'],
        ]);
        $validator->after(function ($validator) use ($request) {
            if ($request->input('intent') === 'publish' && count((array) $request->input('questions', [])) === 0) {
                $validator->errors()->add('questions', 'Minimal satu soal diperlukan sebelum kuis diterbitkan.');
            }
        });
        $data = $validator->validate();
        $data['questions'] = array_values($data['questions'] ?? []);

        return $data;
    }
}

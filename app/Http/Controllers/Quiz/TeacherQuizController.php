<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Services\Quiz\QuizScopeService;
use App\Services\Quiz\QuizService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TeacherQuizController extends Controller
{
    public function __construct(private QuizService $quizzes, private QuizScopeService $scope) {}

    public function index(Request $request)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $classes = $this->scope->classesForTeacher($teacher['Teacher_ID']);
        $classIds = $classes->pluck('Class_ID')->all();

        return view('quiz.teacher.index', [
            'classes' => $this->quizzes->teacherClassIndex(
                $teacher['Teacher_ID'],
                $classes,
                $this->scope->studentCountsForClasses($classIds),
            ),
        ]);
    }

    public function create(Request $request)
    {
        $teacher = $this->scope->teacherForUser($request->user());

        return response()->view('quiz.teacher.form', ['quiz' => null, 'questions' => collect(), 'classes' => $this->scope->classesForTeacher($teacher['Teacher_ID'])])
            ->header('Permissions-Policy', 'microphone=(self)');
    }

    public function classHub(Request $request, string $class)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $classes = $this->scope->classesForTeacher($teacher['Teacher_ID']);
        $selectedClass = $classes->first(
            fn ($row) => trim((string) ($row['Class_ID'] ?? '')) === trim($class)
        );
        abort_unless($selectedClass, 403, 'Kelas berada di luar penugasan kelas Anda.');
        $hub = $this->quizzes->teacherClassHub(
            $teacher['Teacher_ID'],
            (string) $selectedClass['Class_ID'],
            $this->scope->studentsForClass((string) $selectedClass['Class_ID']),
        );

        return view('quiz.teacher.class-hub', $hub + [
            'class' => $selectedClass,
        ]);
    }

    public function createForClass(Request $request, string $class)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $classes = $this->scope->classesForTeacher($teacher['Teacher_ID']);
        $selectedClass = $classes->first(
            fn ($row) => trim((string) ($row['Class_ID'] ?? '')) === trim($class)
        );
        abort_unless($selectedClass, 403, 'Kelas berada di luar penugasan kelas Anda.');

        return response()->view('quiz.teacher.form', [
            'quiz' => null,
            'questions' => collect(),
            'classes' => collect([$selectedClass]),
            'lockedClass' => $selectedClass,
        ])->header('Permissions-Policy', 'microphone=(self)');
    }

    public function store(Request $request)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $data = $this->validated($request);
        foreach ($data['Class_IDs'] as $classId) {
            $this->scope->assertTeacherClass($teacher['Teacher_ID'], $classId);
        }
        $id = $this->quizzes->create($data, $teacher, (string) $request->user()->User_ID);

        return redirect()->route('teacher.quizzes.show', $id)->with('success', 'Kuis berhasil disimpan.');
    }

    public function storeForClass(Request $request, string $class)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $classes = $this->scope->classesForTeacher($teacher['Teacher_ID']);
        $selectedClass = $classes->first(
            fn ($row) => trim((string) ($row['Class_ID'] ?? '')) === trim($class)
        );
        abort_unless($selectedClass, 403, 'Kelas berada di luar penugasan kelas Anda.');
        $request->merge(['Class_ID' => (string) $selectedClass['Class_ID'], 'Class_IDs' => [(string) $selectedClass['Class_ID']]]);
        $data = $this->validated($request);
        $id = $this->quizzes->create($data, $teacher, (string) $request->user()->User_ID);

        return redirect()->route('teacher.quizzes.show', $id)->with('success', 'Kuis berhasil disimpan.');
    }

    public function show(Request $request, string $quiz)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $classes = $this->scope->classesForTeacher($teacher['Teacher_ID']);
        $classIds = $classes->pluck('Class_ID')->all();
        $row = $this->quizzes->teacherQuiz($quiz, $teacher['Teacher_ID'], $classIds);

        return view('quiz.teacher.show', [
            'quiz' => $row,
            'className' => $classes->firstWhere('Class_ID', $row['Class_ID'])['Class_Name'] ?? 'Kelas Anda',
            'audienceClasses' => $classes->filter(fn ($class) => in_array((string) $class['Class_ID'], $row['Audience_Class_IDs'] ?? [], true))->values(),
        ]);
    }

    public function edit(Request $request, string $quiz)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $classes = $this->scope->classesForTeacher($teacher['Teacher_ID']);
        $row = $this->quizzes->teacherQuiz($quiz, $teacher['Teacher_ID'], $classes->pluck('Class_ID')->all());
        abort_unless($row['Editable'], 409, 'Kuis tidak lagi dapat diedit.');

        return response()->view('quiz.teacher.form', ['quiz' => $row, 'questions' => $row['questions'], 'classes' => $classes])
            ->header('Permissions-Policy', 'microphone=(self)');
    }

    public function update(Request $request, string $quiz)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $classIds = $this->scope->classesForTeacher($teacher['Teacher_ID'])->pluck('Class_ID')->all();
        $data = $this->validated($request);
        abort_unless(collect($data['Class_IDs'])->every(fn ($classId) => in_array($classId, $classIds, true)), 403, 'Satu atau lebih kelas berada di luar penugasan kelas Anda.');
        $this->quizzes->update($quiz, $data, $teacher, (string) $request->user()->User_ID, $classIds);

        return redirect()->route('teacher.quizzes.show', $quiz)->with('success', 'Kuis berhasil diperbarui.');
    }

    public function destroy(Request $request, string $quiz)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $classIds = $this->scope->classesForTeacher($teacher['Teacher_ID'])->pluck('Class_ID')->all();
        try {
            $deleted = $this->quizzes->deleteQuiz(
                $quiz,
                $teacher['Teacher_ID'],
                $classIds,
                (string) $request->user()->User_ID,
                $request->ip(),
                $request->userAgent(),
            );
        } catch (\DomainException $exception) {
            return back()->with('error', $this->safeExceptionMessage(
                $exception,
                'Kuis yang sudah dimulai atau memiliki percobaan/hasil tidak dapat dihapus.'
            ));
        } catch (\RuntimeException $exception) {
            Log::error('Quiz permanent deletion failed verification.', [
                'quiz_id' => $quiz,
                'teacher_id' => $teacher['Teacher_ID'],
                'exception' => $exception,
            ]);

            return back()->with('error', 'Kuis belum dapat dihapus sepenuhnya. Tidak ada tahap berikutnya yang dijalankan; silakan coba lagi.');
        }

        return redirect()->route('teacher.quizzes.class', $deleted['quiz']['Class_ID'])->with('success', 'Kuis berhasil dihapus. Soal, hasil siswa, dan poin leaderboard terkait juga telah dihapus.');
    }

    public function resetResults(Request $request, string $quiz)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $classIds = $this->scope->classesForTeacher($teacher['Teacher_ID'])->pluck('Class_ID')->all();
        $reset = $this->quizzes->resetResults(
            $quiz,
            $teacher['Teacher_ID'],
            $classIds,
            (string) $request->user()->User_ID,
            $request->ip(),
            $request->userAgent(),
        );

        return redirect()->route('teacher.quizzes.show', $quiz)->with(
            'success',
            "Hasil kuis berhasil direset ({$reset['deleted']['attempts']} percobaan, {$reset['deleted']['results']} hasil). Soal dan media tetap dipertahankan."
        );
    }

    public function results(Request $request)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $classes = $this->scope->classesForTeacher($teacher['Teacher_ID']);
        $requestedClass = trim((string) $request->query('class'));
        if ($requestedClass !== '' && ! $classes->contains(
            fn ($class) => trim((string) ($class['Class_ID'] ?? '')) === $requestedClass
        )) {
            abort(403, 'Kelas berada di luar penugasan kelas Anda.');
        }
        $selectedClass = $requestedClass;
        $roster = $selectedClass !== '' ? $this->scope->studentsForClass($selectedClass) : collect();
        $overview = $selectedClass !== ''
            ? $this->quizzes->teacherResults($teacher['Teacher_ID'], $selectedClass, $roster)
            : ['groups' => collect(), 'result_count' => 0, 'student_count' => 0];

        return view('quiz.teacher.results', [
            'overview' => $overview,
            'classes' => $classes,
            'selectedClass' => $selectedClass,
            'selectedClassRow' => $classes->firstWhere('Class_ID', $selectedClass),
        ]);
    }

    public function leaderboard(Request $request, string $class)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $classes = $this->scope->classesForTeacher($teacher['Teacher_ID']);
        $selectedClass = $classes->first(
            fn ($row) => trim((string) ($row['Class_ID'] ?? '')) === trim($class)
        );
        abort_unless($selectedClass, 403, 'Kelas berada di luar penugasan kelas Anda.');

        return view('quiz.leaderboard', $this->quizzes->leaderboard($class) + [
            'currentStudentId' => null,
            'backRoute' => route('teacher.quizzes.class', $class),
            'classId' => $class,
            'className' => $selectedClass['Class_Name'] ?? $class,
            'classOptions' => $classes,
        ]);
    }

    public function result(Request $request, string $result)
    {
        $teacher = $this->scope->teacherForUser($request->user());
        $classIds = $this->scope->classesForTeacher($teacher['Teacher_ID'])->pluck('Class_ID')->all();

        return view('quiz.teacher.result', [
            'result' => $this->quizzes->teacherResult($result, $teacher['Teacher_ID'], $classIds),
        ]);
    }

    private function validated(Request $request): array
    {
        $maxQuestions = (int) config('quiz.max_questions', 100);
        $classIds = $request->input('Class_IDs');
        if (! is_array($classIds)) {
            $legacyClassId = trim((string) $request->input('Class_ID'));
            $classIds = $legacyClassId === '' ? [] : [$legacyClassId];
        }
        $request->merge(['Class_IDs' => array_values($classIds)]);
        $validator = Validator::make($request->all(), [
            'Title' => ['required', 'string', 'max:160'],
            'Class_ID' => ['nullable', 'string', 'max:80'],
            'Class_IDs' => ['required', 'array', 'min:1'],
            'Class_IDs.*' => ['required', 'string', 'max:80', 'distinct'],
            'Start_At' => ['required', 'date'],
            'End_At' => ['required', 'date', 'after:Start_At'],
            'Duration_Minutes' => ['required', 'integer', 'min:1', 'max:'.config('quiz.max_duration_minutes', 480)],
            'Idempotency_Key' => ['required', 'uuid'],
            'intent' => ['required', 'in:draft,publish'],
            'question_count' => ['required', 'integer', 'min:1', 'max:'.$maxQuestions],
            'questions' => ['required', 'array', 'min:1', 'max:'.$maxQuestions],
            'questions.*.Question_Text' => ['nullable', 'string', 'max:2000'],
            'questions.*.Option_A' => ['required_if:intent,publish', 'nullable', 'string', 'max:1000'],
            'questions.*.Option_B' => ['required_if:intent,publish', 'nullable', 'string', 'max:1000'],
            'questions.*.Option_C' => ['required_if:intent,publish', 'nullable', 'string', 'max:1000'],
            'questions.*.Option_D' => ['required_if:intent,publish', 'nullable', 'string', 'max:1000'],
            'questions.*.Correct_Option' => ['required_if:intent,publish', 'nullable', 'in:A,B,C,D'],
            'questions.*.Point' => ['required_if:intent,publish', 'nullable', 'numeric', 'gt:0', 'max:10000'],
            'questions.*.Image_Token' => ['nullable', 'uuid'],
            'questions.*.Audio_Token' => ['nullable', 'uuid'],
            'questions.*.Remove_Image' => ['nullable', 'boolean'],
            'questions.*.Remove_Audio' => ['nullable', 'boolean'],
        ], [
            'question_count.required' => 'Jumlah soal wajib diisi.',
            'question_count.integer' => 'Jumlah soal harus berupa bilangan bulat.',
            'question_count.min' => 'Jumlah soal minimal 1.',
            'question_count.max' => "Jumlah soal maksimal {$maxQuestions}.",
            'questions.required' => 'Minimal satu soal diperlukan.',
            'questions.required_if' => 'Minimal satu soal diperlukan sebelum kuis diterbitkan.',
            'questions.min' => 'Minimal satu soal diperlukan sebelum kuis diterbitkan.',
            'questions.max' => "Jumlah soal maksimal {$maxQuestions}.",
            'questions.*.Option_A.required_if' => 'Pilihan A wajib diisi sebelum kuis diterbitkan.',
            'questions.*.Option_B.required_if' => 'Pilihan B wajib diisi sebelum kuis diterbitkan.',
            'questions.*.Option_C.required_if' => 'Pilihan C wajib diisi sebelum kuis diterbitkan.',
            'questions.*.Option_D.required_if' => 'Pilihan D wajib diisi sebelum kuis diterbitkan.',
            'questions.*.Correct_Option.required_if' => 'Jawaban benar wajib dipilih sebelum kuis diterbitkan.',
            'questions.*.Correct_Option.in' => 'Jawaban benar harus A, B, C, atau D.',
            'questions.*.Point.required_if' => 'Poin wajib diisi sebelum kuis diterbitkan.',
            'questions.*.Point.numeric' => 'Poin harus berupa angka.',
            'questions.*.Point.gt' => 'Poin harus lebih besar dari 0.',
        ]);
        $validator->after(function ($validator) use ($request) {
            $questions = $request->input('questions', []);
            if (is_array($questions)) {
                if (! array_is_list($questions)) {
                    $validator->errors()->add('questions', 'Urutan soal tidak valid. Gunakan indeks berurutan mulai dari 0.');
                }

                $requestedCount = filter_var($request->input('question_count'), FILTER_VALIDATE_INT);
                if ($requestedCount !== false && $requestedCount !== count($questions)) {
                    $validator->errors()->add('question_count', 'Jumlah soal tidak sesuai dengan form soal yang dikirim.');
                }
            }

            if ($request->input('intent') === 'publish' && count((array) $request->input('questions', [])) === 0) {
                $validator->errors()->add('questions', 'Minimal satu soal diperlukan sebelum kuis diterbitkan.');
            }
        });
        $data = $validator->validate();
        $data['Class_IDs'] = collect($data['Class_IDs'])->map(fn ($id) => trim((string) $id))->filter()->unique()->values()->all();
        $data['Class_ID'] = $data['Class_IDs'][0];
        $data['questions'] = collect(array_values($data['questions'] ?? []))->map(fn ($question) => [
            'Question_Text' => trim((string) ($question['Question_Text'] ?? '')),
            'Option_A' => trim((string) ($question['Option_A'] ?? '')),
            'Option_B' => trim((string) ($question['Option_B'] ?? '')),
            'Option_C' => trim((string) ($question['Option_C'] ?? '')),
            'Option_D' => trim((string) ($question['Option_D'] ?? '')),
            'Correct_Option' => strtoupper(trim((string) ($question['Correct_Option'] ?? 'A'))),
            'Point' => (float) ($question['Point'] ?? 10),
            'Image_Token' => trim((string) ($question['Image_Token'] ?? '')),
            'Audio_Token' => trim((string) ($question['Audio_Token'] ?? '')),
            'Remove_Image' => filter_var($question['Remove_Image'] ?? false, FILTER_VALIDATE_BOOL),
            'Remove_Audio' => filter_var($question['Remove_Audio'] ?? false, FILTER_VALIDATE_BOOL),
        ])->all();

        return $data;
    }
}

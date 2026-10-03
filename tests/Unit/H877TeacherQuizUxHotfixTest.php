<?php

namespace Tests\Unit;

use App\Http\Controllers\Quiz\TeacherQuizController;
use App\Services\Quiz\QuizScopeService;
use App\Services\Quiz\QuizService;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class H877TeacherQuizUxHotfixTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_question_builder_renders_count_input_and_usable_a_to_d_template(): void
    {
        $html = view('quiz.teacher.form', [
            'quiz' => null,
            'questions' => collect(),
            'classes' => collect([['Class_ID' => 'C1', 'Class_Name' => 'Kelas A']]),
            'errors' => new ViewErrorBag,
        ])->render();

        foreach (['A', 'B', 'C', 'D'] as $letter) {
            $this->assertStringContainsString("Option_{$letter}", $html);
        }
        $this->assertStringContainsString('Masukkan pilihan ${letter}', $html);
        $this->assertStringContainsString('data-option-input', $html);
        $this->assertStringContainsString('data-question-card', $html);
        $this->assertStringContainsString('Jumlah Soal', $html);
        $this->assertStringContainsString('type="number" min="1" max="100" step="1"', $html);
        $this->assertStringContainsString('name="question_count"', $html);
        $this->assertStringContainsString('@click="applyQuestionCount"', $html);
        $this->assertStringContainsString('Buat ${count} Soal', $html);
        $this->assertStringNotContainsString('>+ Soal<', $html);
        $this->assertStringNotContainsString('Hapus Soal', $html);
        $this->assertStringNotContainsString('<label class="text-sm font-bold" x-text="letter">', $html);
    }

    public function test_count_builder_preserves_prefix_and_requires_confirmation_before_tail_removal(): void
    {
        $source = file_get_contents(resource_path('views/quiz/teacher/form.blade.php'));

        $this->assertStringContainsString('while (this.questions.length < target)', $source);
        $this->assertStringContainsString('this.questions.push(fresh())', $source);
        $this->assertStringContainsString('window.confirm(', $source);
        $this->assertStringContainsString('this.requestedCount = this.questions.length;', $source);
        $this->assertStringContainsString('this.questions.splice(target);', $source);
        $this->assertStringNotContainsString('this.questions = []', $source);
    }

    public function test_edit_seed_repopulates_all_question_fields(): void
    {
        $html = view('quiz.teacher.form', [
            'quiz' => [
                'Quiz_ID' => 'Q1', 'Title' => 'Kuis Edit', 'Class_ID' => 'C1',
                'Duration_Minutes' => 30, 'Start_At' => '2026-10-10 08:00:00',
                'End_At' => '2026-10-10 09:00:00',
            ],
            'questions' => collect([[
                'Question_ID' => 'QQ1', 'Question_Text' => 'Well',
                'Option_A' => 'Baik', 'Option_B' => 'Sumur', 'Option_C' => 'Akan',
                'Option_D' => 'Dengan', 'Correct_Option' => 'B', 'Point' => 15,
            ]]),
            'classes' => collect([['Class_ID' => 'C1', 'Class_Name' => 'Kelas A']]),
            'errors' => new ViewErrorBag,
        ])->render();

        foreach (['Well', 'Baik', 'Sumur', 'Akan', 'Dengan', 'Correct_Option', '15'] as $value) {
            $this->assertStringContainsString($value, $html);
        }
        $this->assertStringContainsString('requestedCount: initialQuestions.length', $html);
    }

    public function test_edit_builder_initializes_count_from_all_existing_questions(): void
    {
        $questions = collect(range(1, 25))->map(fn (int $index) => [
            'Question_ID' => 'QQ'.$index,
            'Question_Text' => 'Pertanyaan '.$index,
            'Option_A' => 'A'.$index,
            'Option_B' => 'B'.$index,
            'Option_C' => 'C'.$index,
            'Option_D' => 'D'.$index,
            'Correct_Option' => 'A',
            'Point' => 10,
        ]);
        $html = view('quiz.teacher.form', [
            'quiz' => [
                'Quiz_ID' => 'Q25', 'Title' => 'Kuis 25 Soal', 'Class_ID' => 'C1',
                'Duration_Minutes' => 30, 'Start_At' => '2026-10-10 08:00:00',
                'End_At' => '2026-10-10 09:00:00',
            ],
            'questions' => $questions,
            'classes' => collect([['Class_ID' => 'C1', 'Class_Name' => 'Kelas A']]),
            'errors' => new ViewErrorBag,
        ])->render();

        foreach (range(1, 25) as $index) {
            $this->assertStringContainsString('QQ'.$index, $html);
        }
        $this->assertStringContainsString('requestedCount: initialQuestions.length', $html);
    }

    #[DataProvider('supportedQuestionCountProvider')]
    public function test_question_count_accepts_supported_sizes_and_preserves_order(int $count): void
    {
        $validated = $this->validate($this->validPayload($count));

        $this->assertSame($count, $validated['question_count']);
        $this->assertCount($count, $validated['questions']);
        $this->assertSame(
            range(1, $count),
            collect($validated['questions'])->pluck('Question_Text')->map(
                fn (string $text) => (int) str_replace('Pertanyaan ', '', $text)
            )->all(),
        );
    }

    public static function supportedQuestionCountProvider(): array
    {
        return [
            'one' => [1],
            'ten' => [10],
            'twenty' => [20],
            'maximum' => [100],
        ];
    }

    #[DataProvider('invalidQuestionCountProvider')]
    public function test_question_count_rejects_invalid_sizes(mixed $count, int $payloadSize): void
    {
        $payload = $this->validPayload(max(1, $payloadSize));
        $payload['question_count'] = $count;
        if ($payloadSize === 0) {
            $payload['questions'] = [];
        }

        try {
            $this->validate($payload);
            $this->fail('Invalid question count must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('question_count', $exception->errors());
        }
    }

    public static function invalidQuestionCountProvider(): array
    {
        return [
            'zero' => [0, 0],
            'negative' => [-1, 1],
            'above maximum' => [101, 101],
            'fractional' => ['1.5', 1],
        ];
    }

    public function test_question_count_must_match_a_sequential_payload(): void
    {
        $mismatch = $this->validPayload(2);
        $mismatch['question_count'] = 3;
        try {
            $this->validate($mismatch);
            $this->fail('A mismatched count must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Jumlah soal tidak sesuai dengan form soal yang dikirim.',
                $exception->errors()['question_count'][0],
            );
        }

        $sparse = $this->validPayload(2);
        $sparse['questions'] = [0 => $sparse['questions'][0], 2 => $sparse['questions'][1]];
        try {
            $this->validate($sparse);
            $this->fail('Sparse question indexes must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Urutan soal tidak valid. Gunakan indeks berurutan mulai dari 0.',
                $exception->errors()['questions'][0],
            );
        }
    }

    public function test_class_hub_create_form_locks_authorized_class_context(): void
    {
        $html = view('quiz.teacher.form', [
            'quiz' => null,
            'questions' => collect(),
            'classes' => collect([['Class_ID' => 'C1', 'Class_Name' => 'Kelas A']]),
            'lockedClass' => ['Class_ID' => 'C1', 'Class_Name' => 'Kelas A'],
            'errors' => new ViewErrorBag,
        ])->render();

        $this->assertStringContainsString('action="'.route('teacher.quizzes.class.store', 'C1').'"', $html);
        $this->assertStringContainsString('type="hidden" name="Class_ID" value="C1"', $html);
        $this->assertStringContainsString('Kelas A', $html);
        $this->assertStringNotContainsString('<select name="Class_ID"', $html);
    }

    #[DataProvider('invalidPublishedQuestionProvider')]
    public function test_publish_rejects_incomplete_or_invalid_question(string $field, mixed $value, string $errorKey, string $message): void
    {
        $payload = $this->validPayload();
        if ($value === null) {
            unset($payload['questions'][0][$field]);
        } else {
            $payload['questions'][0][$field] = $value;
        }

        try {
            $this->validate($payload);
            $this->fail("Invalid {$field} must be rejected.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($errorKey, $exception->errors());
            $this->assertSame($message, $exception->errors()[$errorKey][0]);
        }
    }

    public static function invalidPublishedQuestionProvider(): array
    {
        return [
            'missing A' => ['Option_A', null, 'questions.0.Option_A', 'Pilihan A wajib diisi sebelum kuis diterbitkan.'],
            'missing B' => ['Option_B', null, 'questions.0.Option_B', 'Pilihan B wajib diisi sebelum kuis diterbitkan.'],
            'missing C' => ['Option_C', null, 'questions.0.Option_C', 'Pilihan C wajib diisi sebelum kuis diterbitkan.'],
            'missing D' => ['Option_D', null, 'questions.0.Option_D', 'Pilihan D wajib diisi sebelum kuis diterbitkan.'],
            'invalid correct option' => ['Correct_Option', 'E', 'questions.0.Correct_Option', 'Jawaban benar harus A, B, C, atau D.'],
            'non-positive point' => ['Point', 0, 'questions.0.Point', 'Poin harus lebih besar dari 0.'],
        ];
    }

    public function test_draft_may_preserve_an_incomplete_question_without_bypassing_publish_validation(): void
    {
        $payload = $this->validPayload();
        $payload['intent'] = 'draft';
        unset($payload['questions'][0]['Option_D'], $payload['questions'][0]['Point']);

        $validated = $this->validate($payload);

        $this->assertSame('', $validated['questions'][0]['Option_D']);
        $this->assertSame(10.0, $validated['questions'][0]['Point']);
    }

    private function validate(array $payload): array
    {
        $controller = new TeacherQuizController(
            Mockery::mock(QuizService::class),
            Mockery::mock(QuizScopeService::class),
        );
        $method = new ReflectionMethod($controller, 'validated');
        $method->setAccessible(true);

        return $method->invoke($controller, Request::create('/teacher/quizzes', 'POST', $payload));
    }

    private function validPayload(int $count = 1): array
    {
        return [
            'Title' => 'Kosakata',
            'Class_ID' => 'C1',
            'Start_At' => '2026-10-10 08:00:00',
            'End_At' => '2026-10-10 09:00:00',
            'Duration_Minutes' => 30,
            'intent' => 'publish',
            'question_count' => $count,
            'questions' => collect(range(1, $count))->map(fn (int $index) => [
                'Question_Text' => 'Pertanyaan '.$index,
                'Option_A' => 'A'.$index,
                'Option_B' => 'B'.$index,
                'Option_C' => 'C'.$index,
                'Option_D' => 'D'.$index,
                'Correct_Option' => 'B',
                'Point' => 15,
            ])->all(),
        ];
    }
}

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

    public function test_question_builder_renders_usable_a_to_d_inputs_and_complete_dynamic_template(): void
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
        $this->assertStringContainsString('questions.push(fresh())', $html);
        $this->assertStringContainsString('questions.splice(index, 1)', $html);
        $this->assertStringNotContainsString('<label class="text-sm font-bold" x-text="letter">', $html);
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

    private function validPayload(): array
    {
        return [
            'Title' => 'Kosakata',
            'Class_ID' => 'C1',
            'Start_At' => '2026-10-10 08:00:00',
            'End_At' => '2026-10-10 09:00:00',
            'Duration_Minutes' => 30,
            'intent' => 'publish',
            'questions' => [[
                'Question_Text' => 'Well',
                'Option_A' => 'Baik',
                'Option_B' => 'Sumur',
                'Option_C' => 'Akan',
                'Option_D' => 'Dengan',
                'Correct_Option' => 'B',
                'Point' => 15,
            ]],
        ];
    }
}

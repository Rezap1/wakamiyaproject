<?php

namespace App\Http\Requests\PracticeQuiz;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SavePracticeQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $max = (int) config('quiz.max_questions', 100);

        return [
            'Title' => ['required', 'string', 'max:160'],
            'Description' => ['nullable', 'string', 'max:5000'],
            'Instructions' => ['nullable', 'string', 'max:5000'],
            'Duration_Minutes' => ['required', 'integer', 'min:1', 'max:'.config('quiz.max_duration_minutes', 480)],
            'Idempotency_Key' => ['required', 'uuid'],
            'intent' => ['required', 'in:draft,publish'],
            'question_count' => ['required', 'integer', 'min:1', 'max:'.$max],
            'questions' => ['required', 'array', 'min:1', 'max:'.$max],
            'questions.*.Question_Text' => ['nullable', 'string', 'max:2000'],
            'questions.*.Option_A' => ['required_if:intent,publish', 'nullable', 'string', 'max:1000'],
            'questions.*.Option_B' => ['required_if:intent,publish', 'nullable', 'string', 'max:1000'],
            'questions.*.Option_C' => ['required_if:intent,publish', 'nullable', 'string', 'max:1000'],
            'questions.*.Option_D' => ['required_if:intent,publish', 'nullable', 'string', 'max:1000'],
            'questions.*.Correct_Option' => ['required_if:intent,publish', 'nullable', 'in:A,B,C,D'],
            'questions.*.Point' => ['required_if:intent,publish', 'nullable', 'numeric', 'gt:0', 'max:10000'],
            'questions.*.Explanation' => ['nullable', 'string', 'max:5000'],
            'questions.*.Image_Token' => ['nullable', 'uuid'],
            'questions.*.Audio_Token' => ['nullable', 'uuid'],
            'questions.*.Remove_Image' => ['nullable', 'boolean'],
            'questions.*.Remove_Audio' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $questions = $this->input('questions', []);
            if (! is_array($questions) || ! array_is_list($questions)) {
                $validator->errors()->add('questions', 'Urutan soal tidak valid.');

                return;
            }
            if ((int) $this->input('question_count') !== count($questions)) {
                $validator->errors()->add('question_count', 'Jumlah soal tidak sesuai dengan form soal.');
            }
            if ($this->input('intent') === 'publish') {
                foreach ($questions as $index => $question) {
                    if (trim((string) ($question['Question_Text'] ?? '')) === '') {
                        $validator->errors()->add("questions.{$index}.Question_Text", 'Teks soal wajib diisi sebelum diterbitkan.');
                    }
                }
            }
        }];
    }

    public function payload(): array
    {
        $data = $this->validated();
        $data['questions'] = collect(array_values($data['questions']))->map(fn (array $question): array => [
            'Question_Text' => trim((string) ($question['Question_Text'] ?? '')),
            'Option_A' => trim((string) ($question['Option_A'] ?? '')),
            'Option_B' => trim((string) ($question['Option_B'] ?? '')),
            'Option_C' => trim((string) ($question['Option_C'] ?? '')),
            'Option_D' => trim((string) ($question['Option_D'] ?? '')),
            'Correct_Option' => strtoupper(trim((string) ($question['Correct_Option'] ?? 'A'))),
            'Point' => (float) ($question['Point'] ?? 10),
            'Explanation' => trim((string) ($question['Explanation'] ?? '')),
            'Image_Token' => trim((string) ($question['Image_Token'] ?? '')),
            'Audio_Token' => trim((string) ($question['Audio_Token'] ?? '')),
            'Remove_Image' => filter_var($question['Remove_Image'] ?? false, FILTER_VALIDATE_BOOL),
            'Remove_Audio' => filter_var($question['Remove_Audio'] ?? false, FILTER_VALIDATE_BOOL),
        ])->all();

        return $data;
    }
}

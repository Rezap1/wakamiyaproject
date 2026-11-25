<?php

namespace Tests\Feature;

use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class PracticeQuizUiRenderTest extends TestCase
{
    public function test_teacher_builder_renders_unicode_multimedia_recording_and_explanation_controls(): void
    {
        view()->share('errors', new ViewErrorBag);
        $html = view('practice-quiz.teacher.form', ['quiz' => null, 'revision' => null, 'questions' => collect()])->render();

        foreach (['Kuis Latihan', 'Judul Kuis Latihan', 'Instruksi', 'Pilih Gambar', 'Pilih Audio', 'Rekam Suara', 'Pembahasan', 'Simpan Draft', 'Terbitkan', 'snapshotAudioFile', 'QMU-FILE-ACCESS'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }

    public function test_player_renders_unicode_timer_autosave_navigation_and_media_without_answer_keys(): void
    {
        $html = view('practice-quiz.student.player', [
            'attempt' => ['Attempt_ID' => 'A1', 'Deadline_At' => '2026-11-24 09:00:00', 'Answer_Version' => 2],
            'revision' => ['Title' => '聴解 Latihan'],
            'questions' => [[
                'Question_ID' => 'Q1', 'Question_Text' => 'これは何ですか。',
                'Option_A' => '本', 'Option_B' => '車', 'Option_C' => '家', 'Option_D' => '水',
                'Image_URL' => '/private/image', 'Audio_URL' => '/private/audio',
            ]],
            'savedAnswers' => ['Q1' => 'A'], 'answerVersion' => 2,
        ])->render();

        foreach (['聴解 Latihan', 'Sisa waktu', 'Jawaban tersimpan di server', 'Sebelumnya', 'Berikutnya', 'Kirim Jawaban', 'Audio dapat diputar'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('Correct_Option', $html);
        $this->assertStringNotContainsString('Explanation', $html);
    }
}

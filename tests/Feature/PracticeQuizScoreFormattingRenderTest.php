<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class PracticeQuizScoreFormattingRenderTest extends TestCase
{
    public function test_practice_index_and_result_render_scores_without_unnecessary_zeroes(): void
    {
        $practice = (object) [
            'Practice_Quiz_ID' => 'P1',
            'Title' => 'Latihan Nilai',
            'Teacher_Name' => 'Sensei',
            'Teacher_ID' => 'T1',
            'Deleted_At' => null,
            'Description' => 'Deskripsi',
            'Question_Count' => 10,
            'Duration_Minutes' => 15,
            'Attempt_Count' => 2,
            'Active_Attempt_ID' => null,
            'Last_Score' => '85.00',
            'Best_Score' => '100.00',
        ];
        $practices = new LengthAwarePaginator([$practice], 1, 15, 1, ['path' => '/student/practice-quizzes']);

        $index = view('practice-quiz.student.index', compact('practices'))->render();

        $this->assertStringContainsString('<strong>85</strong>', $index);
        $this->assertStringContainsString('<strong>100</strong>', $index);
        $this->assertStringNotContainsString('85.00', $index);
        $this->assertStringNotContainsString('100.00', $index);

        $result = (object) [
            'Result_ID' => 'R1',
            'Revision_ID' => 'REVISION-1',
            'Practice_Quiz_ID' => 'P1',
            'Quiz_Title' => 'Latihan Nilai',
            'Attempt_Number' => 2,
            'Normalized_Score' => '87.50',
            'Correct_Count' => 7,
            'Incorrect_Count' => 2,
            'Unanswered_Count' => 1,
            'Duration_Seconds' => 125,
        ];
        $best = '100.00';
        $previous = '75.00';
        $questions = collect();
        $answers = [];
        $canRestart = false;

        $resultPage = view('practice-quiz.student.result', compact(
            'result', 'best', 'previous', 'questions', 'answers', 'canRestart'
        ))->render();

        $this->assertStringContainsString('>87.5</p>', $resultPage);
        $this->assertStringContainsString('Terbaik: 100', $resultPage);
        $this->assertStringContainsString('Dari sebelumnya: +12.5', $resultPage);
        $this->assertStringNotContainsString('87.50', $resultPage);
        $this->assertStringNotContainsString('100.00', $resultPage);
    }

    public function test_practice_history_chart_tooltip_and_pdf_render_the_same_score_format(): void
    {
        $row = (object) [
            'Result_ID' => 'R1',
            'Quiz_Title' => 'Latihan Nilai',
            'Revision_ID' => 'REVISION-1',
            'Attempt_Number' => 1,
            'Completed_At' => '2026-11-25 10:00:00',
            'Duration_Seconds' => 120,
            'Normalized_Score' => '87.50',
            'Correct_Count' => 7,
            'Incorrect_Count' => 2,
            'Unanswered_Count' => 1,
            'Attempt_Status' => 'SUBMITTED',
        ];
        $stats = [
            'count' => 4,
            'last' => '87.50',
            'best' => '100.00',
            'lowest' => '0.00',
            'average' => '83.33',
            'chart' => collect([['quiz' => 'Latihan Nilai', 'score' => '87.50', 'attempt' => 1]]),
            'chart_truncated' => false,
        ];
        $history = new LengthAwarePaginator([$row], 1, 15, 1, ['path' => '/student/practice-quizzes/history']);
        $quizzes = collect();
        $filters = [];
        $quizId = null;

        $historyPage = view('practice-quiz.student.history', compact(
            'history', 'stats', 'quizzes', 'filters', 'quizId'
        ))->render();

        foreach (['>87.5</', '>100</', '>0</', '>83.33</', 'title="Latihan Nilai · 87.5"'] as $expected) {
            $this->assertStringContainsString($expected, $historyPage);
        }
        $this->assertStringNotContainsString('>87.50</', $historyPage);
        $this->assertStringNotContainsString('title="Latihan Nilai · 87.50"', $historyPage);
        $this->assertStringNotContainsString('>100.00</', $historyPage);

        $companyProfile = ['name' => 'WMS'];
        $title = 'Semua Latihan';
        $student = ['Student_ID' => 'S1', 'Full_Name' => 'Siswa', 'Class_ID' => 'C1'];
        $generatedAt = Carbon::parse('2026-11-25 11:00:00');
        $rows = collect([$row]);
        $pdf = view('practice-quiz.pdf.history', compact(
            'companyProfile', 'title', 'student', 'filters', 'generatedAt', 'stats', 'rows'
        ))->render();

        foreach (['>87.5</', '>100</', '>0</', '>83.33</'] as $expected) {
            $this->assertStringContainsString($expected, $pdf);
        }
        $this->assertStringNotContainsString('>87.50</', $pdf);
        $this->assertStringNotContainsString('>100.00</', $pdf);
    }
}

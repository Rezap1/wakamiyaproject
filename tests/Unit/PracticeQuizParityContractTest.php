<?php

namespace Tests\Unit;

use App\Models\Practice\PracticeQuiz;
use App\Policies\PracticeQuizPolicy;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PracticeQuizParityContractTest extends TestCase
{
    public function test_practice_routes_have_role_and_operation_specific_throttles(): void
    {
        $expected = [
            'teacher.practice-quizzes.media.upload' => 'throttle:30,1,practice-media-upload',
            'teacher.practice-quizzes.media.staging' => 'throttle:120,1,practice-media-staging',
            'teacher.practice-quizzes.media' => 'throttle:240,1,practice-teacher-media',
            'student.practice-quizzes.start' => 'throttle:20,1,practice-start',
            'student.practice-quizzes.attempt-media' => 'throttle:600,1,practice-attempt-media',
            'student.practice-quizzes.autosave' => 'throttle:120,1,practice-autosave',
            'student.practice-quizzes.submit' => 'throttle:10,1,practice-submit',
            'student.practice-quizzes.result-media' => 'throttle:600,1,practice-result-media',
        ];
        foreach ($expected as $name => $middleware) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
        $this->assertContains('role:TEACHER', Route::getRoutes()->getByName('teacher.practice-quizzes.index')->gatherMiddleware());
        $this->assertContains('role:STUDENT', Route::getRoutes()->getByName('student.practice-quizzes.history.pdf')->gatherMiddleware());
    }

    public function test_teacher_form_preserves_regular_multimedia_and_android_picker_contracts(): void
    {
        $regular = file_get_contents(resource_path('views/quiz/teacher/form.blade.php'));
        $practice = file_get_contents(resource_path('views/practice-quiz/teacher/form.blade.php'));
        foreach (['snapshotAudioFile', 'file.arrayBuffer', 'FileReader', 'new File([bytes]', 'QMU-FILE-EMPTY', 'QMU-FILE-ACCESS', 'QMU-FILE-READ', 'QMU-REQUEST-SEND', 'QMU-NETWORK', 'startRecording', 'MediaRecorder', 'input.value = \'\''] as $contract) {
            $this->assertStringContainsString($contract, $regular);
            $this->assertStringContainsString($contract, $practice);
        }
        $this->assertStringContainsString('teacher.practice-quizzes.media.upload', $practice);
        $this->assertStringNotContainsString('name="Start_At"', $practice);
        $this->assertStringNotContainsString('name="End_At"', $practice);
        $this->assertStringNotContainsString('name="Class_IDs[]"', $practice);
        $this->assertStringContainsString('questions[${i}][Explanation]', $practice);
    }

    public function test_player_preserves_timer_autosave_navigation_and_audio_controls_without_keys(): void
    {
        $view = file_get_contents(resource_path('views/practice-quiz/student/player.blade.php'));
        foreach (['startTimer()', 'deadlineTimestamp()', 'flushSave()', 'retrySave()', 'pauseAllAudio()', 'data-quiz-audio', 'controls preload="none"', 'goTo(index + 1)', 'student.practice-quizzes.autosave'] as $contract) {
            $this->assertStringContainsString($contract, $view);
        }
        $this->assertStringContainsString('pb-48', $view);
        $service = file_get_contents(app_path('Services/PracticeQuiz/PracticeQuizService.php'));
        $this->assertStringContainsString("unset(\$row['Correct_Option'], \$row['Point'], \$row['Explanation'])", $service);
    }

    public function test_practice_schema_and_storage_are_isolated_from_regular_quiz(): void
    {
        $migration = file_get_contents(database_path('migrations/wms_mysql/2026_11_24_000001_create_practice_quiz_module.php'));
        foreach (['PRACTICE_QUIZ', 'PRACTICE_QUIZ_REVISION', 'PRACTICE_QUESTION_REVISION', 'PRACTICE_ATTEMPT', 'PRACTICE_ANSWER', 'PRACTICE_RESULT', 'PRACTICE_OPERATION'] as $table) {
            $this->assertStringContainsString("'{$table}'", $migration);
        }
        $this->assertStringNotContainsString("Schema::table('QUIZ", $migration);
        $config = require config_path('practice_quiz.php');
        $this->assertSame('practice-quiz-media', $config['media_root']);
        $media = file_get_contents(app_path('Services/PracticeQuiz/PracticeQuizMediaService.php'));
        $this->assertStringContainsString('practice_quiz_media_token_', $media);
    }

    public function test_teacher_policy_allows_only_the_owning_teacher_identity(): void
    {
        $policy = new PracticeQuizPolicy;
        $user = new GenericUser(['id' => 'U1', 'User_ID' => 'U1', 'password' => 'unused']);
        $quiz = new PracticeQuiz(['Teacher_ID' => 'T1']);

        $this->assertTrue($policy->view($user, $quiz, 'T1'));
        $this->assertTrue($policy->update($user, $quiz, 'T1'));
        $this->assertTrue($policy->delete($user, $quiz, 'T1'));
        $this->assertFalse($policy->view($user, $quiz, 'T2'));
        $this->assertFalse($policy->update($user, $quiz, 'T2'));
        $this->assertFalse($policy->delete($user, $quiz, 'T2'));
    }
}

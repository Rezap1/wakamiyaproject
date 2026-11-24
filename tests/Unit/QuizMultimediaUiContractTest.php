<?php

namespace Tests\Unit;

use Tests\TestCase;

class QuizMultimediaUiContractTest extends TestCase
{
    public function test_student_player_lazy_loads_only_active_media_and_has_unlimited_native_controls(): void
    {
        $source = file_get_contents(resource_path('views/quiz/student/player.blade.php'));

        $this->assertStringContainsString('preload="none"', $source);
        $this->assertStringContainsString('if (this.index !== questionIndex) return null', $source);
        $this->assertStringContainsString('pauseAllAudio()', $source);
        $this->assertStringContainsString('controls', $source);
        $this->assertStringNotContainsString('playCounter', $source);
        $this->assertStringNotContainsString('maximumReplay', $source);
        $this->assertStringContainsString('this.startTimer()', $source);
    }

    public function test_teacher_builder_uses_async_upload_and_centralized_limits(): void
    {
        $source = file_get_contents(resource_path('views/quiz/teacher/form.blade.php'));

        $this->assertStringContainsString('XMLHttpRequest', $source);
        $this->assertStringContainsString('mediaConfig.image_max_bytes', $source);
        $this->assertStringContainsString('mediaConfig.audio_max_duration_ms', $source);
        $this->assertStringContainsString('uploadingCount > 0', $source);
        $this->assertStringContainsString('Remove_Image', $source);
        $this->assertStringContainsString('Remove_Audio', $source);
        $this->assertStringNotContainsString("@js(config('quiz.media'))", $source);
        $this->assertStringNotContainsString('ffprobe_path', $source);
    }

    public function test_browser_audio_metadata_is_advisory_and_server_remains_authoritative(): void
    {
        $source = file_get_contents(resource_path('views/quiz/teacher/form.blade.php'));

        $this->assertStringContainsString('if (file.size > max) throw new Error', $source);
        $this->assertStringContainsString("return 'Metadata browser menunjukkan durasi di atas 01:30. File tetap diperiksa oleh server.'", $source);
        $this->assertStringContainsString("return 'Durasi audio tidak dapat dibaca browser. File tetap diperiksa oleh server.'", $source);
        $this->assertStringNotContainsString('if (duration * 1000 > Number(this.mediaConfig.audio_max_duration_ms)) throw', $source);
        $this->assertStringContainsString('const browserWarning = await this.browserValidate(file, type)', $source);
        $this->assertStringContainsString('q.Audio_Warning = browserWarning', $source);
        $this->assertStringContainsString('q[`${prefix}_Token`] = data.token', $source);
        $this->assertStringContainsString('q[`${prefix}_URL`] = data.preview_url', $source);
        $this->assertStringContainsString('q[`${prefix}_Error`] = error.message || \'Upload media gagal.\'', $source);
        $this->assertStringContainsString('@click="removeMedia(q, \'audio\')"', $source);
        $this->assertSame(2, substr_count($source, 'class="min-h-11 px-2 font-bold text-rose-700"'));
    }

    public function test_recording_uses_feature_detection_actual_mime_and_dedicated_backend_source(): void
    {
        $view = file_get_contents(resource_path('views/quiz/teacher/form.blade.php'));

        $this->assertStringContainsString('navigator.mediaDevices?.getUserMedia', $view);
        $this->assertStringContainsString('MediaRecorder.isTypeSupported', $view);
        $this->assertStringContainsString('recorder.mimeType', $view);
        $this->assertStringContainsString("body.append('source', source)", $view);
        $this->assertStringContainsString("source === 'recording'", $view);
        $this->assertStringContainsString('getTracks().forEach(track => track.stop())', $view);
        $this->assertStringContainsString('89500', $view);
        $this->assertStringContainsString('Recording_Discard', $view);
        $this->assertStringContainsString('cancelRecording(q)', $view);
    }

    public function test_upload_errors_distinguish_validation_session_timeout_and_real_network_failure(): void
    {
        $view = file_get_contents(resource_path('views/quiz/teacher/form.blade.php'));

        foreach ([401, 403, 409, 410, 413, 419, 422, 423, 429, 500, 503, 504] as $status) {
            $this->assertStringContainsString((string) $status, $view);
        }
        $this->assertStringContainsString('payload?.errors?.file', $view);
        $this->assertStringContainsString('Koneksi terputus. Silakan coba kembali.', $view);
        $this->assertStringNotContainsString('Upload media gagal. Periksa koneksi.', $view);
    }
}

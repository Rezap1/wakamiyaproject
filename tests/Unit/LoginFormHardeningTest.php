<?php

namespace Tests\Unit;

use Tests\TestCase;

class LoginFormHardeningTest extends TestCase
{
    public function test_login_form_prevents_concurrent_submissions_with_accessible_feedback(): void
    {
        $view = file_get_contents(resource_path('views/auth/login.blade.php'));

        $this->assertStringContainsString('id="login-form"', $view);
        $this->assertStringContainsString('id="login-submit"', $view);
        $this->assertStringContainsString('button.disabled = true', $view);
        $this->assertStringContainsString("form.dataset.submitting === 'true'", $view);
        $this->assertStringContainsString("button.setAttribute('aria-busy', 'true')", $view);
        $this->assertStringContainsString("label.textContent = 'MEMPROSES...'", $view);
        $this->assertStringContainsString('@csrf', $view);
    }
}

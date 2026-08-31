<?php

namespace Tests\Unit;

use Illuminate\Support\Js;
use Tests\TestCase;

class TeacherViewXssTest extends TestCase
{
    public function test_teacher_views_use_laravel_javascript_encoding(): void
    {
        foreach ([
            resource_path('views/teachers/create.blade.php'),
            resource_path('views/teachers/edit.blade.php'),
        ] as $viewPath) {
            $source = file_get_contents($viewPath);

            $this->assertStringContainsString('\\Illuminate\\Support\\Js::from(', $source, $viewPath);
            $this->assertStringNotContainsString('{!! json_encode(', $source, $viewPath);
        }
    }

    public function test_script_context_payload_escapes_html_significant_characters(): void
    {
        $payload = [
            'Full_Name' => "</script><script>alert('XSS')</script>",
            'symbols' => "<>&'\"",
        ];

        $encoded = Js::from($payload)->toHtml();

        $this->assertStringNotContainsString('</script>', strtolower($encoded));
        $this->assertStringContainsString('\\u003C', $encoded);
        $this->assertStringContainsString('\\u003E', $encoded);
        $this->assertStringContainsString('\\u0026', $encoded);
        $this->assertStringContainsString('\\u0027', $encoded);
        $this->assertStringContainsString('\\u0022', $encoded);
    }
}

<?php

namespace Tests\Support;

use Symfony\Component\Process\Process;

trait InteractsWithPdf
{
    protected function pdfText($response, string $name): string
    {
        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $directory = base_path('tmp/pdf-integrity/'.config('database.default'));
        if (!is_dir($directory)) { mkdir($directory, 0777, true); }
        $path = $directory.'/'.$name.'.pdf';
        file_put_contents($path, $response->getContent());
        $process = new Process([getenv('PDF_AUDIT_PYTHON') ?: 'python', base_path('tests/Support/pdf_text.py'), $path]);
        $process->mustRun();
        $text = preg_replace('/\s+/u', ' ', $process->getOutput());
        file_put_contents($directory.'/'.$name.'.txt', $text);

        return $text;
    }
}

<?php

namespace Tests\Unit;

use App\Http\Controllers\Academic\ScoreController;
use App\Repositories\GoogleSheets\AssessmentRepository;
use App\Repositories\GoogleSheets\StudentRepository;
use App\Services\Academic\AssessmentConfigService;
use App\Services\Academic\ScoreService;
use App\Services\Core\AssignmentService;
use Illuminate\Http\Request;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class ScoreExportConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_pdf_config_uses_the_same_inclusive_assessment_date_filter_and_formatter(): void
    {
        $scoreService = Mockery::mock(ScoreService::class);
        $scoreService->shouldReceive('getAll')->once()->andReturn(collect([
            ['Score_ID' => 'OUTSIDE', 'Student_ID' => 'STU-1', 'Assessment_Date' => '2026-10-31', 'Score' => '70.0000'],
            ['Score_ID' => 'START', 'Student_ID' => 'STU-1', 'Assessment_Date' => '2026-11-01', 'Score' => '89.5000'],
            ['Score_ID' => 'END', 'Student_ID' => 'STU-1', 'Assessment_Date' => '2026-11-30', 'Score' => '100.0000'],
        ]));
        $scoreService->shouldReceive('parseEvaluationDetails')->andReturn([]);

        $students = Mockery::mock(StudentRepository::class);
        $students->shouldReceive('fetchAll')->once()->andReturn(collect([
            ['Student_ID' => 'STU-1', 'Full_Name' => 'Aiko'],
        ]));
        $this->app->instance(StudentRepository::class, $students);

        $assessments = Mockery::mock(AssessmentRepository::class);
        $assessments->shouldReceive('fetchAll')->once()->andReturn(collect());
        $this->app->instance(AssessmentRepository::class, $assessments);

        $assignments = Mockery::mock(AssignmentService::class);
        $assignments->shouldReceive('getAll')->once()->andReturn(collect());
        $this->app->instance(AssignmentService::class, $assignments);

        $configService = Mockery::mock(AssessmentConfigService::class);
        $configService->shouldReceive('categoryLabel')->andReturn('Umum');
        $this->app->instance(AssessmentConfigService::class, $configService);

        $controller = new ScoreController($scoreService);
        $method = new ReflectionMethod($controller, 'getExportConfig');
        $config = $method->invoke($controller, Request::create('/academic/scores/export-pdf', 'GET', [
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-30',
        ]));

        $this->assertSame(['END', 'START'], $config['data']->pluck('Score_ID')->all());
        $this->assertSame(
            'Periode penilaian: 1 November 2026 s.d. 30 November 2026',
            $config['metadata']['scopeLabel']
        );

        $mappedStart = ($config['mapRow'])($config['data']->firstWhere('Score_ID', 'START'));
        $mappedEnd = ($config['mapRow'])($config['data']->firstWhere('Score_ID', 'END'));
        $this->assertSame('89,5', $mappedStart[3]);
        $this->assertSame('100', $mappedEnd[3]);
    }
}

<?php

namespace Tests\Unit;

use App\Http\Requests\StoreAssignmentRequest;
use App\Http\Requests\UpdateAssignmentRequest;
use App\Support\Academic\AssignmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class AssignmentStatusContractTest extends TestCase
{
    public function test_assignment_status_labels_are_indonesian_and_values_are_canonical(): void
    {
        $this->assertSame(
            ['PUBLISHED', 'DRAFT', 'CLOSED', 'ACTIVE', 'ARCHIVED'],
            AssignmentStatus::values()
        );

        $this->assertSame('Terpublikasi', AssignmentStatus::label('published'));
        $this->assertSame('Draf', AssignmentStatus::label('draft'));
        $this->assertSame('Ditutup', AssignmentStatus::label('closed'));
        $this->assertSame('Aktif', AssignmentStatus::label('active'));
        $this->assertSame('Diarsipkan', AssignmentStatus::label('archived'));
    }

    public function test_store_assignment_request_accepts_legacy_status_field_aliases(): void
    {
        $request = $this->makeRequest(StoreAssignmentRequest::class, [
            'Title' => 'Tugas Kelas',
            'Class_ID' => 'CLS-001',
            'Teacher_ID' => 'TCH-001',
            'Deadline' => '2026-09-08 10:00:00',
            'publication_status' => 'draft',
            'Description' => 'Instruksi singkat',
        ]);

        $this->invokePrepareForValidation($request);

        $this->assertSame('DRAFT', $request->input('Status'));

        $validated = $this->validatedData($request);
        $this->assertSame('DRAFT', $validated['Status']);
    }

    public function test_update_assignment_request_accepts_assignment_status_alias(): void
    {
        $request = $this->makeRequest(UpdateAssignmentRequest::class, [
            'Title' => 'Tugas Perbaikan',
            'Class_ID' => 'CLS-001',
            'Teacher_ID' => 'TCH-001',
            'Deadline' => '2026-09-09 10:00:00',
            'Assignment_Status' => 'published',
            'Description' => 'Instruksi perbaikan',
        ]);

        $this->invokePrepareForValidation($request);

        $this->assertSame('PUBLISHED', $request->input('Status'));

        $validated = $this->validatedData($request);
        $this->assertSame('PUBLISHED', $validated['Status']);
    }

    /**
     * @param class-string<FormRequest> $requestClass
     */
    private function makeRequest(string $requestClass, array $payload): FormRequest
    {
        $request = $requestClass::create('/assignments', 'POST', $payload);
        $request->setContainer($this->app);

        return $request;
    }

    private function invokePrepareForValidation(FormRequest $request): void
    {
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);
    }

    private function validatedData(FormRequest $request): array
    {
        $validator = Validator::make($request->all(), $request->rules());

        $this->assertTrue($validator->passes(), var_export($validator->errors()->toArray(), true));

        return $validator->validated();
    }
}

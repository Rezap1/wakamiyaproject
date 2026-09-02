<?php

namespace Tests\Unit;

use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Requests\UpdateStudentRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UploadLimitsTest extends TestCase
{
    public function test_all_application_upload_rules_use_the_five_megabyte_ceiling(): void
    {
        $this->assertSame(5120, config('upload.max_kb'));
        $this->assertSame(5 * 1024 * 1024, config('upload.max_bytes'));

        $rules = [
            [new StoreStudentRequest(), 'Photo'],
            [new UpdateStudentRequest(), 'Photo'],
            [new StoreEmployeeRequest(), 'Profile_Photo'],
            [new UpdateEmployeeRequest(), 'Profile_Photo'],
            [new StoreCompanyRequest(), 'Company_Logo'],
            [new StoreCompanyRequest(), 'Company_Stamp'],
            [new UpdateCompanyRequest(), 'Company_Logo'],
            [new UpdateCompanyRequest(), 'Company_Stamp'],
            [new StorePaymentRequest(), 'Proof_File'],
        ];

        foreach ($rules as [$request, $field]) {
            $rule = $request->rules()[$field];
            $this->assertStringContainsString('max:5120', $rule, $field);
        }
    }

    public function test_image_uploads_over_five_megabytes_are_rejected(): void
    {
        $uploads = [
            [new StoreStudentRequest(), 'Photo', UploadedFile::fake()->image('student.jpg')->size(5121)],
            [new StoreEmployeeRequest(), 'Profile_Photo', UploadedFile::fake()->image('employee.jpg')->size(5121)],
            [new StoreCompanyRequest(), 'Company_Logo', UploadedFile::fake()->image('logo.jpg')->size(5121)],
        ];

        foreach ($uploads as [$request, $field, $file]) {
            $validator = Validator::make([$field => $file], [$field => $request->rules()[$field]]);
            $this->assertTrue($validator->fails(), $field . ' should reject files above 5MB');
            $this->assertArrayHasKey($field, $validator->errors()->toArray());
        }
    }
}

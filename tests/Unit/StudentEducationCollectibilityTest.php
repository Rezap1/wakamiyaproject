<?php

namespace Tests\Unit;

use App\Support\Finance\StudentEducationCollectibility;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentEducationCollectibilityTest extends TestCase
{
    #[DataProvider('operationalLifecycleProvider')]
    public function test_operational_master_student_lifecycle_is_collectible(string $status): void
    {
        $rule = new StudentEducationCollectibility;

        $this->assertTrue($rule->isCollectible($this->student($status, 'TRUE')));
    }

    public static function operationalLifecycleProvider(): array
    {
        return [
            'production active' => ['Aktif Belajar'],
            'waiting class' => ['Menunggu Kelas'],
            'study leave' => ['Cuti'],
            'legacy active' => ['Aktif'],
        ];
    }

    #[DataProvider('nonOperationalLifecycleProvider')]
    public function test_non_operational_master_student_lifecycle_is_not_collectible(string $status, string $active): void
    {
        $rule = new StudentEducationCollectibility;

        $this->assertFalse($rule->isCollectible($this->student($status, $active)));
    }

    public static function nonOperationalLifecycleProvider(): array
    {
        return [
            'production drop out' => ['Drop Out', 'FALSE'],
            'drop out fails safe even with stale active flag' => ['Drop Out', 'TRUE'],
            'alumni' => ['Alumni', 'FALSE'],
            'generic inactive' => ['Aktif Belajar', 'FALSE'],
        ];
    }

    public function test_disabled_or_missing_login_account_is_not_an_input_to_collectibility(): void
    {
        $rule = new StudentEducationCollectibility;
        $student = $this->student('Aktif Belajar', 'TRUE');

        $disabledAccountProjection = array_merge($student, [
            'User_Is_Active' => 'FALSE',
            'User_Status' => 'DELETED',
        ]);

        $this->assertTrue($rule->isCollectible($disabledAccountProjection));
        $this->assertArrayNotHasKey('User_Is_Active', $student);
        $this->assertTrue($rule->isCollectible($student));
    }

    public function test_bulk_index_deduplicates_lookup_work_by_student_id(): void
    {
        $rule = new StudentEducationCollectibility;
        $index = $rule->index([
            $this->student('Aktif Belajar', 'TRUE', 'STU-A'),
            $this->student('Drop Out', 'FALSE', 'STU-B'),
        ]);

        $this->assertTrue($index['STU-A']['collectible']);
        $this->assertFalse($index['STU-B']['collectible']);
        $this->assertSame('Drop Out', $index['STU-B']['lifecycle_label']);
    }

    private function student(string $status, string $active, string $id = 'STU-1'): array
    {
        return [
            'Student_ID' => $id,
            'Enrollment_Status' => $status,
            'Is_Active' => $active,
        ];
    }
}

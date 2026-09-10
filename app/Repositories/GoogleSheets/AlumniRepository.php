<?php

namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\AlumniRepositoryInterface;

class AlumniRepository extends BaseSheetRepository implements AlumniRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_ALUMNI';
        $this->cacheKey = 'alumni_sheet';
        $this->primaryKey = 'Alumni_ID';
    }

    public function findById(string $id) { return $this->fetchAll()->firstWhere($this->primaryKey, $id); }
    public function findByIdFresh($id) { return parent::findByIdFresh($id); }
    public function findByStudentId(string $studentId)
    {
        $rows = $this->fetchAll()->filter(function ($row) use ($studentId) {
            return strcasecmp(trim((string) ($row['Student_ID'] ?? '')), trim($studentId)) === 0;
        });

        return $rows->first(fn ($row) => strtoupper(trim((string) ($row['Is_Active'] ?? 'TRUE'))) !== 'FALSE')
            ?: $rows->first();
    }
    public function create(array $data) { return $this->append($data); }
    public function update($id, array $data) { return parent::update($id, $data); }
    public function softDelete($id) { return $this->update($id, ['Is_Active' => 'FALSE', 'Updated_At' => now()->toDateTimeString()]); }

    protected function assertExpectedHeaders(array $headers): void
    {
        parent::assertExpectedHeaders($headers);

        $required = config('alumni.required_headers', []);
        $missing = array_values(array_diff($required, $headers));
        if ($missing !== []) {
            throw new \RuntimeException("Sheet {$this->sheetName} belum memenuhi schema Alumni. Kolom wajib: " . implode(', ', $missing));
        }
    }

    protected function assertDurableWriteSchema(array $headers): void
    {
        $this->assertExpectedHeaders($headers);
    }
}

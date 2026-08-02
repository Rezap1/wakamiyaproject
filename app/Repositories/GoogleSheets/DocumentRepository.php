<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\DocumentRepositoryInterface;

class DocumentRepository extends BaseSheetRepository implements DocumentRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_DOCUMENT';
        $this->cacheKey = 'document_sheet';
        $this->primaryKey = 'Document_ID';
    }

    public function getAll() { return $this->fetchAll(); }
    public function getById($id) { return $this->fetchAll()->firstWhere($this->primaryKey, $id); }
    public function create(array $data) { return $this->append($data); }
    public function update($id, array $data) { return $this->updateRow($id, $data); }
    public function delete($id) { return $this->updateRow($id, ['Status' => 'Cancelled']); }
}
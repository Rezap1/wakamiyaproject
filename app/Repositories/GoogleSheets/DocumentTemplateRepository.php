<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\DocumentTemplateRepositoryInterface;

class DocumentTemplateRepository extends BaseSheetRepository implements DocumentTemplateRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_DOCUMENT_TEMPLATE';
        $this->cacheKey = 'document_template_sheet';
        $this->primaryKey = 'Template_ID';
    }

    public function getAll() { return $this->fetchAll(); }
    public function getById($id) { return $this->fetchAll()->firstWhere($this->primaryKey, $id); }
    public function create(array $data) { return $this->append($data); }
    public function update($id, array $data) { return $this->updateRow($id, $data); }
    public function delete($id) { return $this->updateRow($id, ['Status' => 'Inactive']); }
}